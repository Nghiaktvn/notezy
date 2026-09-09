"""Conversation and user memory for Notezy AI Copilot.

ConversationMemory:
    Short-term memory for a single chat session.
    Tracks: last referenced note, last search results, topic thread.
    Resolves references like "nó", "note đó", "note vừa tạo", "cái trên".

UserMemory:
    Long-term user preferences stored in a JSON sidecar file.
    Tracks: preferred language, note style, frequent topics.
"""
from __future__ import annotations

import json
import re
from pathlib import Path
from typing import Any


# ---------------------------------------------------------------------------
# Conversation Memory (in-process, per-request session)
# ---------------------------------------------------------------------------

class ConversationMemory:
    """Short-term memory for one chat session.
    
    Maintained across the messages list in a single /v1/chat request.
    Does not persist between requests.
    """

    def __init__(self) -> None:
        self.last_note_id: int | None = None
        self.last_note_title: str = ""
        self.last_search_results: list[dict] = []
        self.last_tool_called: str = ""
        self.topic_thread: list[str] = []   # e.g. ["Docker", "container"]

    def update_from_messages(self, messages: list[dict]) -> None:
        """Scan conversation history to rebuild short-term state."""
        for msg in messages:
            role = msg.get("role")
            content = str(msg.get("content") or "")

            # Extract note_id from context messages
            if role == "system" and "current_note" in content:
                m = re.search(r'"note_id"\s*:\s*(\d+)', content)
                if m:
                    self.last_note_id = int(m.group(1))

            # Extract note_id from tool results
            if role == "tool":
                try:
                    data = json.loads(content)
                    if isinstance(data, dict):
                        nid = data.get("note_id") or data.get("id")
                        if nid:
                            self.last_note_id = int(nid)
                        title = data.get("title")
                        if title:
                            self.last_note_title = title
                        # Search results
                        if "notes" in data:
                            self.last_search_results = data["notes"][:10]
                except (json.JSONDecodeError, TypeError, ValueError):
                    pass

            # Track assistant tool calls
            if role == "assistant":
                for tc in msg.get("tool_calls") or []:
                    fn = (tc.get("function") or {}).get("name") or ""
                    if fn:
                        self.last_tool_called = fn

    def resolve_reference(self, text: str) -> int | None:
        """Try to resolve pronoun references to a note_id.
        
        Handles: "nó", "note đó", "note vừa tạo", "cái này", "cái trên",
                 "note này", "the note", "it", "that note"
        Also handles unaccented equivalents: "no", "note do", "cai do",
                 "note nay", "note vua tao"
        Returns note_id int or None.
        """
        lower = text.lower()
        ref_phrases = [
            # Accented Vietnamese
            "nó", "note đó", "note vừa tạo", "note đã tạo",
            "cái này", "cái đó", "cái trên", "note này",
            "note vừa rồi", "note vừa nói",
            # Unaccented Vietnamese
            "no", "note do", "note vua tao", "note da tao",
            "cai nay", "cai do", "cai tren", "note nay",
            "note vua roi", "note vua noi",
            # English
            "the note", "it", "that note", "this note",
        ]
        if any(phrase in lower for phrase in ref_phrases):
            return self.last_note_id
        return None


# ---------------------------------------------------------------------------
# User Memory (persistent, per-user JSON file)
# ---------------------------------------------------------------------------

_DEFAULT_MEMORY: dict[str, Any] = {
    "preferred_language": "Vietnamese",
    "preferred_note_style": "structured",    # "structured" | "prose" | "bullet"
    "frequent_topics": [],
    "session_count": 0,
    "last_active": None,
}


class UserMemory:
    """Persistent per-user memory stored in storage/user_memory/<user_id>.json.
    
    Gracefully degrades — if file can't be read/written, uses defaults.
    """

    def __init__(self, storage_root: str | None = None) -> None:
        if storage_root is None:
            here = Path(__file__).resolve().parent.parent.parent
            storage_root = str(here / "storage" / "user_memory")
        self._root = Path(storage_root)

    def get(self, user_id: int | str) -> dict[str, Any]:
        """Load user memory. Returns defaults if file doesn't exist."""
        path = self._user_path(user_id)
        if not path.exists():
            return dict(_DEFAULT_MEMORY)
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
            # Merge with defaults so new keys are always present
            return {**_DEFAULT_MEMORY, **data}
        except (json.JSONDecodeError, OSError):
            return dict(_DEFAULT_MEMORY)

    def update(self, user_id: int | str, updates: dict[str, Any]) -> None:
        """Merge updates into user memory and persist."""
        memory = self.get(user_id)
        memory.update(updates)
        self._save(user_id, memory)

    def add_topic(self, user_id: int | str, topic: str) -> None:
        """Track a topic the user is engaging with."""
        memory = self.get(user_id)
        topics: list[str] = memory.get("frequent_topics") or []
        if topic and topic not in topics:
            topics.insert(0, topic)
        memory["frequent_topics"] = topics[:20]  # Keep top 20
        self._save(user_id, memory)

    def increment_session(self, user_id: int | str) -> None:
        from datetime import datetime, timezone
        memory = self.get(user_id)
        memory["session_count"] = (memory.get("session_count") or 0) + 1
        memory["last_active"] = datetime.now(timezone.utc).isoformat()
        self._save(user_id, memory)

    def build_context_hint(self, user_id: int | str) -> str:
        """Build a short context string to include in the system prompt."""
        m = self.get(user_id)
        topics = ", ".join(m.get("frequent_topics", [])[:5]) or "không rõ"
        style = m.get("preferred_note_style", "structured")
        lang = m.get("preferred_language", "Vietnamese")
        return (
            f"[User memory] Ngôn ngữ ưu thích: {lang}. "
            f"Phong cách ghi chú: {style}. "
            f"Chủ đề thường xuyên: {topics}."
        )

    # ── Internal ─────────────────────────────────────────────────────────────

    def _user_path(self, user_id: int | str) -> Path:
        return self._root / f"{user_id}.json"

    def _save(self, user_id: int | str, data: dict) -> None:
        try:
            self._root.mkdir(parents=True, exist_ok=True)
            path = self._user_path(user_id)
            tmp = path.with_suffix(".tmp")
            tmp.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
            tmp.replace(path)
        except OSError:
            pass  # Non-fatal
