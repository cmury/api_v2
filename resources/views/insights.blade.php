<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IMBY Insights — Chat</title>
    <style>
        :root {
            --bg: #0f172a; --panel: #1e293b; --panel-2: #273449; --border: #334155;
            --text: #e2e8f0; --muted: #94a3b8; --accent: #6366f1; --accent-2: #4f46e5;
            --user: #3730a3; --ok: #10b981; --err: #ef4444; --code: #0b1220;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg); color: var(--text); height: 100vh; display: flex; flex-direction: column;
        }
        header {
            padding: 14px 20px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 12px; background: rgba(15,23,42,.7);
        }
        header .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--err); box-shadow: 0 0 10px var(--err); }
        header .dot.on { background: var(--ok); box-shadow: 0 0 10px var(--ok); }
        header h1 { font-size: 15px; margin: 0; font-weight: 600; }
        header .sub { color: var(--muted); font-size: 12px; margin-left: auto; }

        .body { flex: 1; display: flex; min-height: 0; }
        .sidebar { width: 250px; border-right: 1px solid var(--border); display: flex; flex-direction: column; background: rgba(30,41,59,.4); }
        .sidebar.hidden { display: none; }
        .sidebar .new { margin: 12px; padding: 9px 12px; background: var(--accent); color: #fff; border: 0; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .sidebar .new:hover { background: var(--accent-2); }
        .sidebar .label { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .5px; padding: 4px 16px; }
        .threads { overflow-y: auto; flex: 1; padding: 4px 8px 12px; }
        .thread-item { padding: 9px 10px; border-radius: 8px; cursor: pointer; font-size: 13px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .thread-item:hover { background: var(--panel-2); color: var(--text); }
        .thread-item.active { background: var(--panel-2); color: var(--text); }

        main { flex: 1; overflow-y: auto; padding: 24px; }
        .wrap { max-width: 820px; margin: 0 auto; display: flex; flex-direction: column; gap: 16px; }

        .card { background: var(--panel); border: 1px solid var(--border); border-radius: 14px; padding: 18px; }
        .login h2 { margin: 0 0 4px; font-size: 16px; }
        .login p { margin: 0 0 14px; color: var(--muted); font-size: 13px; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; }
        input, button, textarea { font: inherit; }
        .field { flex: 1; min-width: 200px; }
        label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
        input[type=email], input[type=password], textarea {
            width: 100%; background: var(--panel-2); border: 1px solid var(--border); color: var(--text);
            border-radius: 10px; padding: 10px 12px; outline: none;
        }
        input:focus, textarea:focus { border-color: var(--accent); }
        button.primary { background: var(--accent); color: #fff; border: 0; border-radius: 10px; padding: 10px 16px; cursor: pointer; font-weight: 600; }
        button.primary:hover { background: var(--accent-2); }
        button.primary:disabled { opacity: .5; cursor: not-allowed; }
        .hint { color: var(--err); font-size: 13px; margin-top: 8px; min-height: 16px; }

        .msg { display: flex; gap: 10px; }
        .msg .avatar { width: 30px; height: 30px; border-radius: 8px; flex: 0 0 auto; display: grid; place-items: center; font-size: 13px; font-weight: 700; }
        .msg.user .avatar { background: var(--user); }
        .msg.bot .avatar { background: var(--accent-2); }
        .bubble { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; max-width: 100%; overflow: auto; }
        .msg.user .bubble { background: var(--panel-2); }
        .bubble .explain { margin: 0 0 8px; }
        .bubble.err { border-color: var(--err); }
        pre { background: var(--code); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; margin: 8px 0 0; overflow-x: auto; font-size: 12.5px; color: #cbd5e1; }
        .meta { color: var(--muted); font-size: 12px; margin-top: 6px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; font-size: 13px; }
        th, td { border: 1px solid var(--border); padding: 6px 10px; text-align: left; }
        th { background: var(--panel-2); color: var(--muted); font-weight: 600; }
        .chips { display: flex; gap: 8px; flex-wrap: wrap; }
        .chip { background: var(--panel-2); border: 1px solid var(--border); color: var(--muted); border-radius: 999px; padding: 6px 12px; font-size: 12.5px; cursor: pointer; }
        .chip:hover { color: var(--text); border-color: var(--accent); }
        .typing span { display: inline-block; width: 6px; height: 6px; margin: 0 1px; background: var(--muted); border-radius: 50%; animation: blink 1.2s infinite both; }
        .typing span:nth-child(2) { animation-delay: .2s; } .typing span:nth-child(3) { animation-delay: .4s; }
        @keyframes blink { 0%, 80%, 100% { opacity: .2; } 40% { opacity: 1; } }

        footer { border-top: 1px solid var(--border); padding: 14px 20px; background: rgba(15,23,42,.7); }
        .composer { max-width: 820px; margin: 0 auto; display: flex; gap: 10px; }
        .composer textarea { resize: none; height: 44px; max-height: 140px; }
        .hidden { display: none !important; }
    </style>
</head>
<body>
    <header>
        <div id="status-dot" class="dot"></div>
        <h1>IMBY Insights</h1>
        <div class="sub" id="status-text">Not connected</div>
    </header>

    <div class="body">
        <aside class="sidebar hidden" id="sidebar">
            <button class="new" id="new-chat">+ New chat</button>
            <div class="label">History</div>
            <div class="threads" id="threads"></div>
        </aside>

        <main>
            <div class="wrap">
                <div class="card login" id="login-card">
                    <h2>Connect to the API</h2>
                    <p>Log in to get a Bearer token, then ask questions about the planning data in plain English.</p>
                    <div class="row">
                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" id="email" value="grace@example.com" autocomplete="username">
                        </div>
                        <div class="field">
                            <label for="password">Password</label>
                            <input type="password" id="password" value="Password123!" autocomplete="current-password">
                        </div>
                    </div>
                    <div style="margin-top:14px" class="row">
                        <button class="primary" id="login-btn">Connect</button>
                    </div>
                    <div class="hint" id="login-hint"></div>
                </div>

                <div id="chat" class="hidden">
                    <div class="chips" id="chips">
                        <div class="chip">How many authorities are there per state?</div>
                        <div class="chip">In NSW which authority covers the greatest area?</div>
                        <div class="chip">How many applications have been lodged in Mawson ACT?</div>
                        <div class="chip">List 10 authorities in NSW with their region</div>
                    </div>
                    <div id="messages" style="margin-top:16px; display:flex; flex-direction:column; gap:16px;"></div>
                </div>
            </div>
        </main>
    </div>

    <footer>
        <div class="composer">
            <textarea id="q" placeholder="Log in first, then ask a question…" disabled></textarea>
            <button class="primary" id="send" disabled>Ask</button>
        </div>
    </footer>

    @verbatim
    <script>
        let token = localStorage.getItem('imby_token') || null;
        let currentThreadId = null;

        const dot = document.getElementById('status-dot');
        const statusText = document.getElementById('status-text');
        const loginCard = document.getElementById('login-card');
        const loginBtn = document.getElementById('login-btn');
        const loginHint = document.getElementById('login-hint');
        const chat = document.getElementById('chat');
        const chips = document.getElementById('chips');
        const messages = document.getElementById('messages');
        const qInput = document.getElementById('q');
        const sendBtn = document.getElementById('send');
        const sidebar = document.getElementById('sidebar');
        const threadsEl = document.getElementById('threads');

        function authHeaders(extra) {
            return Object.assign({ 'Accept': 'application/json', 'Authorization': 'Bearer ' + token }, extra || {});
        }

        function setConnected(on) {
            dot.classList.toggle('on', on);
            statusText.textContent = on ? 'Connected' : 'Not connected';
            loginCard.classList.toggle('hidden', on);
            chat.classList.toggle('hidden', !on);
            sidebar.classList.toggle('hidden', !on);
            qInput.disabled = !on;
            sendBtn.disabled = !on;
            qInput.placeholder = on ? 'Ask a question about the planning data…' : 'Log in first, then ask a question…';
        }

        async function login() {
            loginHint.textContent = '';
            loginBtn.disabled = true; loginBtn.textContent = 'Connecting…';
            try {
                const res = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ email: document.getElementById('email').value, password: document.getElementById('password').value }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Login failed');
                token = data.data.token;
                localStorage.setItem('imby_token', token);
                setConnected(true);
                await loadThreads();
                qInput.focus();
            } catch (e) {
                loginHint.textContent = e.message;
            } finally {
                loginBtn.disabled = false; loginBtn.textContent = 'Connect';
            }
        }

        function el(tag, cls, html) {
            const n = document.createElement(tag);
            if (cls) n.className = cls;
            if (html !== undefined) n.innerHTML = html;
            return n;
        }
        function escapeHtml(v) {
            return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        async function loadThreads() {
            try {
                const res = await fetch('/api/insights/threads', { headers: authHeaders() });
                if (!res.ok) return;
                const { data } = await res.json();
                threadsEl.innerHTML = '';
                (data || []).forEach(t => {
                    const item = el('div', 'thread-item' + (t.id === currentThreadId ? ' active' : ''), escapeHtml(t.title || ('Thread ' + t.id)));
                    item.title = t.title || '';
                    item.addEventListener('click', () => openThread(t.id));
                    threadsEl.appendChild(item);
                });
            } catch (e) { /* ignore */ }
        }

        function renderTable(rows) {
            if (!rows || rows.length === 0) return '<div class="meta">No rows returned.</div>';
            const cols = Object.keys(rows[0]);
            let h = '<table><thead><tr>' + cols.map(c => '<th>' + escapeHtml(c) + '</th>').join('') + '</tr></thead><tbody>';
            for (const r of rows.slice(0, 100)) h += '<tr>' + cols.map(c => '<td>' + escapeHtml(r[c]) + '</td>').join('') + '</tr>';
            h += '</tbody></table>';
            return h;
        }

        function addUser(text) {
            const m = el('div', 'msg user');
            m.appendChild(el('div', 'avatar', 'You'));
            const b = el('div', 'bubble'); b.textContent = text; m.appendChild(b);
            messages.appendChild(m); scroll();
        }
        function addBotTyping() {
            const m = el('div', 'msg bot');
            m.appendChild(el('div', 'avatar', 'AI'));
            const b = el('div', 'bubble'); b.appendChild(el('div', 'typing', '<span></span><span></span><span></span>'));
            m.appendChild(b); messages.appendChild(m); scroll(); return b;
        }
        function botAnswerHtml({ explanation, rows, row_count, sql, error }) {
            let html = '';
            if (explanation) html += '<p class="explain">' + escapeHtml(explanation) + '</p>';
            if (!error) {
                html += renderTable(rows);
                if (row_count !== undefined) html += '<div class="meta">' + row_count + ' row(s)</div>';
            }
            if (sql) html += '<pre>' + escapeHtml(sql) + '</pre>';
            return html;
        }

        async function openThread(id) {
            const res = await fetch('/api/insights/threads/' + id, { headers: authHeaders() });
            if (!res.ok) return;
            const { data } = await res.json();
            currentThreadId = data.id;
            chips.classList.add('hidden');
            messages.innerHTML = '';
            (data.messages || []).forEach(m => {
                if (m.role === 'user') { addUser(m.content); return; }
                const bubble = el('div', 'msg bot');
                bubble.appendChild(el('div', 'avatar', 'AI'));
                const b = el('div', 'bubble');
                const payload = m.payload || {};
                if (payload.error) b.classList.add('err');
                b.innerHTML = botAnswerHtml({
                    explanation: m.content, sql: m.sql,
                    rows: payload.rows, row_count: payload.row_count, error: payload.error,
                });
                bubble.appendChild(b); messages.appendChild(bubble);
            });
            loadThreads(); scroll();
        }

        function newChat() {
            currentThreadId = null;
            messages.innerHTML = '';
            chips.classList.remove('hidden');
            loadThreads();
            qInput.focus();
        }

        async function ask(question) {
            chips.classList.add('hidden');
            addUser(question);
            const bubble = addBotTyping();
            try {
                const res = await fetch('/api/insights/ask', {
                    method: 'POST',
                    headers: authHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ question, thread_id: currentThreadId }),
                });
                const data = await res.json();
                if (res.status === 401) {
                    bubble.classList.add('err'); bubble.innerHTML = 'Session expired. Please reconnect.';
                    setConnected(false); localStorage.removeItem('imby_token'); return;
                }
                if (data.thread_id) currentThreadId = data.thread_id;
                if (!res.ok) {
                    bubble.classList.add('err');
                    bubble.innerHTML = botAnswerHtml({ explanation: data.message, sql: data.sql || data.generated_sql, error: true })
                        + (data.reason ? '<div class="meta">' + escapeHtml(data.reason) + '</div>' : '');
                } else {
                    bubble.innerHTML = botAnswerHtml(data);
                }
                loadThreads();
            } catch (e) {
                bubble.classList.add('err'); bubble.textContent = e.message;
            }
            scroll();
        }

        function scroll() { const m = document.querySelector('main'); m.scrollTop = m.scrollHeight; }
        function send() { const q = qInput.value.trim(); if (!q) return; qInput.value = ''; ask(q); }

        loginBtn.addEventListener('click', login);
        sendBtn.addEventListener('click', send);
        document.getElementById('new-chat').addEventListener('click', newChat);
        qInput.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });
        chips.addEventListener('click', e => { if (e.target.classList.contains('chip')) { qInput.value = e.target.textContent; send(); } });

        if (token) { setConnected(true); loadThreads(); } else { setConnected(false); }
    </script>
    @endverbatim
</body>
</html>
