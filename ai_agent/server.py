#!/usr/bin/env python3
"""Notezy AI Copilot — HTTP server. Bind to 127.0.0.1 only.

Endpoints:
    GET  /health       — Health check
    POST /v1/chat      — AI chat (main endpoint, called by PHP)
    POST /v1/complete  — Alias for /v1/chat
    POST /v1/index     — Index a note for semantic search (called on note create/update)
    DELETE /v1/index   — Remove a note from the vector index (called on note delete)
    GET  /v1/index/stats — Vector store statistics
"""

from __future__ import annotations

import json
import os
import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT.parent))

from ai_agent.agent import handle_request  # noqa: E402
from ai_agent.rag.indexer import NoteIndexer  # noqa: E402
from ai_agent.rag.shared import get_shared_vector_store  # noqa: E402

# Shared vector store + indexer (module-level singletons)
# Uses the same SimpleVectorStore instance as the agent's retriever so
# newly indexed notes are immediately visible to semantic_search.
_vector_store = get_shared_vector_store()
_indexer = NoteIndexer(vector_store=_vector_store)


def _load_env() -> None:
    env_path = ROOT.parent / ".env"
    if not env_path.exists():
        return
    for line in env_path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        name, value = line.split("=", 1)
        name = name.strip()
        value = value.strip().strip('"').strip("'")
        if name and name not in os.environ:
            os.environ[name] = value


class Handler(BaseHTTPRequestHandler):
    def log_message(self, fmt: str, *args) -> None:  # noqa: A003
        sys.stderr.write("[notezy-ai] " + (fmt % args) + "\n")

    def _send(self, code: int, body: dict) -> None:
        raw = json.dumps(body, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def _auth(self) -> bool:
        """Check shared secret header. Returns True if authorized."""
        secret = os.environ.get("AI_AGENT_SHARED_SECRET", "")
        got = self.headers.get("X-Notezy-Agent-Secret", "")
        return not secret or got == secret

    def _read_json(self, max_bytes: int = 400_000) -> tuple[dict | None, int]:
        """Read and parse JSON body. Returns (parsed_dict, error_code)."""
        length = int(self.headers.get("Content-Length", "0"))
        if length > max_bytes:
            return None, 413
        try:
            payload = json.loads(self.rfile.read(length).decode("utf-8"))
        except (json.JSONDecodeError, UnicodeDecodeError):
            return None, 400
        if not isinstance(payload, dict):
            return None, 400
        return payload, 0

    # ── GET ─────────────────────────────────────────────────────────────────

    def do_GET(self) -> None:  # noqa: N802
        if self.path == "/health":
            self._send(200, {
                "ok": True,
                "service": "notezy-ai-copilot",
                "version": "2.0",
                "indexed_notes": _vector_store.count(),
            })
            return

        if self.path == "/v1/index/stats":
            if not self._auth():
                self._send(401, {"ok": False, "error": "Unauthorized"})
                return
            self._send(200, {"ok": True, "data": _indexer.stats()})
            return

        self._send(404, {"ok": False, "error": "Not found"})

    # ── POST ────────────────────────────────────────────────────────────────

    def do_POST(self) -> None:  # noqa: N802
        if not self._auth():
            self._send(401, {"ok": False, "error": "Unauthorized"})
            return

        # ── Chat endpoint ────────────────────────────────────────────────────
        if self.path in ("/v1/chat", "/v1/complete"):
            payload, err = self._read_json()
            if err:
                codes = {413: "Payload too large", 400: "Invalid JSON"}
                self._send(err, {"ok": False, "error": codes.get(err, "Error")})
                return
            result = handle_request(payload)
            status = 200 if result.get("ok") else int(result.get("status") or 500)
            self._send(status, result)
            return

        # ── Index endpoint ────────────────────────────────────────────────────
        if self.path == "/v1/index":
            payload, err = self._read_json(max_bytes=200_000)
            if err:
                self._send(err, {"ok": False, "error": "Invalid request"})
                return
            result = _indexer.index(payload)
            self._send(200 if result.get("ok") else 500, result)
            return

        self._send(404, {"ok": False, "error": "Not found"})

    # ── DELETE ───────────────────────────────────────────────────────────────

    def do_DELETE(self) -> None:  # noqa: N802
        if not self._auth():
            self._send(401, {"ok": False, "error": "Unauthorized"})
            return

        if self.path == "/v1/index":
            payload, err = self._read_json(max_bytes=1024)
            if err:
                self._send(err, {"ok": False, "error": "Invalid request"})
                return
            note_id = payload.get("note_id")
            if not note_id:
                self._send(400, {"ok": False, "error": "Missing note_id"})
                return
            result = _indexer.remove(note_id)
            self._send(200, result)
            return

        self._send(404, {"ok": False, "error": "Not found"})


def main() -> None:
    # Ensure stdout supports UTF-8 on Windows CP1252 terminals
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    if hasattr(sys.stderr, "reconfigure"):
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")

    _load_env()
    host = os.environ.get("AI_AGENT_HOST", "127.0.0.1")
    port = int(os.environ.get("AI_AGENT_PORT", "8765"))
    httpd = ThreadingHTTPServer((host, port), Handler)
    provider = os.environ.get("LLM_PROVIDER", "gemini")
    print(f"[notezy-ai] Notezy AI Copilot v2.0 — provider={provider}", flush=True)
    print(f"[notezy-ai] Listening on http://{host}:{port}", flush=True)
    print(f"[notezy-ai] Vector store: {_vector_store.count()} notes indexed", flush=True)
    httpd.serve_forever()


if __name__ == "__main__":
    main()
