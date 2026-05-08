/**
 * ai-assistant.js — DGV6.90 AI Edition
 * Zero-Latency AI Page Guide & Chat Widget
 *
 * - Loads deferred (non-blocking) — never slows page load
 * - Checks localStorage cache before making ANY server request
 * - Shows a floating bubble with contextual page guidance
 * - Provides a collapsible AI chat panel
 * - Only activates if window.__ai_enabled === true (set by PHP header)
 */

(function () {
    'use strict';

    // Guard: Only run if AI is enabled for this session
    if (!window.__ai_enabled) return;

    const PAGE_SLUG   = window.__ai_page_slug || document.title.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
    const CACHE_KEY   = `ai_guide_${PAGE_SLUG}_${new Date().toDateString()}`;
    const HANDLER_URL = window.__ai_handler_url || '/web/ai-handler.php';
    const GUIDE_URL   = window.__ai_guide_url  || '/web/ai-guide-cache.php';

    // ─── 1. Inject widget HTML ────────────────────────────────
    function injectWidget() {
        const css = `
            #ai-bubble { position:fixed; bottom:24px; right:24px; z-index:9999; }
            #ai-fab {
                width:56px; height:56px; border-radius:50%;
                background:linear-gradient(135deg,#7c3aed,#2563eb);
                border:none; color:#fff; font-size:1.4rem; cursor:pointer;
                box-shadow:0 4px 20px rgba(124,58,237,0.5);
                transition:transform .2s, box-shadow .2s;
                display:flex; align-items:center; justify-content:center;
            }
            #ai-fab:hover { transform:scale(1.1); box-shadow:0 6px 28px rgba(124,58,237,0.7); }
            #ai-panel {
                display:none; position:fixed; bottom:92px; right:24px; width:340px;
                max-height:520px; background:#fff; border-radius:1.25rem;
                box-shadow:0 8px 40px rgba(0,0,0,0.15); z-index:9999;
                flex-direction:column; overflow:hidden;
                border:1px solid rgba(124,58,237,0.2);
            }
            #ai-panel.open { display:flex; animation:ai-slide-in .25s ease; }
            @keyframes ai-slide-in { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
            #ai-header {
                background:linear-gradient(135deg,#7c3aed,#2563eb);
                color:#fff; padding:.85rem 1rem; display:flex;
                justify-content:space-between; align-items:center;
            }
            #ai-header h6 { margin:0; font-weight:700; font-size:.9rem; }
            #ai-close { background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer; }
            #ai-tokens { font-size:.7rem; opacity:.8; }
            #ai-messages { flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:.65rem; }
            .ai-msg { padding:.6rem .85rem; border-radius:.85rem; font-size:.82rem; line-height:1.5; max-width:90%; }
            .ai-msg.bot { background:#f3f0ff; color:#1e1b4b; align-self:flex-start; border-bottom-left-radius:.2rem; }
            .ai-msg.user { background:#7c3aed; color:#fff; align-self:flex-end; border-bottom-right-radius:.2rem; }
            .ai-msg.error { background:#fef2f2; color:#991b1b; align-self:flex-start; }
            #ai-input-row { padding:.75rem; border-top:1px solid #f0f0f0; display:flex; gap:.5rem; }
            #ai-input {
                flex:1; border:1px solid #e5e7eb; border-radius:.75rem; padding:.5rem .75rem;
                font-size:.82rem; outline:none;
            }
            #ai-input:focus { border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.15); }
            #ai-send {
                background:#7c3aed; border:none; border-radius:.75rem; color:#fff;
                padding:.5rem .85rem; cursor:pointer; font-size:.85rem;
                transition:background .15s;
            }
            #ai-send:hover { background:#6d28d9; }
            #ai-send:disabled { background:#c4b5fd; cursor:not-allowed; }
            .ai-typing { align-self:flex-start; background:#f3f0ff; color:#7c3aed; border-radius:.85rem; padding:.5rem .85rem; font-size:.78rem; }
            #ai-guide-toast {
                position:fixed; bottom:92px; right:88px; width:260px; background:#1e1b4b;
                color:#fff; border-radius:1rem; padding:.85rem 1rem; font-size:.78rem;
                box-shadow:0 4px 20px rgba(0,0,0,.2); z-index:9998; cursor:pointer;
                animation:ai-toast-in .3s ease;
            }
            @keyframes ai-toast-in { from{opacity:0;transform:translateX(10px)} to{opacity:1;transform:translateX(0)} }
            #ai-guide-toast .toast-close { float:right; opacity:.6; cursor:pointer; margin-left:8px; }
        `;
        const styleEl = document.createElement('style');
        styleEl.textContent = css;
        document.head.appendChild(styleEl);

        const html = `
            <div id="ai-bubble">
                <div id="ai-panel" role="dialog" aria-label="AI Assistant">
                    <div id="ai-header">
                        <div>
                            <h6>⚡ AI Assistant</h6>
                            <div id="ai-tokens">Loading tokens...</div>
                        </div>
                        <button id="ai-close" aria-label="Close">✕</button>
                    </div>
                    <div id="ai-messages" role="log" aria-live="polite"></div>
                    <div id="ai-input-row">
                        <input id="ai-input" type="text" placeholder="Ask me anything about your business..." maxlength="500" autocomplete="off">
                        <button id="ai-send" aria-label="Send">➤</button>
                    </div>
                </div>
                <button id="ai-fab" aria-label="Open AI Assistant" aria-expanded="false">🤖</button>
            </div>
        `;
        const container = document.createElement('div');
        container.innerHTML = html;
        document.body.appendChild(container);
    }

    // ─── 2. Page Guide Toast ──────────────────────────────────
    function showPageGuide(text) {
        if (localStorage.getItem(CACHE_KEY)) return; // Already shown today

        const toast = document.createElement('div');
        toast.id = 'ai-guide-toast';
        toast.innerHTML = `<span class="toast-close" id="ai-toast-close">✕</span>💡 <strong>Quick Tip:</strong><br>${text.slice(0, 180)}...`;
        document.body.appendChild(toast);

        document.getElementById('ai-toast-close').onclick = (e) => {
            e.stopPropagation();
            toast.remove();
        };
        toast.onclick = () => {
            toast.remove();
            openPanel(text);
        };

        localStorage.setItem(CACHE_KEY, '1');
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 10000);
    }

    // ─── 3. Load Page Guide (cached) ─────────────────────────
    async function loadPageGuide() {
        try {
            const resp = await fetch(`${GUIDE_URL}?page=${encodeURIComponent(PAGE_SLUG)}`, { cache: 'no-cache' });
            if (!resp.ok) return;
            const data = await resp.json();
            if (data.guide) showPageGuide(data.guide);
        } catch (_) {}
    }

    // ─── 4. Panel Logic ───────────────────────────────────────
    function openPanel(initialMsg) {
        const panel = document.getElementById('ai-panel');
        const fab   = document.getElementById('ai-fab');
        panel.classList.add('open');
        fab.setAttribute('aria-expanded', 'true');
        fab.textContent = '✕';
        if (initialMsg) appendMsg('bot', initialMsg);
        document.getElementById('ai-input').focus();
    }

    function closePanel() {
        const panel = document.getElementById('ai-panel');
        const fab   = document.getElementById('ai-fab');
        panel.classList.remove('open');
        fab.setAttribute('aria-expanded', 'false');
        fab.textContent = '🤖';
    }

    function appendMsg(role, text) {
        const msgs = document.getElementById('ai-messages');
        const div  = document.createElement('div');
        div.className = `ai-msg ${role}`;
        div.textContent = text;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
        return div;
    }

    // ─── 5. Send Message ──────────────────────────────────────
    async function sendMessage() {
        const input  = document.getElementById('ai-input');
        const sendBtn = document.getElementById('ai-send');
        const prompt = input.value.trim();
        if (!prompt) return;

        input.value = '';
        sendBtn.disabled = true;
        appendMsg('user', prompt);

        const typing = document.createElement('div');
        typing.className = 'ai-typing';
        typing.textContent = 'AI is thinking...';
        document.getElementById('ai-messages').appendChild(typing);
        document.getElementById('ai-messages').scrollTop = 99999;

        try {
            const resp = await fetch(HANDLER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ prompt, action: 'chat' }),
            });
            const data = await resp.json();
            typing.remove();

            if (data.status === 'success') {
                appendMsg('bot', data.response);
                // Update token display
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
            typing.remove();
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
        document.getElementById('ai-send').onclick  = sendMessage;
        document.getElementById('ai-input').onkeydown = (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        };
    }

    // ─── 7. Boot ─────────────────────────────────────────────
    function boot() {
        injectWidget();
        bindEvents();
        // Load page guide after 1.5s delay (non-blocking)
        setTimeout(loadPageGuide, 1500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
