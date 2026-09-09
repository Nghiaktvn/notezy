"""Gemini Embedding API wrapper — stdlib only, no SDK required.

Uses Google's gemini-embedding-001 model (768 dimensions via outputDimensionality).
Falls back gracefully if API is unavailable.
"""
from __future__ import annotations

import json
import os
import urllib.error
import urllib.request


EMBED_MODEL = "gemini-embedding-001"
BASE_URL = "https://generativelanguage.googleapis.com/v1beta"


def embed_text(text: str, api_key: str | None = None) -> list[float]:
    """Generate a 768-dimensional embedding vector for the given text.
    
    Returns empty list on failure so callers can degrade gracefully.
    """
    key = api_key or os.environ.get("GEMINI_API_KEY", "").strip()
    if not key or not text.strip():
        return []

    model_name = os.environ.get("GEMINI_EMBED_MODEL", EMBED_MODEL)
    base = os.environ.get("GEMINI_BASE_URL", BASE_URL).rstrip("/")
    url = f"{base}/models/{model_name}:embedContent"

    payload = {
        "model": f"models/{model_name}",
        "content": {"parts": [{"text": text[:8000]}]},
        "outputDimensionality": 768,
    }
    body = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=body,
        headers={
            "Content-Type": "application/json",
            "x-goog-api-key": key,
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        return data.get("embedding", {}).get("values", [])
    except (urllib.error.HTTPError, urllib.error.URLError, json.JSONDecodeError, KeyError):
        return []
