"""RAG (Retrieval-Augmented Generation) engine for Notezy AI.

Components:
- embeddings.py  : Gemini Embedding API wrapper
- vector_store.py: In-memory + JSON-persisted cosine similarity store
- retriever.py   : Smart retriever (keyword vs semantic mode)
- indexer.py     : Note indexer (called when notes are created/updated)
- shared.py      : Process-wide shared vector store singleton
"""
from .retriever import Retriever
from .indexer import NoteIndexer
from .vector_store import SimpleVectorStore
from .shared import get_shared_vector_store

__all__ = ["Retriever", "NoteIndexer", "SimpleVectorStore", "get_shared_vector_store"]
