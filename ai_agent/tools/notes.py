"""Note CRUD tool schemas."""
from __future__ import annotations


def note_tool_definitions() -> list[dict]:
    return [
        {
            "type": "function",
            "function": {
                "name": "get_note",
                "description": "Get one note by id if it belongs to the current user.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "note_id": {"type": "integer", "minimum": 1},
                    },
                    "required": ["note_id"],
                    "additionalProperties": False,
                },
            },
        },
        {
            "type": "function",
            "function": {
                "name": "get_recent_notes",
                "description": "List the user's most recently updated notes (titles and short previews only).",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "limit": {"type": "integer", "minimum": 1, "maximum": 20},
                    },
                    "additionalProperties": False,
                },
            },
        },
        {
            "type": "function",
            "function": {
                "name": "create_note",
                "description": (
                    "Create a note IMMEDIATELY — no confirmation step. Always write content as "
                    "structured markdown: one bold main idea line, then '- ' sub-points underneath. "
                    "Always choose background_color/text_color from the palette in the system prompt "
                    "based on the note's topic. Use note_type='task' and check-style sub-points "
                    "('- [ ] ...') when the user wants a checklist/todo."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "title": {"type": "string"},
                        "content": {
                            "type": "string",
                            "description": "Markdown: **Main idea**, then '- ' sub-points (or '- [ ] ' for checklists).",
                        },
                        "labels": {
                            "type": "array",
                            "items": {"type": "string"},
                        },
                        "note_type": {
                            "type": "string",
                            "enum": ["note", "task"],
                            "description": "'task' for checklists/todos, otherwise 'note'.",
                        },
                        "background_color": {
                            "type": "string",
                            "description": "Hex color, e.g. #E8F0FE. Pick from the palette in the system prompt.",
                        },
                        "text_color": {
                            "type": "string",
                            "description": "Hex color for readable text on background_color.",
                        },
                        "category": {
                            "type": "string",
                            "enum": ["Study", "Work", "Idea", "Personal", "Finance", "Task", "Urgent"],
                            "description": "Auto-detected category for the note.",
                        },
                    },
                    "required": ["title", "content"],
                    "additionalProperties": False,
                },
            },
        },
        {
            "type": "function",
            "function": {
                "name": "update_note",
                "description": "Propose updating a note. The backend will NOT save until the user confirms.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "note_id": {"type": "integer", "minimum": 1},
                        "title": {"type": "string"},
                        "content": {"type": "string"},
                        "labels": {
                            "type": "array",
                            "items": {"type": "string"},
                        },
                        "note_type": {"type": "string", "enum": ["note", "task"]},
                        "background_color": {"type": "string"},
                        "text_color": {"type": "string"},
                        "category": {
                            "type": "string",
                            "enum": ["Study", "Work", "Idea", "Personal", "Finance", "Task", "Urgent"],
                        },
                    },
                    "required": ["note_id"],
                    "additionalProperties": False,
                },
            },
        },
        {
            "type": "function",
            "function": {
                "name": "delete_note",
                "description": "Propose deleting a note. Always requires explicit user confirmation. Never delete multiple notes in one call.",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "note_id": {"type": "integer", "minimum": 1},
                    },
                    "required": ["note_id"],
                    "additionalProperties": False,
                },
            },
        },
    ]
