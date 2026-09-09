"""Intent classifier — identifies user intent before calling Gemini.

Uses keyword matching (no extra API call). Fast and deterministic.
Falls back to "unknown" so Gemini handles the full response.
"""
from __future__ import annotations

import re
from dataclasses import dataclass


@dataclass
class Intent:
    intent: str
    confidence: float           # 0.0 – 1.0
    target: str | None = None   # "current_note", "all_notes", or a note_id str


# Each entry: (intent_name, keyword_list, confidence_boost)
# Checked in order — first match wins if confidence > threshold.
# Both accented Vietnamese AND common unaccented equivalents are included
# since mobile users often type without diacritics.
_KEYWORD_RULES: list[tuple[str, list[str], float]] = [
    # ── High-confidence AI generation intents ──────────────────────────────
    ("generate_quiz",      ["quiz", "trắc nghiệm", "thi trắc nghiệm",
                            "tac nghiem", "tao quiz", "câu hỏi trắc nghiệm"], 0.95),
    ("generate_flashcard", ["flashcard", "flash card", "thẻ ghi nhớ", "thẻ học",
                            "the ghi nho", "tao flashcard"], 0.95),
    ("extract_tasks",      ["extract task", "tìm task", "công việc trong note",
                            "checklist từ note", "task trong note",
                            "tim task", "checklist tu note", "viec can lam"], 0.95),
    ("summarize",          ["tóm tắt", "summarize", "summary", "tl;dr",
                            "rút gọn", "tóm gọn",
                            "tom tat", "tom gon", "rut gon"], 0.90),
    ("suggest_labels",     ["gợi ý nhãn", "suggest label", "auto tag", "auto-tag",
                            "phân loại note", "gắn nhãn tự động",
                            "goi y nhan", "phan loai note", "phan loai",
                            "gan nhan"], 0.90),
    # ── Analytics / statistics ──────────────────────────────────────────────
    ("analytics",          ["thống kê", "statistics", "analytics",
                            "bao nhiêu note", "tổng số note", "chủ đề nào",
                            "thong ke", "bao nhieu note", "tong so note"], 0.90),
    ("get_deadline",       ["deadline", "hạn chót", "tuần này phải làm",
                            "cần làm gì", "lịch", "việc sắp tới", "sắp hết hạn",
                            "han chot", "tuan nay", "can lam gi",
                            "viec sap toi"], 0.90),
    # ── Search ──────────────────────────────────────────────────────────────
    ("semantic_search",    ["liên quan đến", "nói về", "tìm note về",
                            "ghi chú về", "note về",
                            "lien quan den", "noi ve", "tim note ve",
                            "ghi chu ve"], 0.85),
    ("find_related",       ["note liên quan", "gợi ý note", "note tương tự",
                            "note cùng chủ đề",
                            "note lien quan", "note tuong tu",
                            "note cung chu de"], 0.85),
    ("search",             ["tìm", "find", "search", "tra cứu", "tìm kiếm",
                            "tim", "tim kiem", "tra cuu"], 0.80),
    # ── CRUD ────────────────────────────────────────────────────────────────
    ("delete",             ["xóa", "delete", "xoá bỏ", "remove",
                            "xoa", "xoa bo"], 0.85),
    ("update",             ["sửa", "update", "chỉnh sửa", "cập nhật",
                            "edit", "thay đổi",
                            "sua", "chinh sua", "cap nhat", "thay doi"], 0.80),
    ("create",             ["tạo", "create", "viết note", "ghi lại",
                            "thêm note", "new note",
                            "tao", "viet note", "ghi lai", "them note"], 0.80),
    # ── General ask ─────────────────────────────────────────────────────────
    ("recommend",          ["gợi ý", "recommend", "đề xuất", "nên làm gì",
                            "goi y", "de xuat", "nen lam gi"], 0.75),
    ("ask",                ["là gì", "what is", "how does", "explain",
                            "giải thích", "la gi", "giai thich"], 0.65),
]

_CONFIDENCE_THRESHOLD = 0.60


class IntentClassifier:
    """Fast keyword-based intent classifier — no API call required."""

    def classify(self, message: str, context: dict | None = None) -> Intent:
        """Classify user intent from a message string.
        
        Args:
            message: The user's latest message.
            context: Optional context (current_note, etc.) for target resolution.
        
        Returns:
            Intent with intent name, confidence, and optional target.
        """
        lower = message.lower().strip()

        for intent_name, keywords, confidence in _KEYWORD_RULES:
            if any(kw in lower for kw in keywords):
                target = self._resolve_target(intent_name, message, context or {})
                return Intent(intent=intent_name, confidence=confidence, target=target)

        return Intent(intent="unknown", confidence=0.5)

    def _resolve_target(self, intent: str, message: str, context: dict) -> str | None:
        """Determine what the intent targets: current note, all notes, or specific note."""
        lower = message.lower()

        # References to current/this note
        if any(r in lower for r in ("note này", "ghi chú này", "note hiện tại", "this note", "current note")):
            note_id = (context.get("current_note") or {}).get("note_id")
            return f"note:{note_id}" if note_id else "current_note"

        # References to all notes
        if any(r in lower for r in ("tất cả", "toàn bộ", "all notes", "mọi note")):
            return "all_notes"

        # Explicit note_id in message
        m = re.search(r"\bnote[_\s]?(\d+)\b", lower)
        if m:
            return f"note:{m.group(1)}"

        # Analytics/deadline intents are always all-notes
        if intent in ("analytics", "get_deadline"):
            return "all_notes"

        # Default: use current note if available, else all
        note_id = (context.get("current_note") or {}).get("note_id")
        if note_id:
            return f"note:{note_id}"
        return None
