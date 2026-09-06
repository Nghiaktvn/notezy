"""Retrieval interface for current keyword search and future RAG.

Current version: the PHP backend searches MySQL and returns relevant notes.
Future upgrade:
  notes -> embeddings -> vector DB -> semantic retrieval -> LLM
This module is the extension point; it does not talk to MySQL.
"""

from __future__ import annotations


class Retriever:
    def build_query_hints(self, user_message: str) -> dict:
        return {"mode": "keyword", "query": user_message[:200]}
