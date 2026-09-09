"""Search tool schemas: keyword search + semantic search + find related."""
from __future__ import annotations


def search_tool_definitions() -> list[dict]:
    return [
        {
            "type": "function",
            "function": {
                "name": "search_notes",
                "description": (
                    "Search the authenticated user's notes by keyword in title, content, or labels. "
                    "Use this for exact keyword queries. "
                    "For meaning-based queries, prefer semantic_search. "
                    "Never invent notes."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "query": {"type": "string", "description": "Search keywords"},
                        "limit": {"type": "integer", "minimum": 1, "maximum": 20},
                    },
                    "required": ["query"],
                    "additionalProperties": False,
                },
            },
        },
        {
            "type": "function",
            "function": {
                "name": "semantic_search",
                "description": (
                    "Search notes by semantic meaning — finds notes even when exact keywords don't match. "
                    "Use this when the user asks conceptual questions like 'note nào nói về cách chạy nhiều service?' "
                    "or 'ghi chú liên quan đến database'. "
                    "Returns top-K most relevant notes by cosine similarity."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "query": {"type": "string", "description": "Natural language search query"},
                        "top_k": {
                            "type": "integer",
                            "minimum": 1,
                            "maximum": 10,
                            "description": "Number of results to return (default 5)",
                        },
                        "threshold": {
                            "type": "number",
                            "description": "Minimum similarity score 0.0-1.0 (default 0.60)",
                        },
                    },
                    "required": ["query"],
                    "additionalProperties": False,
                },
            },
        },
        {
            "type": "function",
            "function": {
                "name": "find_related_notes",
                "description": (
                    "Find notes semantically related to a specific note. "
                    "Use when user asks 'tìm note liên quan', 'note tương tự', 'gợi ý note liên quan đến note này'."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "note_id": {
                            "type": "integer",
                            "minimum": 1,
                            "description": "Source note ID to find related notes for",
                        },
                        "limit": {"type": "integer", "minimum": 1, "maximum": 10},
                    },
                    "required": ["note_id"],
                    "additionalProperties": False,
                },
            },
        },
    ]
