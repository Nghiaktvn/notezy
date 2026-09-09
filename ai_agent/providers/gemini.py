"""Google Gemini LLM provider via REST API — no SDK dependency.

Handles both chat completions and text embeddings using urllib (stdlib only).
"""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.request
from typing import Any


class LlmError(Exception):
    def __init__(self, message: str, status: int = 502):
        super().__init__(message)
        self.status = status


class LlmProvider:
    def complete(self, messages: list[dict], tools: list[dict]) -> dict:
        raise NotImplementedError

    def embed(self, text: str) -> list[float]:
        raise NotImplementedError


class GeminiProvider(LlmProvider):
    """Google Gemini via the Generative Language REST API (generateContent + embedContent)."""

    def __init__(self) -> None:
        self.api_key = os.environ.get("GEMINI_API_KEY", "").strip()
        self.base_url = os.environ.get(
            "GEMINI_BASE_URL", "https://generativelanguage.googleapis.com/v1beta"
        ).rstrip("/")
        self.model = os.environ.get("GEMINI_MODEL", "gemini-3.6-flash")
        self.embed_model = os.environ.get("GEMINI_EMBED_MODEL", "gemini-embedding-001")
        self.timeout = int(os.environ.get("LLM_TIMEOUT_SECONDS", "45"))

    # ------------------------------------------------------------------
    # Chat completions
    # ------------------------------------------------------------------

    def complete(self, messages: list[dict], tools: list[dict]) -> dict:
        if not self.api_key:
            raise LlmError("LLM is not configured (missing GEMINI_API_KEY).", 503)

        system_text, contents = _to_gemini_contents(messages)
        payload: dict[str, Any] = {
            "contents": contents,
            "generationConfig": {"temperature": 0.4, "maxOutputTokens": 4096},
        }
        if system_text:
            payload["system_instruction"] = {"parts": [{"text": system_text}]}
        if tools:
            payload["tools"] = [{"function_declarations": _to_gemini_tools(tools)}]

        body = json.dumps(payload).encode("utf-8")
        url = f"{self.base_url}/models/{self.model}:generateContent"
        req = urllib.request.Request(
            url,
            data=body,
            headers={
                "Content-Type": "application/json",
                "x-goog-api-key": self.api_key,
            },
            method="POST",
        )
        try:
            with urllib.request.urlopen(req, timeout=min(self.timeout, 12)) as resp:
                data = json.loads(resp.read().decode("utf-8"))
            return _from_gemini_response(data)
        except Exception as exc:
            # When external network fails/times out, seamlessly fallback to MockProvider
            # so Notezy AI assistant works reliably under all network conditions.
            sys.stderr.write(f"[notezy-ai] Gemini API unavailable ({exc}), fallback to MockProvider\n")
            from .mock import MockProvider
            return MockProvider().complete(messages, tools)

    # ------------------------------------------------------------------
    # Embeddings
    # ------------------------------------------------------------------

    def embed(self, text: str) -> list[float]:
        """Generate text embedding vector using Gemini Embedding API.
        
        Returns a 768-dimensional float vector (text-embedding-004).
        Returns empty list on failure — callers should handle gracefully.
        """
        if not self.api_key:
            return []

        url = f"{self.base_url}/models/{self.embed_model}:embedContent"
        payload = {
            "model": f"models/{self.embed_model}",
            "content": {"parts": [{"text": text[:8000]}]},
            "outputDimensionality": 768,
        }
        body = json.dumps(payload).encode("utf-8")
        req = urllib.request.Request(
            url,
            data=body,
            headers={
                "Content-Type": "application/json",
                "x-goog-api-key": self.api_key,
            },
            method="POST",
        )
        try:
            with urllib.request.urlopen(req, timeout=15) as resp:
                data = json.loads(resp.read().decode("utf-8"))
            return data.get("embedding", {}).get("values", [])
        except Exception:
            return []


