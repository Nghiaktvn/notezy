"""In-memory vector store with JSON persistence — no external dependencies.

Architecture:
- Vectors stored in memory as dict: note_id -> {vector, metadata}
- Persisted to JSON file at storage/ai_vectors.json
- Search via cosine similarity
- Thread-safe with a simple lock

This is sufficient for up to ~5000 notes. For larger collections,
swap in ChromaDB or Qdrant without changing the interface.
"""
from __future__ import annotations

import json
import math
import os
import threading
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any


@dataclass
class VectorEntry:
    note_id: str
    vector: list[float]
    text_preview: str       # First 500 chars for display
    metadata: dict[str, Any] = field(default_factory=dict)


@dataclass
class SearchResult:
    note_id: str
    score: float
    title: str = ""
    preview: str = ""
    metadata: dict[str, Any] = field(default_factory=dict)


def cosine_similarity(a: list[float], b: list[float]) -> float:
    """Compute cosine similarity between two equal-length vectors."""
    if not a or not b or len(a) != len(b):
        return 0.0
    dot = sum(x * y for x, y in zip(a, b))
    mag_a = math.sqrt(sum(x * x for x in a))
    mag_b = math.sqrt(sum(x * x for x in b))
    if mag_a == 0.0 or mag_b == 0.0:
        return 0.0
    return dot / (mag_a * mag_b)


class SimpleVectorStore:
    """Thread-safe in-memory vector store with JSON persistence."""

    def __init__(self, storage_path: str | None = None) -> None:
        if storage_path is None:
            # Default: <project_root>/storage/ai_vectors.json
            here = Path(__file__).resolve().parent.parent.parent
            storage_path = str(here / "storage" / "ai_vectors.json")

        self.storage_path = Path(storage_path)
        self._lock = threading.Lock()
        self._store: dict[str, VectorEntry] = {}
        self._load()

    # ── Public API ──────────────────────────────────────────────────────────

    def upsert(
        self,
        note_id: str | int,
        vector: list[float],
        text: str,
        metadata: dict | None = None,
    ) -> None:
        """Insert or update a note embedding."""
        if not vector:
            return
        key = str(note_id)
        entry = VectorEntry(
            note_id=key,
            vector=vector,
            text_preview=text[:500],
            metadata=metadata or {},
        )
        with self._lock:
            self._store[key] = entry
            self._save()

    def delete(self, note_id: str | int) -> None:
        """Remove a note's embedding."""
        key = str(note_id)
        with self._lock:
            if key in self._store:
                del self._store[key]
                self._save()

    def search(
        self,
        query_vector: list[float],
        top_k: int = 5,
        threshold: float = 0.60,
        exclude_ids: list[str] | None = None,
    ) -> list[SearchResult]:
        """Return top-K notes most similar to query_vector."""
        if not query_vector:
            return []

        exclude = set(exclude_ids or [])
        results: list[SearchResult] = []

        with self._lock:
            for key, entry in self._store.items():
                if key in exclude:
                    continue
                score = cosine_similarity(query_vector, entry.vector)
                if score >= threshold:
                    results.append(
                        SearchResult(
                            note_id=key,
                            score=round(score, 4),
                            title=entry.metadata.get("title", ""),
                            preview=entry.text_preview[:200],
                            metadata=entry.metadata,
                        )
                    )

        results.sort(key=lambda r: r.score, reverse=True)
        return results[:top_k]

    def find_related(
        self, note_id: str | int, top_k: int = 5, threshold: float = 0.55
    ) -> list[SearchResult]:
        """Find notes related to a given note (excluding itself)."""
        key = str(note_id)
        with self._lock:
            entry = self._store.get(key)
        if not entry:
            return []
        return self.search(
            entry.vector,
            top_k=top_k,
            threshold=threshold,
            exclude_ids=[key],
        )

    def count(self) -> int:
        with self._lock:
            return len(self._store)

    # ── Persistence ──────────────────────────────────────────────────────────

    def _save(self) -> None:
        """Persist store to JSON. Call only while holding self._lock."""
        try:
            self.storage_path.parent.mkdir(parents=True, exist_ok=True)
            data = {
                key: {
                    "vector": entry.vector,
                    "text_preview": entry.text_preview,
                    "metadata": entry.metadata,
                }
                for key, entry in self._store.items()
            }
            tmp = self.storage_path.with_suffix(".tmp")
            tmp.write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")
            tmp.replace(self.storage_path)
        except OSError:
            pass  # Non-fatal: vectors survive in memory for this session

    def _load(self) -> None:
        """Load store from JSON on startup."""
        if not self.storage_path.exists():
            return
        try:
            data = json.loads(self.storage_path.read_text(encoding="utf-8"))
            for key, item in data.items():
                self._store[key] = VectorEntry(
                    note_id=key,
                    vector=item.get("vector", []),
                    text_preview=item.get("text_preview", ""),
                    metadata=item.get("metadata", {}),
                )
        except (json.JSONDecodeError, KeyError, OSError):
            pass  # Start fresh if file is corrupt
