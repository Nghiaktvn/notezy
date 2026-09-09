"""Notezy AI Copilot — main orchestrator (v2).

Architecture:
    PHP → POST /v1/chat → AiAgent.run(payload) → Gemini → tool_calls / answer

Pipeline per request:
    1. ConversationMemory.update_from_messages()  — rebuild short-term state
    2. IntentClassifier.classify()                — fast intent detection
    3. ContextBuilder.build()                     — enrich context
    4. Safety check                               — prompt injection guard
    5. Retriever.build_query_hints()              — keyword or semantic hints
    6. Build LLM message list
    7. Gemini.complete()                          — tool_calls or text
    8. Return {content, tool_calls, retrieval, intent}
"""

from __future__ import annotations

import json
from typing import Any

from .providers import LlmError, create_provider
from .prompts.system import SYSTEM_PROMPT, build_context_message
from .tools import ALL_TOOLS, tool_definitions
from .rag.retriever import Retriever
from .rag.shared import get_shared_vector_store
from .core.intent import IntentClassifier
from .core.memory import ConversationMemory
from .core.context import ContextBuilder
from .core.safety import check_injection


# Shared singletons (module-level, reused across requests)
# NOTE: _vector_store MUST use get_shared_vector_store() so the
#       NoteIndexer in server.py and this Retriever reference the
#       same in-memory store. Without this, newly-indexed notes are
#       invisible to semantic search until server restart.
_vector_store = get_shared_vector_store()
_retriever = Retriever(vector_store=_vector_store)
_intent_clf = IntentClassifier()
_ctx_builder = ContextBuilder()


class AiAgent:
    """Notezy AI Copilot — stateless per-request agent."""

    def __init__(self) -> None:
        self.provider = create_provider()

    def run(self, payload: dict[str, Any]) -> dict[str, Any]:
        messages_in: list[dict] = payload.get("messages") or []
        # Fallback: bare "message" payloads (no history) still get a user turn,
        # otherwise contents would be empty and Gemini returns 400.
        if not messages_in and payload.get("message"):
            messages_in = [{"role": "user", "content": str(payload["message"])}]
        raw_context: dict = payload.get("context") or {}
        tool_results: list[dict] | None = payload.get("tool_results")
        user_id = raw_context.get("user_id")

        # ── 1. Rebuild conversation memory ───────────────────────────────────
        conv_mem = ConversationMemory()
        conv_mem.update_from_messages(messages_in)

        # ── 2. Classify intent ───────────────────────────────────────────────
        last_user_text = _last_user_text(messages_in)
        intent = _intent_clf.classify(last_user_text, raw_context)

        # ── 3. Safety check ──────────────────────────────────────────────────
        is_injected, injection_reason = check_injection(last_user_text)
        if is_injected:
            return {
                "content": "Tôi không thể xử lý yêu cầu này.",
                "tool_calls": [],
                "retrieval": {},
                "intent": {"intent": "blocked", "confidence": 1.0},
            }

        # ── 4. Enrich context ────────────────────────────────────────────────
        enriched_ctx = _ctx_builder.build(raw_context, messages_in, conv_mem, user_id)

        # ── 5. Retrieval hints ───────────────────────────────────────────────
        retrieval = _retriever.build_query_hints(last_user_text)

        # ── 6. Build LLM messages ────────────────────────────────────────────
        # Optional user memory hint in system
        mem_hint = _ctx_builder.user_memory_hint(user_id)
        system_suffix = f"\n\n{mem_hint}" if mem_hint else ""

        llm_messages: list[dict] = [
            {"role": "system", "content": SYSTEM_PROMPT + system_suffix},
            {"role": "system", "content": build_context_message(enriched_ctx)},
        ]

        for msg in messages_in:
            role = msg.get("role")
            if role not in ("user", "assistant", "tool"):
                continue
            entry: dict = {"role": role, "content": msg.get("content") or ""}
            if role == "assistant" and msg.get("tool_calls"):
                entry["tool_calls"] = msg["tool_calls"]
            if role == "tool":
                entry["tool_call_id"] = msg.get("tool_call_id") or "tool"
                entry["name"] = msg.get("name") or "tool"
            llm_messages.append(entry)

        # Inject tool results from PHP execution
        if tool_results:
            for tr in tool_results:
                llm_messages.append(
                    {
                        "role": "tool",
                        "tool_call_id": tr.get("tool_call_id") or "tool",
                        "name": tr.get("name") or "tool",
                        "content": json.dumps(
                            tr.get("result", {}), ensure_ascii=False
                        )[:10000],
                    }
                )

        # ── 7. Call Gemini ───────────────────────────────────────────────────
        completion = self.provider.complete(llm_messages, tool_definitions())
        tool_calls = _sanitize_tool_calls(completion.get("tool_calls") or [])
        content = (completion.get("content") or "").strip()

        # ── 8. Return response ───────────────────────────────────────────────
        return {
            "content": content,
            "tool_calls": tool_calls,
            "retrieval": retrieval,
            "intent": {
                "intent": intent.intent,
                "confidence": intent.confidence,
                "target": intent.target,
            },
        }


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _sanitize_tool_calls(raw: list) -> list[dict]:
    """Validate and clean tool_calls from Gemini. Strip user_id injection."""
    cleaned = []
    for call in raw[:6]:   # Max 6 tool calls per turn
        if not isinstance(call, dict):
            continue
        fn = call.get("function") or {}
        name = (fn.get("name") or "").strip()
        if name not in ALL_TOOLS:
            continue
        args_raw = fn.get("arguments") or "{}"
        try:
            args = json.loads(args_raw) if isinstance(args_raw, str) else args_raw
        except json.JSONDecodeError:
            continue
        if not isinstance(args, dict):
            continue
        # Never trust user_id from the model
        args.pop("user_id", None)
        args.pop("userId", None)
        cleaned.append(
            {
                "id": str(call.get("id") or name)[:64],
                "name": name,
                "arguments": args,
            }
        )
    return cleaned


def _last_user_text(messages: list) -> str:
    for msg in reversed(messages):
        if msg.get("role") == "user":
            return str(msg.get("content") or "")
    return ""


# ---------------------------------------------------------------------------
# Entry point (called by server.py)
# ---------------------------------------------------------------------------

def handle_request(payload: dict) -> dict:
    try:
        return {"ok": True, "data": AiAgent().run(payload)}
    except LlmError as exc:
        return {"ok": False, "error": str(exc), "status": exc.status}
    except Exception as exc:
        import traceback
        traceback.print_exc()
        return {
            "ok": False,
            "error": f"AI agent failed: {exc}",
            "status": 500,
        }
