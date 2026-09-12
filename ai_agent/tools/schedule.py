"""Timetable tool schemas for Vietnamese schedule requests."""
from __future__ import annotations


def schedule_tool_definitions() -> list[dict]:
    return [{
        "type": "function",
        "function": {
            "name": "create_schedule",
            "description": (
                "Create a Vietnamese timetable/calendar item immediately when the user clearly asks "
                "to add a class, meeting, or appointment. Use Asia/Ho_Chi_Minh. day_of_week uses 1=Monday through 7=Sunday."
            ),
            "parameters": {
                "type": "object",
                "properties": {
                    "title": {"type": "string"},
                    "day_of_week": {"type": "integer", "minimum": 1, "maximum": 7},
                    "specific_date": {"type": "string", "description": "Optional YYYY-MM-DD for a one-time event."},
                    "start_time": {"type": "string", "description": "24-hour HH:MM."},
                    "end_time": {"type": "string", "description": "24-hour HH:MM."},
                    "location": {"type": "string"},
                    "teacher": {"type": "string"},
                    "reminder_minutes": {"type": "integer", "enum": [-1, 0, 5, 10, 15, 30, 60]},
                    "note": {"type": "string"},
                },
                "required": ["title", "day_of_week", "start_time", "end_time"],
                "additionalProperties": False,
            },
        },
    }]
