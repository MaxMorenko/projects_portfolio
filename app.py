import base64
import hashlib
import hmac
import json
import os
import sqlite3
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse

ROOT = Path(__file__).parent
PUBLIC = ROOT / 'public'
DB_PATH = ROOT / 'data.db'
SECRET = os.environ.get('APP_SECRET', 'dev-secret-change-me').encode('utf-8')
PORT = int(os.environ.get('PORT', '3000'))

conn = sqlite3.connect(DB_PATH, check_same_thread=False)
conn.row_factory = sqlite3.Row
conn.execute(
    '''CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        jira_base_url TEXT,
        jira_email TEXT,
        jira_api_token TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'''
)
conn.execute(
    '''CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        status TEXT NOT NULL,
        description TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )'''
)
conn.commit()


def hash_password(password: str) -> str:
    digest = hashlib.sha256(password.encode('utf-8')).hexdigest()
    return digest


def sign_token(payload: dict) -> str:
    payload = dict(payload)
    payload['exp'] = int(time.time()) + 60 * 60 * 8
    raw = json.dumps(payload, ensure_ascii=False, separators=(',', ':')).encode('utf-8')
    body = base64.urlsafe_b64encode(raw).decode('utf-8').rstrip('=')
    sig = hmac.new(SECRET, body.encode('utf-8'), hashlib.sha256).hexdigest()
    return f'{body}.{sig}'


def verify_token(token: str):
    try:
        body, sig = token.split('.', 1)
        expected = hmac.new(SECRET, body.encode('utf-8'), hashlib.sha256).hexdigest()
        if not hmac.compare_digest(expected, sig):
            return None
        padded = body + '=' * (-len(body) % 4)
        payload = json.loads(base64.urlsafe_b64decode(padded.encode('utf-8')))
        if payload.get('exp', 0) < int(time.time()):
            return None
        return payload
    except Exception:
        return None


class Handler(BaseHTTPRequestHandler):
    def send_json(self, status: int, body):
        data = json.dumps(body, ensure_ascii=False).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def read_json(self):
        length = int(self.headers.get('Content-Length', '0'))
        raw = self.rfile.read(length) if length > 0 else b'{}'
        return json.loads(raw.decode('utf-8')) if raw else {}

    def get_user(self):
        auth = self.headers.get('Authorization', '')
        token = auth[7:] if auth.startswith('Bearer ') else ''
        payload = verify_token(token)
        if not payload:
            return None
        row = conn.execute('SELECT id, name, email FROM users WHERE id=?', (payload['id'],)).fetchone()
        return row

    def serve_static(self, path: str):
        safe = path.lstrip('/')
        if safe == '':
            safe = 'index.html'
        full = (PUBLIC / safe).resolve()
        if not str(full).startswith(str(PUBLIC.resolve())) or not full.exists() or full.is_dir():
            return False

        content = full.read_bytes()
        mime = 'text/plain; charset=utf-8'
        if full.suffix == '.html':
            mime = 'text/html; charset=utf-8'
        elif full.suffix == '.css':
            mime = 'text/css; charset=utf-8'
        elif full.suffix == '.js':
            mime = 'application/javascript; charset=utf-8'

        self.send_response(200)
        self.send_header('Content-Type', mime)
        self.send_header('Content-Length', str(len(content)))
        self.end_headers()
        self.wfile.write(content)
        return True

    def do_GET(self):
        parsed = urlparse(self.path)
        if parsed.path == '/api/me':
            user = self.get_user()
            if not user:
                return self.send_json(401, {'error': 'Требуется авторизация'})
            data = conn.execute('SELECT id,name,email,jira_base_url,jira_email,jira_api_token FROM users WHERE id=?', (user['id'],)).fetchone()
            return self.send_json(200, {
                'id': data['id'],
                'name': data['name'],
                'email': data['email'],
                'jira_base_url': data['jira_base_url'] or '',
                'jira_email': data['jira_email'] or '',
                'jira_api_token': '********' if data['jira_api_token'] else ''
            })

        if parsed.path == '/api/projects':
            user = self.get_user()
            if not user:
                return self.send_json(401, {'error': 'Требуется авторизация'})
            rows = conn.execute('SELECT id,title,status,description,created_at FROM projects WHERE user_id=? ORDER BY id DESC', (user['id'],)).fetchall()
            return self.send_json(200, [dict(r) for r in rows])

        if parsed.path.startswith('/api/'):
            return self.send_json(404, {'error': 'Not found'})

        if not self.serve_static(parsed.path):
            self.serve_static('index.html')

    def do_POST(self):
        parsed = urlparse(self.path)
        payload = self.read_json()

        if parsed.path == '/api/auth/register':
            name = (payload.get('name') or '').strip()
            email = (payload.get('email') or '').strip().lower()
            password = payload.get('password') or ''
            if not name or not email or not password:
                return self.send_json(400, {'error': 'name, email и password обязательны'})
            try:
                cur = conn.execute('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)', (name, email, hash_password(password)))
                conn.commit()
            except sqlite3.IntegrityError:
                return self.send_json(409, {'error': 'Пользователь с таким email уже существует'})

            user = {'id': cur.lastrowid, 'name': name, 'email': email}
            return self.send_json(201, {'token': sign_token(user), 'user': user})

        if parsed.path == '/api/auth/login':
            email = (payload.get('email') or '').strip().lower()
            password = payload.get('password') or ''
            row = conn.execute('SELECT id,name,email,password_hash FROM users WHERE email=?', (email,)).fetchone()
            if not row or row['password_hash'] != hash_password(password):
                return self.send_json(401, {'error': 'Неверные учетные данные'})
            user = {'id': row['id'], 'name': row['name'], 'email': row['email']}
            return self.send_json(200, {'token': sign_token(user), 'user': user})

        if parsed.path == '/api/projects':
            user = self.get_user()
            if not user:
                return self.send_json(401, {'error': 'Требуется авторизация'})
            title = (payload.get('title') or '').strip()
            status = (payload.get('status') or '').strip()
            desc = (payload.get('description') or '').strip()
            if not title or not status:
                return self.send_json(400, {'error': 'title и status обязательны'})
            cur = conn.execute('INSERT INTO projects(user_id,title,status,description) VALUES(?,?,?,?)', (user['id'], title, status, desc))
            conn.commit()
            return self.send_json(201, {'id': cur.lastrowid, 'title': title, 'status': status, 'description': desc})

        self.send_json(404, {'error': 'Not found'})

    def do_PUT(self):
        parsed = urlparse(self.path)
        payload = self.read_json()

        if parsed.path == '/api/integrations/jira':
            user = self.get_user()
            if not user:
                return self.send_json(401, {'error': 'Требуется авторизация'})
            conn.execute(
                'UPDATE users SET jira_base_url=?, jira_email=?, jira_api_token=? WHERE id=?',
                (
                    (payload.get('jiraBaseUrl') or '').strip(),
                    (payload.get('jiraEmail') or '').strip(),
                    (payload.get('jiraApiToken') or '').strip(),
                    user['id'],
                ),
            )
            conn.commit()
            return self.send_json(200, {'message': 'Интеграция Jira сохранена'})

        self.send_json(404, {'error': 'Not found'})


if __name__ == '__main__':
    server = ThreadingHTTPServer(('0.0.0.0', PORT), Handler)
    print(f'Projects portfolio app listening on http://localhost:{PORT}')
    server.serve_forever()
