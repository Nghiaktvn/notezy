"""Label tool schemas: list, create, and AI-powered suggest."""
from __future__ import annotations


def label_tool_definitions() -> list[dict]:
    return [
        {
            "type": "function",
            "function": {
                "name": "get_labels",
                "description": "List labels that belong to the current user.",
                "parameters": {
                    "type": "object",
                    "properties": {},
                    "additionalProperties": False,
                },
            },
        },
        {
            "type": "function",
            "function": {
                "name": "create_label",
                "description": "Propose creating a label. The backend will NOT save until the user confirms.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "name": {"type": "string"},
                        "note_id": {"type": "integer", "minimum": 1},
                    },
                    "required": ["name"],
                    "additionalProperties": False,
                },
            },
        },
        {
            "type": "function",
            "function": {
                "name": "suggest_labels",
                "description": (
                    "AI-powered label suggestion and auto-categorization for a note. "
                    "PHP backend returns note content; AI suggests labels, category, priority, and note type. "
                    "Use when user asks 'gợi ý nhãn', 'auto tag', 'phân loại note này', "
                    "or automatically after creating a note without labels."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "note_id": {
                            "type": "integer",
                            "minimum": 1,
                            "description": "Note ID to analyze and suggest labels for",
                        },
                    },
                    "required": ["note_id"],
                    "additionalProperties": False,
                },
            },
        },
    ]
