"""Note indexer — called by /v1/index endpoint when notes are created/updated.

Flow:
  PHP creates/updates note
       │
       ▼
  PHP calls POST /v1/index with {note_id, title, content, labels, user_id}
       │
       ▼
  NoteIndexer.index(payload)
       │
       ├── Build text = title + labels + content (truncated)
       ├── embed_text(text) → 768-dim vector
       └── SimpleVectorStore.upsert(note_id, vector, metadata)
"""
from __future__ import annotations

from .embeddings import embed_text
from .vector_store import SimpleVectorStore


class NoteIndexer:
    """Indexes note content into the vector store for semantic search."""

    def __init__(self, vector_store: SimpleVectorStore | None = None) -> None:
        self._store = vector_store or SimpleVectorStore()

    def index(self, payload: dict) -> dict:
        """Index a note. payload keys: note_id, title, content, labels, user_id."""
        note_id = payload.get("note_id")
        if not note_id:
            return {"ok": False, "error": "Missing note_id"}

        title = str(payload.get("title") or "")
        content = str(payload.get("content") or "")
        labels = payload.get("labels") or []
        user_id = str(payload.get("user_id") or "")

        if not title and not content:
            return {"ok": False, "error": "Nothing to index"}

        # Build searchable text: title has 3× weight, labels 2×
        text_parts = []
        if title:
            text_parts.extend([title] * 3)
        if labels:
            label_str = " ".join(str(l) for l in labels)
            text_parts.extend([label_str] * 2)
        if content:
            text_parts.append(content[:3000])

        text = "\n".join(text_parts)
        vector = embed_text(text)

        if not vector:
            # API unavailable — store empty, skip (search will fall back to keyword)
            return {"ok": True, "indexed": False, "reason": "embedding_unavailable"}

        metadata = {
            "note_id": str(note_id),
            "title": title,
            "user_id": user_id,
            "labels": labels,
        }
        self._store.upsert(str(note_id), vector, content[:500], metadata)
        return {"ok": True, "indexed": True, "note_id": note_id}

    def remove(self, note_id: int | str) -> dict:
        """Remove a note from the vector store (called on delete)."""
        self._store.delete(str(note_id))
        return {"ok": True, "note_id": str(note_id)}

    def stats(self) -> dict:
        return {"indexed_count": self._store.count()}
