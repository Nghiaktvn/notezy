"""Shared vector store singleton.

WHY THIS EXISTS
---------------
server.py creates _indexer = NoteIndexer(vector_store=...)
agent.py  creates _retriever = Retriever(vector_store=...)

If each uses its own SimpleVectorStore(), they start from the same JSON file
but diverge the moment a new note is indexed — the agent's retriever never
sees the update until the server restarts.

This module gives both a single shared object so indexing is immediately
visible to semantic search.

Usage
-----
    from ai_agent.rag.shared import get_shared_vector_store
    vs = get_shared_vector_store()
"""
from __future__ import annotations

from .vector_store import SimpleVectorStore

_SHARED: SimpleVectorStore | None = None


def get_shared_vector_store() -> SimpleVectorStore:
    """Return the process-wide shared vector store (lazy init, thread-safe enough).

    The SimpleVectorStore already uses a threading.Lock internally for all
    mutations, so two concurrent first-calls are safe — at worst we briefly
    have two objects; the second one immediately replaces the first and both
    loaded from the same JSON file.
    """
    global _SHARED
    if _SHARED is None:
        _SHARED = SimpleVectorStore()
    return _SHARED
