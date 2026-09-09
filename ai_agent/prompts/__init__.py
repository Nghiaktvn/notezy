"""Prompt templates for Notezy AI Agent."""
from .system import SYSTEM_PROMPT, build_context_message
from .summary import SUMMARY_PROMPTS, get_summary_prompt
from .quiz import QUIZ_PROMPT
from .flashcard import FLASHCARD_PROMPT

__all__ = [
    "SYSTEM_PROMPT",
    "build_context_message",
    "SUMMARY_PROMPTS",
    "get_summary_prompt",
    "QUIZ_PROMPT",
    "FLASHCARD_PROMPT",
]
