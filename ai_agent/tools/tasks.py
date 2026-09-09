"""Task and deadline tool schemas."""
from __future__ import annotations


def task_tool_definitions() -> list[dict]:
    return [
        {
            "type": "function",
            "function": {
                "name": "extract_tasks",
                "description": (
                    "Analyze a note's content and extract a list of actionable tasks and deadlines. "
                    "Use when user says 'tìm task trong note', 'tạo checklist từ note', "
                    "'note này có việc gì cần làm?'. "
                    "PHP backend returns the note content; AI then identifies tasks and deadlines."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "note_id": {
                            "type": "integer",
                            "minimum": 1,
                            "description": "Note ID to extract tasks from",
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
                "name": "get_deadlines",
                "description": (
                    "Get all tasks and deadlines for the current user in a given time range. "
                    "Use when user asks 'tuần này tôi phải làm gì?', 'deadline sắp tới', "
                    "'việc cần làm hôm nay'."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "range": {
                            "type": "string",
                            "enum": ["today", "this_week", "this_month", "all"],
                            "description": "Time range for deadline lookup",
                        },
                    },
                    "required": ["range"],
                    "additionalProperties": False,
                },
            },
        },
    ]
