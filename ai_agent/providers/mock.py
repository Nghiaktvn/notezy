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

        # Complete the tool round-trip in local/demo mode. Previously the mock
        # provider repeated the same tool call forever after PHP returned data.
        tool_name, tool_result = _last_tool_result(messages)
        if tool_name:
            if not tool_result.get("ok"):
                return {
                    "role": "assistant",
                    "content": "Không tìm thấy ghi chú phù hợp hoặc bạn không có quyền truy cập.",
                    "tool_calls": [],
                }
            if tool_name in ("search_notes", "semantic_search", "find_related_notes"):
                notes = tool_result.get("notes") or []
                if not notes:
                    return {"role": "assistant", "content": "Không tìm thấy ghi chú phù hợp.", "tool_calls": []}
                if any(x in lower for x in ("tóm tắt", "summarize", "summary", "tom tat", "tom gon")):
                    return {
                        "role": "assistant",
                        "content": "",
                        "tool_calls": [_fn("summarize_note", {"note_id": int(notes[0]["note_id"]), "style": "detailed"})],
                    }
                if any(x in lower for x in ("task", "công việc", "việc cần làm", "checklist", "viec can lam", "cong viec")):
                    return {
                        "role": "assistant",
                        "content": "",
                        "tool_calls": [_fn("extract_tasks", {"note_id": int(notes[0]["note_id"])})],
                    }
                snippets = []
                for note in notes[:3]:
                    content = _plain_text(str(note.get("content") or ""))
                    snippets.append(f"• {note.get('title', 'Ghi chú')}: {_shorten(content, 180)}")
                return {
                    "role": "assistant",
                    "content": "Dựa trên các ghi chú liên quan:\n" + "\n".join(snippets),
                    "tool_calls": [],
                }
            if tool_name == "summarize_note":
                content = _plain_text(str(tool_result.get("content") or ""))
                sentences = [s.strip() for s in re.split(r"(?<=[.!?])\s+|\n+", content) if s.strip()]
                summary = " ".join(sentences[:3]) if sentences else "Ghi chú chưa có nội dung để tóm tắt."
                return {
                    "role": "assistant",
                    "content": f"Tóm tắt «{tool_result.get('title', 'Ghi chú')}»: {_shorten(summary, 500)}",
                    "tool_calls": [],
                }
            if tool_name == "extract_tasks":
                content = _plain_text(str(tool_result.get("content") or ""))
                items = [s.strip(" -•\t") for s in re.split(r"[\n.;]+", content) if s.strip()]
                return {"role": "assistant", "content": "Việc cần làm:\n" + "\n".join(f"• {x}" for x in items[:6]), "tool_calls": []}
            if tool_name in ("generate_quiz", "generate_flashcards", "suggest_labels"):
                title = tool_result.get("title", "ghi chú")
                return {"role": "assistant", "content": f"Đã phân tích «{title}». Nội dung đã sẵn sàng để tạo tài liệu học tập.", "tool_calls": []}

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
            current_note = _context_note_id(messages)
            if not current_note:
                return {
                    "role": "assistant",
                    "content": "",
                    "tool_calls": [_fn("search_notes", {"query": _note_query(last_user), "limit": 5})],
                }
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("summarize_note", {"note_id": current_note, "style": "detailed"})],
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
            current_note = _context_note_id(messages)
            if not current_note:
                return {
                    "role": "assistant",
                    "content": "",
                    "tool_calls": [_fn("search_notes", {"query": _named_note_query(last_user), "limit": 5})],
                }
            return {
                "role": "assistant",
                "content": "",
                "tool_calls": [_fn("extract_tasks", {"note_id": current_note})],
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
        # Match only the note nested under current_note. The old broad search
        # accidentally captured the first recent_notes item on list pages.
        m = re.search(r'"current_note"\s*:\s*\{[^{}]*?"note_id"\s*:\s*(\d+)', content, flags=re.S)
        if m:
            return int(m.group(1))
    return None


def _last_tool_result(messages: list[dict]) -> tuple[str, dict]:
    for msg in reversed(messages):
        if msg.get("role") != "tool":
            continue
        try:
            result = json.loads(msg.get("content") or "{}")
        except (TypeError, json.JSONDecodeError):
            result = {}
        return str(msg.get("name") or ""), result if isinstance(result, dict) else {}
    return "", {}


def _note_query(text: str) -> str:
    query = re.sub(r"^(hãy\s+)?(tóm tắt|tom tat|summarize|summary)\s+(ghi chú|note)?\s*", "", text, flags=re.I).strip()
    query = re.split(r"\s+(?:và|để|rồi)\s+", query, maxsplit=1, flags=re.I)[0]
    return query.strip(" .,:;\"'") or text[:120]


def _named_note_query(text: str) -> str:
    match = re.search(r"(?:ghi chú|note)\s+(.+)", text, flags=re.I)
    query = match.group(1) if match else text
    query = re.split(r"[,.;?]|\s+(?:có|gồm|và|thì)\s+", query, maxsplit=1, flags=re.I)[0]
    return query.strip(" .,:;?!\"'") or text[:120]


def _plain_text(value: str) -> str:
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", value)).strip()


def _shorten(value: str, limit: int) -> str:
    return value if len(value) <= limit else value[: limit - 1].rstrip() + "…"
