"""Smart retriever — decides keyword vs semantic search mode.

Replaces the 15-line stub in the original retriever.py.
Backward-compatible: PHP reads the 'mode' field to decide how to search.
"""
from __future__ import annotations

import os
import re

from .embeddings import embed_text
from .vector_store import SimpleVectorStore, SearchResult


# Keywords that strongly suggest semantic / conceptual queries
# Both accented Vietnamese and unaccented equivalents are listed so users
# on mobile (without IME) get semantic mode even without diacritics.
_SEMANTIC_TRIGGERS = [
    # Accented Vietnamese
    "liên quan đến", "nói về", "đề cập", "giải thích", "cách ",
    "như thế nào", "tại sao", "ý nghĩa", "khái niệm",
    # Unaccented Vietnamese
    "lien quan den", "noi ve", "de cap", "giai thich", "cach ",
    "nhu the nao", "tai sao", "y nghia", "khai niem",
    # English
    "related to", "about", "explain", "concept", "how to",
    "why", "what is", "meaning of", "difference between",
]


class Retriever:
    """Retriever with automatic keyword/semantic mode selection."""

    def __init__(self, vector_store: SimpleVectorStore | None = None) -> None:
        self._store = vector_store or SimpleVectorStore()

    # ── Public API ───────────────────────────────────────────────────────────

    def build_query_hints(self, user_message: str) -> dict:
        """Build retrieval hints to pass back to PHP.
        
        Returns:
            dict with keys:
                mode: "keyword" | "semantic"
                query: cleaned query string
                top_k: int (semantic only)
                threshold: float (semantic only)
                semantic_results: list[dict] (semantic only, pre-computed)
        """
        msg = (user_message or "").strip()
        if not msg:
            return {"mode": "keyword", "query": ""}

        if self._should_use_semantic(msg):
            return self._semantic_hints(msg)
        return {"mode": "keyword", "query": msg[:200]}

    def semantic_search(
        self, query: str, top_k: int = 5, threshold: float = 0.60
    ) -> list[dict]:
        """Perform semantic search and return list of result dicts."""
        vector = embed_text(query)
        if not vector:
            return []
        results = self._store.search(vector, top_k=top_k, threshold=threshold)
        return [_result_to_dict(r) for r in results]

    def find_related(self, note_id: int | str, top_k: int = 5) -> list[dict]:
        """Find notes semantically related to a given note."""
        results = self._store.find_related(str(note_id), top_k=top_k)
        return [_result_to_dict(r) for r in results]

    # ── Internal ─────────────────────────────────────────────────────────────

    def _should_use_semantic(self, text: str) -> bool:
        lower = text.lower()
        # Trigger on conceptual keywords
        if any(trigger in lower for trigger in _SEMANTIC_TRIGGERS):
            return True
        # Long queries (>5 words) usually benefit from semantic search
        word_count = len(re.findall(r"\w+", text))
        return word_count > 5

    def _semantic_hints(self, query: str) -> dict:
        top_k = 5
        threshold = 0.60

        # Pre-compute semantic results (may be empty if store is unpopulated)
        semantic_results = self.semantic_search(query, top_k=top_k, threshold=threshold)

        return {
            "mode": "semantic",
            "query": query[:500],
            "top_k": top_k,
            "threshold": threshold,
            "semantic_results": semantic_results,
        }


def _result_to_dict(r: SearchResult) -> dict:
    return {
        "note_id": r.note_id,
        "score": r.score,
        "title": r.title,
        "preview": r.preview,
    }
