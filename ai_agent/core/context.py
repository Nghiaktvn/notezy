"""Context builder — enriches payload context for the AI agent."""
from __future__ import annotations

from datetime import datetime
from zoneinfo import ZoneInfo

from .memory import ConversationMemory, UserMemory


class ContextBuilder:
    """Builds enriched context dict for each AI request."""

    def __init__(self) -> None:
        self._user_memory = UserMemory()

    def build(
        self,
        raw_context: dict,
        messages: list[dict],
        conv_memory: ConversationMemory,
        user_id: int | str | None = None,
    ) -> dict:
        """Return enriched context dict.
        
        Merges:
        - Raw context from PHP (current_note, recent_notes, labels, page)
        - Conversation memory (last_note_id, last_search_results)
        - User memory (preferred_language, frequent_topics)
        """
        ctx = dict(raw_context)
        now_vn = datetime.now(ZoneInfo("Asia/Ho_Chi_Minh"))
        ctx["vietnam_now"] = now_vn.strftime("%Y-%m-%d %H:%M:%S")
        ctx["timezone"] = "Asia/Ho_Chi_Minh"

        # ── Conversation memory ──────────────────────────────────────────────
        if conv_memory.last_note_id and not ctx.get("last_referenced_note_id"):
            ctx["last_referenced_note_id"] = conv_memory.last_note_id
        if conv_memory.last_note_title:
            ctx["last_referenced_note_title"] = conv_memory.last_note_title
        if conv_memory.last_search_results:
            ctx["last_search_results"] = conv_memory.last_search_results[:5]
        if conv_memory.last_tool_called:
            ctx["last_tool_called"] = conv_memory.last_tool_called

        # ── User memory ──────────────────────────────────────────────────────
        if user_id:
            user_mem = self._user_memory.get(user_id)
            ctx["user_preferences"] = {
                "language": user_mem.get("preferred_language", "Vietnamese"),
                "note_style": user_mem.get("preferred_note_style", "structured"),
                "frequent_topics": user_mem.get("frequent_topics", [])[:5],
            }

        return ctx

    def user_memory_hint(self, user_id: int | str | None) -> str | None:
        """Return a short hint string for the system prompt."""
        if not user_id:
            return None
        return self._user_memory.build_context_hint(user_id)
