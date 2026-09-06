#!/usr/bin/env python3
"""Local HTTP server for the Notezy AI agent. Bind to 127.0.0.1 only."""

from __future__ import annotations

import json
import os
import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))

from ai_agent import handle_request  # noqa: E402


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
        sys.stderr.write("[ai_agent] " + (fmt % args) + "\n")

    def _send(self, code: int, body: dict) -> None:
        raw = json.dumps(body, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def do_GET(self) -> None:  # noqa: N802
        if self.path == "/health":
            self._send(200, {"ok": True, "service": "notezy-ai-agent"})
            return
        self._send(404, {"ok": False, "error": "Not found"})

    def do_POST(self) -> None:  # noqa: N802
        if self.path not in ("/v1/chat", "/v1/complete"):
            self._send(404, {"ok": False, "error": "Not found"})
            return
        secret = os.environ.get("AI_AGENT_SHARED_SECRET", "")
        got = self.headers.get("X-Notezy-Agent-Secret", "")
        if secret and got != secret:
            self._send(401, {"ok": False, "error": "Unauthorized"})
            return
        length = int(self.headers.get("Content-Length", "0"))
        if length > 400_000:
            self._send(413, {"ok": False, "error": "Payload too large"})
            return
        try:
            payload = json.loads(self.rfile.read(length).decode("utf-8"))
        except (json.JSONDecodeError, UnicodeDecodeError):
            self._send(400, {"ok": False, "error": "Invalid JSON"})
            return
        if not isinstance(payload, dict):
            self._send(400, {"ok": False, "error": "Invalid request"})
            return
        result = handle_request(payload)
        status = 200 if result.get("ok") else int(result.get("status") or 500)
        self._send(status, result)


def main() -> None:
    _load_env()
    host = os.environ.get("AI_AGENT_HOST", "127.0.0.1")
    port = int(os.environ.get("AI_AGENT_PORT", "8765"))
    httpd = ThreadingHTTPServer((host, port), Handler)
    print(f"Notezy AI agent listening on http://{host}:{port}", flush=True)
    httpd.serve_forever()


if __name__ == "__main__":
    main()
