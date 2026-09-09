"""OpenAI-compatible LLM provider for Notezy AI."""
from __future__ import annotations

import json
import os
import urllib.error
import urllib.request
from typing import Any

from .gemini import LlmError, LlmProvider


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
            "max_tokens": 4096,
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
            raise LlmError(f"OpenAI API error ({exc.code}): {raw}", 502) from None
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

    def embed(self, text: str) -> list[float]:
        """OpenAI text-embedding-3-small compatible."""
        if not self.api_key:
            return []
        embed_model = os.environ.get("OPENAI_EMBED_MODEL", "text-embedding-3-small")
        payload = {"model": embed_model, "input": text[:8000]}
        body = json.dumps(payload).encode("utf-8")
        req = urllib.request.Request(
            f"{self.base_url}/embeddings",
            data=body,
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {self.api_key}",
            },
            method="POST",
        )
        try:
            with urllib.request.urlopen(req, timeout=15) as resp:
                data = json.loads(resp.read().decode("utf-8"))
            return (data.get("data") or [{}])[0].get("embedding", [])
        except Exception:
            return []
