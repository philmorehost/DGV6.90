(function() {
    /**
     * ai-assistant.js — DGV6.90 AI Edition
     * Lightweight Floating AI Assistant Widget with Persistent History
     */

    const HANDLER_URL = window.__ai_handler_url || '/web/ai-handler.php';
    const PAGE_SLUG   = window.__ai_page_slug || 'unknown';

    // ─── 1. Styles ───────────────────────────────────────────
    const styles = `
        #ai-fab { position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.2); cursor: pointer; z-index: 10000; font-size: 24px; transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        #ai-fab:hover { transform: scale(1.1) rotate(5deg); }
        #ai-fab.recording { background: #ef4444; animation: ai-pulse 1.5s infinite; }
        @keyframes ai-pulse { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
        #ai-panel { position: fixed; bottom: 90px; right: 20px; width: 380px; height: 550px; background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); display: flex; flex-direction: column; overflow: hidden; transform: translateY(20px) scale(0.95); opacity: 0; pointer-events: none; transition: all 0.3s ease; z-index: 9999; border: 1px solid #e2e8f0; }
        #ai-panel.open { transform: translateY(0) scale(1); opacity: 1; pointer-events: all; }
        #ai-header { padding: 20px; background: linear-gradient(to right, #6366f1, #a855f7); color: white; display: flex; justify-content: space-between; align-items: center; }
        #ai-tokens { font-size: 10px; opacity: 0.8; }
        #ai-messages { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; background: #f8fafc; scroll-behavior: smooth; }
        .ai-msg { max-width: 85%; padding: 12px 16px; border-radius: 18px; font-size: 14px; line-height: 1.5; word-wrap: break-word; }
        .ai-msg.user { align-self: flex-end; background: #6366f1; color: white; border-bottom-right-radius: 4px; }
        .ai-msg.bot { align-self: flex-start; background: white; color: #1e293b; border-bottom-left-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .ai-msg.error { align-self: center; background: #fee2e2; color: #991b1b; font-size: 12px; }
        #ai-footer { padding: 15px; background: white; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; align-items: center; }
        #ai-input { flex: 1; border: 1px solid #e2e8f0; border-radius: 25px; padding: 10px 15px; outline: none; font-size: 14px; transition: border 0.3s; }
        #ai-input:focus { border-color: #6366f1; }
        .ai-btn { background: none; border: none; cursor: pointer; color: #64748b; font-size: 20px; transition: color 0.3s; display: flex; align-items: center; }
        .ai-btn:hover { color: #6366f1; }
        .ai-typing { font-size: 12px; color: #94a3b8; margin-bottom: 5px; font-style: italic; }
        @media (max-width: 480px) { #ai-panel { width: calc(100% - 40px); height: 80vh; bottom: 85px; } }
    `;

    // ─── 2. Template ──────────────────────────────────────────
    const template = `
        <button id="ai-fab" title="AI Assistant" aria-haspopup="true" aria-expanded="false">🤖</button>
        <div id="ai-panel">
            <div id="ai-header">
                <div>
                    <div style="font-weight: 700; font-size: 16px;">✨ AI Assistant</div>
                    <div id="ai-tokens">Loading tokens...</div>
                </div>
                <button id="ai-close" style="background:none; border:none; color:white; font-size: 20px; cursor:pointer;">&times;</button>
            </div>
            <div id="ai-messages"></div>
            <div id="ai-footer">
                <button id="ai-voice" class="ai-btn" title="Voice Command">🎤</button>
                <input type="text" id="ai-input" placeholder="Ask me anything..." autocomplete="off">
                <button id="ai-send" class="ai-btn" title="Send message">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>
        </div>
    `;

    // ─── 3. Initialization ────────────────────────────────────
    function injectWidget() {
        if (document.getElementById('ai-widget-container')) return;
        const styleEl = document.createElement('style');
        styleEl.textContent = styles;
        document.head.appendChild(styleEl);

        const container = document.createElement('div');
        container.id = 'ai-widget-container';
        container.innerHTML = template;
        document.body.appendChild(container);
    }

    function openPanel() {
        const panel = document.getElementById('ai-panel');
        const fab   = document.getElementById('ai-fab');
        const initialMsg = window.__ai_init_msg;
        
        panel.classList.add('open');
        fab.setAttribute('aria-expanded', 'true');
        fab.textContent = '✕';
        
        // Only load history if messages are empty
        const msgs = document.getElementById('ai-messages');
        if (msgs.children.length === 0) {
            loadHistory();
            if (msgs.children.length === 0 && initialMsg) appendMsg('bot', initialMsg);
        }
        
        document.getElementById('ai-input').focus();
    }

    function closePanel() {
        const panel = document.getElementById('ai-panel');
        const fab   = document.getElementById('ai-fab');
        panel.classList.remove('open');
        fab.setAttribute('aria-expanded', 'false');
        fab.textContent = '🤖';
    }

    function formatMarkdown(text) {
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>')
            .replace(/^- (.*)/gm, '• $1');
    }

    function appendMsg(role, text, skipSave = false) {
        const msgs = document.getElementById('ai-messages');
        const div  = document.createElement('div');
        div.className = `ai-msg ${role}`;
        
        if (role === 'bot') {
            div.innerHTML = formatMarkdown(text);
        } else {
            div.textContent = text;
        }
        
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;

        if (!skipSave && (role === 'user' || role === 'bot')) {
            saveHistory();
        }
        return div;
    }

    // ─── 4. History Management ────────────────────────────────
    function getHistoryKey() {
        const ctx = window.__ai_context || {};
        const user = ctx.username || 'anon';
        const vid  = ctx.vendor_id || '0';
        return `ai_hist_${vid}_${user}`;
    }

    function saveHistory() {
        const msgs = document.getElementById('ai-messages');
        const history = [];
        const msgEls = msgs.querySelectorAll('.ai-msg.user, .ai-msg.bot');
        
        // Take only last 20 messages for performance
        const start = Math.max(0, msgEls.length - 20);
        for (let i = start; i < msgEls.length; i++) {
            const el = msgEls[i];
            history.push({
                role: el.classList.contains('user') ? 'user' : 'bot',
                text: el.innerText || el.textContent
            });
        }

        if (history.length > 0) {
            localStorage.setItem(getHistoryKey(), JSON.stringify({
                ts: Date.now(),
                msgs: history
            }));
        }
    }

    function loadHistory() {
        const key = getHistoryKey();
        const raw = localStorage.getItem(key);
        if (!raw) return;

        try {
            const data = JSON.parse(raw);
            const now = Date.now();
            const ageHours = (now - data.ts) / (1000 * 60 * 60);
            
            // Expiry: 48 hours
            if (ageHours > 48) {
                localStorage.removeItem(key);
                return;
            }

            data.msgs.forEach(m => {
                appendMsg(m.role, m.text, true);
            });
        } catch (e) {
            console.error('AI History load failed', e);
        }
    }

    // ─── 5. Send Message ──────────────────────────────────────
    async function sendMessage(forceAction = null) {
        const input  = document.getElementById('ai-input');
        const sendBtn = document.getElementById('ai-send');
        const prompt = input.value.trim();
        if (!prompt) return;

        // Detect if this is a transaction command (Typed or Voice)
        const isTx = /send|buy|recharge|topup|data|airtime|pay|pin|exam/i.test(prompt);
        let action = forceAction || (isTx ? 'voice_vtu' : 'chat');
        let payloadExtra = {};

        // Robust Confirmation Detection
        const isConfirmation = /\b(yes|confirm|proceed|go ahead|yep|sure|ok|do it|okay|process)\b/i.test(prompt);
        const pendingVtu = sessionStorage.getItem('pending_vtu');

        if (isConfirmation && pendingVtu) {
            action = 'execute_vtu';
            payloadExtra.intent = JSON.parse(pendingVtu);
            sessionStorage.removeItem('pending_vtu'); 
        } else if (!isConfirmation && !isTx) {
            // Only clear if it's definitely NOT a confirmation and NOT a new transaction request
            sessionStorage.removeItem('pending_vtu');
        }

        input.value = '';
        sendBtn.disabled = true;
        appendMsg('user', prompt);

        const typing = document.createElement('div');
        typing.className = 'ai-typing';
        typing.textContent = (action === 'execute_vtu') ? 'Processing transaction...' : 'AI is thinking...';
        document.getElementById('ai-messages').appendChild(typing);
        document.getElementById('ai-messages').scrollTop = 99999;

        try {
            const context = window.__ai_context || {};
            const resp = await fetch(HANDLER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    prompt, 
                    action: action, 
                    ...payloadExtra,
                    context: {
                        page: PAGE_SLUG,
                        ...context
                    }
                }),
            });
            const data = await resp.json();
            if (typing && typing.parentNode) typing.remove();

            if (data.status === 'success') {
                appendMsg('bot', data.response);
                
                if (data.pending_vtu) {
                    sessionStorage.setItem('pending_vtu', JSON.stringify(data.pending_vtu));
                }

                const tokEl = document.getElementById('ai-tokens');
                if (tokEl && data.tokens_remaining !== undefined) {
                    tokEl.textContent = `${data.tokens_remaining.toLocaleString()} tokens remaining`;
                }
            } else {
                const errMessages = {
                    'AI_DISABLED':          'AI features are not enabled. Visit AI Settings to get started.',
                    'INSUFFICIENT_TOKENS':  'You\'ve run out of AI tokens. Visit AI Settings to buy more.',
                    'RATE_LIMITED':         'Slow down! Too many requests. Try again in a minute.',
                    'PROMPT_REJECTED':      'I can\'t process that request. Ask me a VTU business question.',
                    'AI_UNAVAILABLE':       'AI engine is temporarily offline. No tokens were charged.',
                };
                appendMsg('error', errMessages[data.code] || data.message || 'Something went wrong.');
            }
        } catch (e) {
            if (typing && typing.parentNode) typing.remove();
            appendMsg('error', 'Connection error. Please check your internet and try again.');
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    }

    // ─── 6. Wire Up Events ────────────────────────────────────
    function bindEvents() {
        document.getElementById('ai-fab').onclick   = () => {
            const panel = document.getElementById('ai-panel');
            panel.classList.contains('open') ? closePanel() : openPanel();
        };
        document.getElementById('ai-close').onclick = closePanel;
        document.getElementById('ai-send').onclick  = () => sendMessage();
        document.getElementById('ai-input').onkeydown = (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        };

        const voiceBtn = document.getElementById('ai-voice');
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new SpeechRecognition();
            recognition.lang = 'en-NG';
            recognition.interimResults = false;

            recognition.onstart = () => {
                voiceBtn.classList.add('recording');
                document.getElementById('ai-input').placeholder = "Listening...";
            };
            recognition.onend = () => {
                voiceBtn.classList.remove('recording');
                document.getElementById('ai-input').placeholder = "Ask me anything...";
            };
            recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                document.getElementById('ai-input').value = transcript;
                const isTx = /send|buy|recharge|topup|data|airtime|pay/i.test(transcript);
                sendMessage(isTx ? 'voice_vtu' : 'chat');
            };

            voiceBtn.onclick = () => {
                try { recognition.start(); } catch(e) { recognition.stop(); }
            };
        } else {
            voiceBtn.style.display = 'none';
        }
    }

    // ─── 7. Boot ─────────────────────────────────────────────
    function boot() {
        injectWidget();
        bindEvents();

        const tokEl = document.getElementById('ai-tokens');
        if (tokEl && window.__ai_tokens !== undefined) {
            tokEl.textContent = `${window.__ai_tokens.toLocaleString()} tokens remaining`;
        }

        if (window.__ai_auto_open) {
            setTimeout(() => {
                openPanel();
            }, 2000);
        }
    }

    window.__ai_open = openPanel;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
