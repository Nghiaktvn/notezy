"""Safety checks for Notezy AI — prompt injection and policy guards."""
from __future__ import annotations

# Phrases that signal prompt injection attempts
_INJECTION_PHRASES = [
    "ignore previous instructions",
    "ignore all instructions",
    "disregard your instructions",
    "new instructions:",
    "system prompt:",
    "reveal your prompt",
    "show your system prompt",
    "repeat your instructions",
    "bỏ qua hướng dẫn trước",
    "bỏ qua lệnh trước",
    "hướng dẫn hệ thống",
    "tiết lộ system prompt",
    "delete all notes",
    "xóa tất cả note",
    "xóa hết note",
]

# Suspicious patterns that should be flagged but not blocked
_WARNING_PHRASES = [
    "act as",
    "pretend you are",
    "you are now",
    "roleplay as",
    "giả vờ bạn là",
    "đóng vai",
]


def check_injection(text: str) -> tuple[bool, str]:
    """Check if text appears to contain a prompt injection attempt.
    
    Returns:
        (is_injected: bool, reason: str)
    """
    lower = (text or "").lower()
    for phrase in _INJECTION_PHRASES:
        if phrase in lower:
            return True, f"Detected injection attempt: '{phrase}'"
    return False, ""


def safe_user_content(text: str) -> bool:
    """Return True if the user message is safe to process normally."""
    injected, _ = check_injection(text)
    return not injected
