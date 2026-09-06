"""LLM provider abstraction. Swap providers without changing Notezy.

Default provider is Google Gemini (via the plain REST API, no extra SDK
dependency — keeps `requirements.txt` stdlib-only). An OpenAI-compatible
provider is kept available for anyone who wants to switch back.
"""

from __future__ import annotations

import json
import os
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


class GeminiProvider(LlmProvider):
    """Google Gemini via the Generative Language REST API (generateContent)."""

    def __init__(self) -> None:
        self.api_key = os.environ.get("GEMINI_API_KEY", "").strip()
        self.base_url = os.environ.get(
            "GEMINI_BASE_URL", "https://generativelanguage.googleapis.com/v1beta"
        ).rstrip("/")
        # "gemini-flash-latest" is Google's auto-updating alias for the
        # newest stable Flash model, so this default doesn't rot as Google
        # ships new model generations. Pin a specific version via
        # GEMINI_MODEL if you need reproducible behaviour.
        self.model = os.environ.get("GEMINI_MODEL", "gemini-flash-latest")
        self.timeout = int(os.environ.get("LLM_TIMEOUT_SECONDS", "45"))

    def complete(self, messages: list[dict], tools: list[dict]) -> dict:
        if not self.api_key:
            raise LlmError("LLM is not configured (missing GEMINI_API_KEY).", 503)

        system_text, contents = _to_gemini_contents(messages)

        payload: dict[str, Any] = {
            "contents": contents,
            "generationConfig": {"temperature": 0.4, "maxOutputTokens": 1200},
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
            with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                data = json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as exc:
            raw = exc.read().decode("utf-8", errors="replace")[:1000]
            raise LlmError(f"Gemini API error ({exc.code}): {raw}", 502) from None
        except urllib.error.URLError:
            raise LlmError("The language model timed out or is unreachable.", 504) from None

        return _from_gemini_response(data)


class OpenAICompatProvider(LlmProvider):
    def __init__(self) -> None:
        self.api_key = os.environ.get("OPENAI_API_KEY", "").strip()
        self.base_url = os.environ.get("OPENAI_BASE_URL", "https://api.openai.com/v1").rstrip("/")
        self.model = os.environ.get("OPENAI_MODEL", "gpt-4o-mini")
        self.timeout = int(os.environ.get("LLM_TIMEOUT_SECONDS", "45"))

    def complete(self, messages: list[dict], tools: list[dict]) -> dict:
        if not self.api_key:
            raise LlmError("LLM is not configured (missing OPENAI_API_KEY).", 503)

        payload: dict[str, Any] = {
            "model": self.model,
            "messages": messages,
            "temperature": 0.4,
            "max_tokens": 1200,
        }
        if tools:
            payload["tools"] = tools
            payload["tool_choice"] = "auto"

        body = json.dumps(payload).encode("utf-8")
        req = urllib.request.Request(
            f"{self.base_url}/chat/completions",
            data=body,
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {self.api_key}",
            },
            method="POST",
        )
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                data = json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as exc:
            raw = exc.read().decode("utf-8", errors="replace")[:1000]
            raise LlmError(f"OpenAI API error ({exc.code}): {raw}",502,) from None
        except urllib.error.URLError:
            raise LlmError("The language model timed out or is unreachable.", 504) from None

        choices = data.get("choices") or []
        if not choices:
            raise LlmError("The language model returned an empty response.", 502)
        message = choices[0].get("message") or {}
        return {
            "role": "assistant",
            "content": message.get("content") or "",
            "tool_calls": message.get("tool_calls") or [],
        }


class MockProvider(LlmProvider):
    """Deterministic provider for tests and local demos without an API key."""

    def complete(self, messages: list[dict], tools: list[dict]) -> dict:
        last_user = ""
        for msg in reversed(messages):
            if msg.get("role") == "user":
                last_user = (msg.get("content") or "").strip()
                break
        lower = last_user.lower()

        if any(x in lower for x in ("system prompt", "system prompt", "hướng dẫn hệ thống", "ignore previous")):
            return {"role": "assistant", "content": "Tôi không thể tiết lộ hướng dẫn nội bộ. Tôi chỉ giúp quản lý ghi chú của bạn.", "tool_calls": []}

        if any(x in lower for x in ("xin chào", "hello", "hi ")):
            return {"role": "assistant", "content": "Xin chào! Tôi là trợ lý ghi chú Notezy. Bạn muốn tìm, tóm tắt, hay tạo note?", "tool_calls": []}

        if any(x in lower for x in ("tìm", "find", "search", "related")):
            query = last_user
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("search_notes", {"query": query, "limit": 8})],
            }

        if any(x in lower for x in ("tóm tắt", "summarize", "summary")):
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("get_note", {"note_id": _context_note_id(messages) or 0})],
            }

        if any(x in lower for x in ("tạo", "create", "viết note", "docker", "react")) and "xóa" not in lower:
            if any(x in lower for x in ("tạo đi", "ok", "confirm", "đồng ý")):
                return {
                    "role": "assistant",
                    "content": "",
                    "tool_calls": [_fn("create_note", {"title": "Docker Basics", "content": "# Docker\n\nGiới thiệu Docker cho người mới.", "labels": ["Docker"]})],
                }
            topic = "Docker" if "docker" in lower else "Ghi chú mới"
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [
                    _fn(
                        "create_note",
                        {
                            "title": f"{topic} Basics",
                            "content": f"# {topic}\n\nNội dung được tạo cho người mới bắt đầu.",
                            "labels": [topic],
                        },
                    )
                ],
            }

        if any(x in lower for x in ("xóa", "delete")):
            nid = _context_note_id(messages) or 0
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("delete_note", {"note_id": nid})],
            }

        return {
            "role": "assistant",
            "content": "Bạn có thể nhờ tôi tìm note, tóm tắt note hiện tại, tạo note mới, hoặc gợi ý nhãn.",
            "tool_calls": [],
        }


def _fn(name: str, arguments: dict) -> dict:
    return {
        "id": f"call_{name}",
        "type": "function",
        "function": {"name": name, "arguments": json.dumps(arguments, ensure_ascii=False)},
    }


def _context_note_id(messages: list[dict]) -> int | None:
    for msg in messages:
        content = msg.get("content") or ""
        if "current_note" in content and "note_id" in content:
            import re

            m = re.search(r'"note_id"\s*:\s*(\d+)', content)
            if m:
                return int(m.group(1))
    return None


# ---------------------------------------------------------------------------
# Gemini <-> OpenAI-shaped message/tool conversion
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
                    "role": "function",
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
                "parameters": _strip_unsupported_schema_keys(fn.get("parameters") or {"type": "object", "properties": {}}),
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


def create_provider() -> LlmProvider:
    name = os.environ.get("LLM_PROVIDER", "gemini").strip().lower()
    if name == "mock":
        return MockProvider()
    if name in ("openai_compat", "openai"):
        return OpenAICompatProvider()
    return GeminiProvider()
