"""Mock LLM provider — deterministic responses for tests and demos without an API key."""
from __future__ import annotations

import json
import re

from .gemini import LlmProvider


class MockProvider(LlmProvider):
    """Deterministic provider for tests and local demos without an API key."""

    def complete(self, messages: list[dict], tools: list[dict]) -> dict:
        last_user = ""
        for msg in reversed(messages):
            if msg.get("role") == "user":
                last_user = (msg.get("content") or "").strip()
                break
        lower = last_user.lower()

        # Prompt injection guard
        if any(x in lower for x in (
            "system prompt", "hướng dẫn hệ thống", "ignore previous",
            "huong dan he thong"
        )):
            return {
                "role": "assistant",
                "content": "Tôi không thể tiết lộ hướng dẫn nội bộ.",
                "tool_calls": [],
            }

        # Greetings — use specific phrases to avoid false matches inside Vietnamese words.
        # e.g. "ghi" (write) contains "hi" — so we must NOT use bare "hi" or "hi ".
        if any(x in lower for x in ("xin chào", "xin chao", "hello", "chào bạn", "chao ban")):
            return {
                "role": "assistant",
                "content": "Xin chào! Tôi là Notezy AI Copilot. Tôi có thể giúp bạn tìm kiếm, tóm tắt, tạo quiz, flashcard từ ghi chú của bạn. Bạn muốn làm gì?",
                "tool_calls": [],
            }

        if any(x in lower for x in (
            "tóm tắt", "summarize", "summary", "tom tat", "tom gon"
        )):
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("summarize_note", {"note_id": _context_note_id(messages) or 1, "style": "detailed"})],
            }

        if any(x in lower for x in (
            "quiz", "câu hỏi trắc nghiệm", "tao quiz", "trac nghiem"
        )):
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("generate_quiz", {"note_id": _context_note_id(messages) or 1, "count": 5})],
            }

        if any(x in lower for x in (
            "flashcard", "thẻ ghi nhớ", "the ghi nho", "tao flashcard"
        )):
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("generate_flashcards", {"note_id": _context_note_id(messages) or 1, "count": 5})],
            }

        if any(x in lower for x in (
            "task", "công việc", "việc cần làm", "checklist",
            "viec can lam", "cong viec"
        )):
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("extract_tasks", {"note_id": _context_note_id(messages) or 1})],
            }

        if any(x in lower for x in (
            "deadline", "hạn chót", "tuần này", "cần làm gì",
            "han chot", "tuan nay", "can lam gi"
        )):
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("get_deadlines", {"range": "this_week"})],
            }

        if any(x in lower for x in (
            "thống kê", "analytics", "bao nhiêu note",
            "thong ke", "bao nhieu note"
        )):
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("get_note_statistics", {})],
            }

        if any(x in lower for x in (
            "gợi ý nhãn", "suggest label", "auto tag", "phân loại",
            "goi y nhan", "phan loai", "gan nhan"
        )):
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("suggest_labels", {"note_id": _context_note_id(messages) or 1})],
            }

        if any(x in lower for x in (
            "tìm", "find", "search", "related", "liên quan",
            "tim kiem", "lien quan"
        )):
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("semantic_search", {"query": last_user, "top_k": 5})],
            }

        if any(x in lower for x in (
            "tạo", "create", "viết note", "tao"
        )) and "xóa" not in lower and "xoa" not in lower:
            topic = "Docker" if "docker" in lower else "Ghi chú mới"
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [
                    _fn("create_note", {
                        "title": f"{topic} Basics",
                        "content": f"**{topic}**\n\n- Khái niệm cơ bản\n- Ứng dụng thực tế\n- Ví dụ minh hoạ",
                        "labels": [topic],
                    })
                ],
            }

        if any(x in lower for x in ("xóa", "delete", "xoa")):
            nid = _context_note_id(messages) or 0
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("delete_note", {"note_id": nid})],
            }

        return {
            "role": "assistant",
            "content": (
                "Tôi là **Notezy AI Copilot**. Tôi có thể:\n"
                "- 🔍 **Tìm kiếm** note theo ngữ nghĩa\n"
                "- 📝 **Tóm tắt** note (ngắn / chi tiết / học tập)\n"
                "- ✅ **Trích xuất task** và deadline\n"
                "- 🎯 **Tạo quiz** trắc nghiệm\n"
                "- 🗂️ **Tạo flashcard** từ note\n"
                "- 🏷️ **Gợi ý nhãn** tự động\n"
                "- 📊 **Thống kê** kho ghi chú\n\n"
                "Bạn muốn làm gì?"
            ),
            "tool_calls": [],
        }

    def embed(self, text: str) -> list[float]:
        """Return a fake 768-dim vector for testing."""
        import hashlib
        h = int(hashlib.md5(text.encode()).hexdigest(), 16)
        import math
        return [math.sin(h * i * 0.001) for i in range(768)]


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


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
            m = re.search(r'"note_id"\s*:\s*(\d+)', content)
            if m:
                return int(m.group(1))
    return None
