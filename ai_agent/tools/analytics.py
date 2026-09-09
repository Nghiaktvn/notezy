"""Analytics and AI-generation tool schemas: summarize, statistics, quiz, flashcard."""
from __future__ import annotations


def analytics_tool_definitions() -> list[dict]:
    return [
        {
            "type": "function",
            "function": {
                "name": "summarize_note",
                "description": (
                    "Summarize a note's content in one of three styles:\n"
                    "- 'short': TL;DR in 2 sentences.\n"
                    "- 'detailed': Structured summary with main points, key insights, warnings, action items.\n"
                    "- 'study': Learning-focused with concepts, key takeaways, and review questions.\n"
                    "PHP backend returns the note content; AI generates the summary."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "note_id": {"type": "integer", "minimum": 1},
                        "style": {
                            "type": "string",
                            "enum": ["short", "detailed", "study"],
                            "description": "Summary style. Default: 'short'",
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
                "name": "get_note_statistics",
                "description": (
                    "Get statistics for the user's entire note collection: total count, "
                    "top topics (by label frequency), pending tasks, upcoming deadlines, "
                    "and activity trends. Use when user asks 'thống kê note', 'tôi có bao nhiêu note', "
                    "'chủ đề tôi ghi chú nhiều nhất'."
                ),
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
                "name": "generate_flashcards",
                "description": (
                    "Generate Q&A flashcard pairs from a note's content. "
                    "Great for study notes, technical documentation, or any educational content. "
                    "PHP backend returns the note content; AI generates the flashcards. "
                    "Use when user asks 'tạo flashcard', 'tạo thẻ ghi nhớ'."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "note_id": {"type": "integer", "minimum": 1},
                        "count": {
                            "type": "integer",
                            "minimum": 1,
                            "maximum": 15,
                            "description": "Number of flashcards to generate (default 5)",
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
                "name": "generate_quiz",
                "description": (
                    "Generate multiple-choice quiz questions from a note's content. "
                    "Each question has 4 options (A-D) with correct answer and explanation. "
                    "PHP backend returns the note content; AI generates the quiz. "
                    "Use when user asks 'tạo quiz', 'tạo câu hỏi trắc nghiệm', 'ôn tập'."
                ),
                "parameters": {
                    "type": "object",
                    "properties": {
                        "note_id": {"type": "integer", "minimum": 1},
                        "count": {
                            "type": "integer",
                            "minimum": 1,
                            "maximum": 15,
                            "description": "Number of quiz questions to generate (default 5)",
                        },
                    },
                    "required": ["note_id"],
                    "additionalProperties": False,
                },
            },
        },
    ]
