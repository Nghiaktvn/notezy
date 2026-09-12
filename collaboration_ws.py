"""Minimal authenticated WebSocket relay for Notezy collaboration.

Uses only the Python standard library so the Docker demo has no extra package
installation step. Clients authenticate with a short-lived HMAC token issued by
api/collab_token.php and messages are broadcast only to the same note room.
"""
import base64, hashlib, hmac, json, os, socket, struct, threading, time
from urllib.parse import parse_qs, urlparse

HOST, PORT = "0.0.0.0", int(os.getenv("COLLAB_WS_PORT", "8766"))
secret_value = os.getenv("COLLAB_SECRET")
if not secret_value:
    raise RuntimeError("COLLAB_SECRET must be configured")
SECRET = secret_value.encode()
rooms, lock = {}, threading.Lock()

def valid_token(token):
    parts = token.split(":")
    if len(parts) != 4: return None
    user, note, expires, signature = parts
    try:
        if int(expires) < time.time(): return None
        payload = f"{user}:{note}:{expires}"
        expected = hmac.new(SECRET, payload.encode(), hashlib.sha256).hexdigest()
        if not hmac.compare_digest(expected, signature): return None
        return int(user), int(note)
    except ValueError: return None

def recv_exact(conn, size):
    data = b""
    while len(data) < size:
        chunk = conn.recv(size - len(data))
        if not chunk: raise ConnectionError()
        data += chunk
    return data

def receive_frame(conn):
    first, second = recv_exact(conn, 2)
    opcode, masked, length = first & 15, bool(second & 128), second & 127
    if length == 126: length = struct.unpack("!H", recv_exact(conn, 2))[0]
    elif length == 127: length = struct.unpack("!Q", recv_exact(conn, 8))[0]
    mask = recv_exact(conn, 4) if masked else b""
    payload = bytearray(recv_exact(conn, length))
    if masked:
        for i in range(len(payload)): payload[i] ^= mask[i % 4]
    return opcode, bytes(payload)

def send_frame(conn, payload):
    payload = payload.encode()
    header = bytes([129])
    if len(payload) < 126: header += bytes([len(payload)])
    elif len(payload) < 65536: header += bytes([126]) + struct.pack("!H", len(payload))
    else: header += bytes([127]) + struct.pack("!Q", len(payload))
    conn.sendall(header + payload)

def broadcast(note_id, sender, message):
    with lock: peers = list(rooms.get(note_id, set()))
    for peer in peers:
        if peer is sender: continue
        try: send_frame(peer, message)
        except OSError: pass

def handle(conn):
    note_id = None
    try:
        request = conn.recv(8192).decode("utf-8", "ignore")
        line = request.split("\r\n", 1)[0].split()
        headers = {p.split(":", 1)[0].lower(): p.split(":", 1)[1].strip() for p in request.split("\r\n")[1:] if ":" in p}
        parsed = urlparse(line[1])
        if parsed.path == "/health":
            conn.sendall(b"HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length:11\r\n\r\n{\"ok\":true}")
            return
        token = parse_qs(parsed.query).get("token", [""])[0]
        identity = valid_token(token)
        if not identity:
            conn.sendall(b"HTTP/1.1 401 Unauthorized\r\nContent-Length:0\r\n\r\n"); return
        _, note_id = identity
        key = headers.get("sec-websocket-key", "")
        accept = base64.b64encode(hashlib.sha1((key + "258EAFA5-E914-47DA-95CA-C5AB0DC85B11").encode()).digest()).decode()
        conn.sendall(("HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: " + accept + "\r\n\r\n").encode())
        with lock: rooms.setdefault(note_id, set()).add(conn)
        while True:
            opcode, payload = receive_frame(conn)
            if opcode == 8: break
            if opcode == 9: conn.sendall(b"\x8a\x00"); continue
            if opcode == 1:
                try:
                    data = json.loads(payload.decode())
                    if int(data.get("note_id", 0)) == note_id:
                        broadcast(note_id, conn, json.dumps(data, ensure_ascii=False))
                except (ValueError, UnicodeDecodeError): pass
    except (OSError, ConnectionError): pass
    finally:
        if note_id is not None:
            with lock:
                rooms.get(note_id, set()).discard(conn)
        try: conn.close()
        except OSError: pass

with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as server:
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind((HOST, PORT)); server.listen()
    while True:
        conn, _ = server.accept()
        threading.Thread(target=handle, args=(conn,), daemon=True).start()
