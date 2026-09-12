"""End-to-end smoke test for the authenticated Notezy WebSocket relay.

Usage: python tests/test_collaboration_ws.py '<token from api/collab_token.php>'
It connects two independently opened clients in the same authorized note room,
sends one draft event, and confirms the other client receives it.
"""
import base64
import json
import os
import secrets
import socket
import struct
import sys


def connect(token):
    client = socket.create_connection(("127.0.0.1", 8766), timeout=5)
    key = base64.b64encode(secrets.token_bytes(16)).decode()
    request = (
        f"GET /?token={token} HTTP/1.1\r\n"
        "Host: localhost:8766\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
        f"Sec-WebSocket-Key: {key}\r\nSec-WebSocket-Version: 13\r\n\r\n"
    )
    client.sendall(request.encode())
    response = client.recv(4096).decode("utf-8", "replace")
    if "101 Switching Protocols" not in response:
        raise RuntimeError("WebSocket handshake failed: " + response.split("\r\n")[0])
    return client


def masked_text(client, data):
    payload = data.encode()
    mask = secrets.token_bytes(4)
    header = bytearray([0x81])
    if len(payload) < 126:
        header.append(0x80 | len(payload))
    elif len(payload) < 65536:
        header.extend([0x80 | 126])
        header.extend(struct.pack("!H", len(payload)))
    else:
        raise ValueError("payload too large")
    encoded = bytes(byte ^ mask[index % 4] for index, byte in enumerate(payload))
    client.sendall(header + mask + encoded)


def recv_exact(client, size):
    data = b""
    while len(data) < size:
        chunk = client.recv(size - len(data))
        if not chunk:
            raise ConnectionError("relay closed the connection")
        data += chunk
    return data


def receive_text(client):
    first, second = recv_exact(client, 2)
    if first & 0x0F != 1:
        raise RuntimeError("unexpected WebSocket opcode")
    length = second & 0x7F
    if length == 126:
        length = struct.unpack("!H", recv_exact(client, 2))[0]
    elif length == 127:
        length = struct.unpack("!Q", recv_exact(client, 8))[0]
    return recv_exact(client, length).decode()


if len(sys.argv) != 2:
    raise SystemExit("usage: test_collaboration_ws.py TOKEN")

token = sys.argv[1]
parts = token.split(":")
if len(parts) != 4:
    raise SystemExit("invalid token format")
note_id = int(parts[1])
first = second = None
try:
    first = connect(token)
    second = connect(token)
    event = {"type": "draft", "note_id": note_id, "title": "Realtime check", "content": "delivered"}
    masked_text(first, json.dumps(event))
    received = json.loads(receive_text(second))
    if received != event:
        raise RuntimeError("relay payload mismatch")
    print("PASS: authenticated WebSocket handshake and same-note broadcast")
finally:
    for client in (first, second):
        if client:
            try:
                client.close()
            except OSError:
                pass
