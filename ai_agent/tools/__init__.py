"""Tool registry for Notezy AI Agent.

ALL_TOOLS — set of all valid tool names.
tool_definitions() — returns full OpenAI-style schema list sent to Gemini.
risk_for(name) — returns the risk level of a tool.
"""
from __future__ import annotations

from .notes import note_tool_definitions
from .search import search_tool_definitions
from .tasks import task_tool_definitions
from .labels import label_tool_definitions
from .analytics import analytics_tool_definitions
from .schedule import schedule_tool_definitions

# ── Risk classification ─────────────────────────────────────────────────────
READ_TOOLS = {
    "search_notes",
    "semantic_search",
    "get_note",
    "get_recent_notes",
    "get_labels",
    "find_related_notes",
    "get_deadlines",
    "get_note_statistics",
}
WRITE_TOOLS = {
    "create_note",
    "update_note",
    "create_label",
    "suggest_labels",
    "extract_tasks",
    "summarize_note",
    "generate_flashcards",   # plural — matches tool schema in analytics.py
    "generate_quiz",
    "create_schedule",
}
DESTRUCTIVE_TOOLS = {"delete_note"}

ALL_TOOLS = READ_TOOLS | WRITE_TOOLS | DESTRUCTIVE_TOOLS


def risk_for(name: str) -> str:
    if name in DESTRUCTIVE_TOOLS:
        return "destructive"
    if name in WRITE_TOOLS:
        return "write"
    if name in READ_TOOLS:
        return "read"
    return "unknown"


def tool_definitions() -> list[dict]:
    """Return complete list of tool schemas to send to Gemini."""
    return (
        search_tool_definitions()
        + note_tool_definitions()
        + task_tool_definitions()
        + label_tool_definitions()
        + analytics_tool_definitions()
        + schedule_tool_definitions()
    )
