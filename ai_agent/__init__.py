"""Notezy AI Agent package."""
from __future__ import annotations

import sys
from pathlib import Path

_PKG_DIR = str(Path(__file__).resolve().parent)
if _PKG_DIR not in sys.path:
    sys.path.insert(0, _PKG_DIR)

from .ai_agent import AiAgent, handle_request, _sanitize_tool_calls

__all__ = ["AiAgent", "handle_request", "_sanitize_tool_calls"]
