"""Notezy AI agent: prompts + LLM + tool requests. Does not touch MySQL."""

from __future__ import annotations

import json
from typing import Any

try:
    from .llm_provider import LlmError, create_provider
    from .prompts import SYSTEM_PROMPT, build_context_message
    from .retriever import Retriever
    from .tools import ALL_TOOLS, tool_definitions
except ImportError:
    from llm_provider import LlmError, create_provider
    from prompts import SYSTEM_PROMPT, build_context_message
    from retriever import Retriever
    from tools import ALL_TOOLS, tool_definitions


class AiAgent:
    def __init__(self) -> None:
        self.provider = create_provider()
        self.retriever = Retriever()

    def run(self, payload: dict[str, Any]) -> dict[str, Any]:
        messages_in = payload.get("messages") or []
        context = payload.get("context") or {}
        tool_results = payload.get("tool_results")

        llm_messages: list[dict] = [{"role": "system", "content": SYSTEM_PROMPT}]
        llm_messages.append({"role": "system", "content": build_context_message(context)})

        for msg in messages_in:
            role = msg.get("role")
            if role not in ("user", "assistant", "tool"):
                continue
            entry = {"role": role, "content": msg.get("content") or ""}
            if role == "assistant" and msg.get("tool_calls"):
                entry["tool_calls"] = msg["tool_calls"]
            if role == "tool":
                entry["tool_call_id"] = msg.get("tool_call_id") or "tool"
                entry["name"] = msg.get("name") or "tool"
            llm_messages.append(entry)

        if tool_results:
            for tr in tool_results:
                llm_messages.append(
                    {
                        "role": "tool",
                        "tool_call_id": tr.get("tool_call_id") or "tool",
                        "name": tr.get("name") or "tool",
                        "content": json.dumps(tr.get("result", {}), ensure_ascii=False)[:8000],
                    }
                )

        completion = self.provider.complete(llm_messages, tool_definitions())
        tool_calls = _sanitize_tool_calls(completion.get("tool_calls") or [])
        content = (completion.get("content") or "").strip()
        return {
            "content": content,
            "tool_calls": tool_calls,
            "retrieval": self.retriever.build_query_hints(
                _last_user_text(messages_in)
            ),
        }


def _sanitize_tool_calls(raw: list) -> list[dict]:
    cleaned = []
    for call in raw[:4]:
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