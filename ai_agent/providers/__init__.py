"""LLM provider abstraction for Notezy AI."""
from __future__ import annotations

from .gemini import GeminiProvider, LlmError, LlmProvider
from .mock import MockProvider

import os


def create_provider() -> LlmProvider:
    name = os.environ.get("LLM_PROVIDER", "gemini").strip().lower()
    if name == "mock":
        return MockProvider()
    if name in ("openai_compat", "openai"):
        from .openai_compat import OpenAICompatProvider
        return OpenAICompatProvider()
    return GeminiProvider()


__all__ = ["LlmProvider", "LlmError", "GeminiProvider", "MockProvider", "create_provider"]