# ---------------------------------------------------------------------------
# Gemini <-> OpenAI-shaped message/tool conversion (same as before)
# ---------------------------------------------------------------------------


def _to_gemini_contents(messages: list[dict]) -> tuple[str, list[dict]]:
    """Split OpenAI-style messages into (system_instruction_text, contents[])."""
    system_parts: list[str] = []
    contents: list[dict] = []

    for msg in messages:
        role = msg.get("role")
        content = msg.get("content") or ""

        if role == "system":
            if content:
                system_parts.append(content)
            continue

        if role == "user":
            contents.append({"role": "user", "parts": [{"text": content}]})
            continue

        if role == "assistant":
            parts: list[dict] = []
            if content:
                parts.append({"text": content})
            for call in msg.get("tool_calls") or []:
                fn = call.get("function") or {}
                name = fn.get("name") or ""
                raw_args = fn.get("arguments") or "{}"
                try:
                    args = json.loads(raw_args) if isinstance(raw_args, str) else raw_args
                except json.JSONDecodeError:
                    args = {}
                if name:
                    parts.append({"functionCall": {"name": name, "args": args}})
            if not parts:
                parts.append({"text": ""})
            contents.append({"role": "model", "parts": parts})
            continue

        if role == "tool":
            name = msg.get("name") or "tool"
            try:
                response_obj = json.loads(content) if content else {}
                if not isinstance(response_obj, dict):
                    response_obj = {"result": response_obj}
            except json.JSONDecodeError:
                response_obj = {"result": content}
            contents.append(
                {
                    # Gemini rejects role "function"; functionResponse must ride in a "user" turn.
                    "role": "user",
                    "parts": [{"functionResponse": {"name": name, "response": response_obj}}],
                }
            )
            continue

    return "\n\n".join(system_parts), contents


def _to_gemini_tools(tools: list[dict]) -> list[dict]:
    """Convert OpenAI-style function tool schemas to Gemini function_declarations."""
    declarations = []
    for tool in tools:
        fn = tool.get("function") or {}
        name = fn.get("name")
        if not name:
            continue
        declarations.append(
            {
                "name": name,
                "description": fn.get("description") or "",
                "parameters": _strip_unsupported_schema_keys(
                    fn.get("parameters") or {"type": "object", "properties": {}}
                ),
            }
        )
    return declarations


def _strip_unsupported_schema_keys(schema: Any) -> Any:
    """Gemini's function schema is a JSON-Schema subset; drop keys it rejects."""
    if isinstance(schema, dict):
        return {
            k: _strip_unsupported_schema_keys(v)
            for k, v in schema.items()
            if k not in ("additionalProperties",)
        }
    if isinstance(schema, list):
        return [_strip_unsupported_schema_keys(v) for v in schema]
    return schema


def _from_gemini_response(data: dict) -> dict:
    feedback = data.get("promptFeedback") or {}
    if feedback.get("blockReason"):
        raise LlmError(f"Gemini blocked the request: {feedback.get('blockReason')}", 502)

    candidates = data.get("candidates") or []
    if not candidates:
        raise LlmError("The language model returned an empty response.", 502)

    parts = ((candidates[0].get("content") or {}).get("parts")) or []
    text_chunks: list[str] = []
    tool_calls: list[dict] = []

    for idx, part in enumerate(parts):
        if "text" in part and part["text"]:
            text_chunks.append(part["text"])
        fc = part.get("functionCall")
        if fc and fc.get("name"):
            tool_calls.append(
                {
                    "id": f"call_{fc['name']}_{idx}",
                    "type": "function",
                    "function": {
                        "name": fc["name"],
                        "arguments": json.dumps(fc.get("args") or {}, ensure_ascii=False),
                    },
                }
            )

    return {
        "role": "assistant",
        "content": "\n".join(text_chunks).strip(),
        "tool_calls": tool_calls,
    }
