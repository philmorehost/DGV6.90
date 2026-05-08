/**
 * DGV6.90 AI Edition — WhatsApp Gateway Bridge
 * Uses @whiskeysockets/baileys (multi-device, no phone required)
 *
 * Endpoints (all on 127.0.0.1 only):
 *   POST /send       → { phone, message }     → { success, message_id }
 *   GET  /status     → { online, phone, qr_ready, uptime }
 *   GET  /qr         → { qr_base64 }          (PNG, base64-encoded)
 *   POST /disconnect → {}                     → { success }
 *
 * Start:  node index.js
 * Daemon: pm2 start index.js --name vtu-whatsapp
 */

'use strict';

const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const P = require('pino');
const http = require('http');
const qrcode = require('qrcode');
const fs = require('fs');
const path = require('path');

// ── Configuration ─────────────────────────────────────────────────────────────
const CONFIG = {
    PORT:           parseInt(process.env.WA_PORT || '3001'),
    HOST:           '127.0.0.1',       // Never expose to public internet
    AUTH_DIR:       path.join(__dirname, '.wa_auth'),
    RATE_LIMIT_MS:  2000,              // Minimum 2 seconds between messages (prevent ban)
    MAX_QUEUE:      50,                // Max queued messages before dropping
    LOG_LEVEL:      'silent',          // 'silent' | 'info' | 'debug'
};

// ── State ─────────────────────────────────────────────────────────────────────
let sock         = null;
let qrBase64     = null;
let isOnline     = false;
let linkedPhone  = null;
let startTime    = Date.now();
let sendQueue    = [];
let isSending    = false;
let reconnectAttempts = 0;

// ── Logger ─────────────────────────────────────────────────────────────────────
const logger = P({ level: CONFIG.LOG_LEVEL });

// ── Queue processor (rate-limited send) ────────────────────────────────────────
async function processQueue() {
    if (isSending || sendQueue.length === 0) return;
    isSending = true;

    const { phone, message, resolve, reject } = sendQueue.shift();
    try {
        const jid = phone.replace(/[^0-9]/g, '') + '@s.whatsapp.net';
        const result = await sock.sendMessage(jid, { text: message });
        resolve({ success: true, message_id: result.key.id });
    } catch (err) {
        reject({ success: false, error: err.message });
    }

    // Rate limit pause
    await new Promise(r => setTimeout(r, CONFIG.RATE_LIMIT_MS));
    isSending = false;
    processQueue();
}

// ── Baileys connection ──────────────────────────────────────────────────────────
async function connectWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState(CONFIG.AUTH_DIR);
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
        version,
        logger,
        auth: state,
        printQRInTerminal: true,
        browser: ['DGV VTU Platform', 'Chrome', '120.0.0'],
        generateHighQualityLinkPreview: false,
    });

    // Credentials update → persist
    sock.ev.on('creds.update', saveCreds);

    // Connection state changes
    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            // New QR code — encode as base64 PNG for the PHP admin UI
            try {
                qrBase64 = await qrcode.toDataURL(qr);
                console.log('[WA] New QR code generated. Scan via admin panel: /bc-spadmin/WhatsAppAIManager.php');
            } catch (e) {
                console.error('[WA] QR encode failed:', e.message);
            }
        }

        if (connection === 'open') {
            isOnline = true;
            qrBase64 = null;
            reconnectAttempts = 0;
            linkedPhone = sock.user?.id?.split(':')[0] || 'unknown';
            console.log(`[WA] ✅ Connected! Linked number: ${linkedPhone}`);
        }

        if (connection === 'close') {
            isOnline   = false;
            linkedPhone = null;
            const reason = new Boom(lastDisconnect?.error)?.output?.statusCode;
            console.log(`[WA] ❌ Disconnected. Reason: ${reason}`);

            if (reason === DisconnectReason.loggedOut) {
                console.log('[WA] Session logged out. Deleting auth files. Please re-scan QR.');
                try { fs.rmSync(CONFIG.AUTH_DIR, { recursive: true, force: true }); } catch(e) {}
                // Restart after 3s to generate fresh QR
                setTimeout(connectWhatsApp, 3000);
            } else if (reconnectAttempts < 10) {
                reconnectAttempts++;
                const delay = Math.min(reconnectAttempts * 5000, 60000);
                console.log(`[WA] Reconnecting in ${delay/1000}s (attempt ${reconnectAttempts})...`);
                setTimeout(connectWhatsApp, delay);
            } else {
                console.error('[WA] Max reconnect attempts reached. Manual intervention required.');
            }
        }
    });
}

// ── HTTP server ──────────────────────────────────────────────────────────────────
function json(res, statusCode, data) {
    res.writeHead(statusCode, { 'Content-Type': 'application/json', 'X-Robots-Tag': 'noindex' });
    res.end(JSON.stringify(data));
}

function parseBody(req) {
    return new Promise((resolve, reject) => {
        let body = '';
        req.on('data', chunk => { body += chunk; if (body.length > 10240) reject(new Error('Body too large')); });
        req.on('end', () => {
            try { resolve(JSON.parse(body || '{}')); } catch(e) { resolve({}); }
        });
        req.on('error', reject);
    });
}

const server = http.createServer(async (req, res) => {
    // Security: Only allow localhost
    const remoteAddr = req.socket.remoteAddress;
    if (remoteAddr !== '127.0.0.1' && remoteAddr !== '::1' && remoteAddr !== '::ffff:127.0.0.1') {
        return json(res, 403, { success: false, error: 'Forbidden' });
    }

    const url = req.url?.split('?')[0];

    // ── GET /status
    if (req.method === 'GET' && url === '/status') {
        return json(res, 200, {
            success: true,
            online:    isOnline,
            phone:     linkedPhone,
            qr_ready:  qrBase64 !== null,
            uptime_s:  Math.floor((Date.now() - startTime) / 1000),
            queue_len: sendQueue.length,
            version:   '6.9.2-ai',
        });
    }

    // ── GET /qr
    if (req.method === 'GET' && url === '/qr') {
        if (!qrBase64) {
            return json(res, 200, { success: false, qr_base64: null, message: isOnline ? 'Already connected' : 'No QR available yet. Wait a moment.' });
        }
        return json(res, 200, { success: true, qr_base64: qrBase64 });
    }

    // ── POST /send
    if (req.method === 'POST' && url === '/send') {
        if (!isOnline || !sock) {
            return json(res, 503, { success: false, error: 'WhatsApp not connected. Scan QR first.' });
        }
        const body = await parseBody(req);
        const { phone, message } = body;
        if (!phone || !message) {
            return json(res, 400, { success: false, error: 'phone and message are required' });
        }
        if (sendQueue.length >= CONFIG.MAX_QUEUE) {
            return json(res, 429, { success: false, error: 'Send queue full. Try again shortly.' });
        }

        // Sanitize phone: strip non-digits, ensure starts with country code
        const cleanPhone = phone.replace(/[^0-9]/g, '');
        if (cleanPhone.length < 10) {
            return json(res, 400, { success: false, error: 'Invalid phone number' });
        }
        // Normalize Nigerian numbers
        const normalizedPhone = cleanPhone.startsWith('0') ? '234' + cleanPhone.slice(1) : cleanPhone;

        try {
            const result = await new Promise((resolve, reject) => {
                sendQueue.push({ phone: normalizedPhone, message: String(message).slice(0, 4096), resolve, reject });
                processQueue();
                // Timeout after 30s
                setTimeout(() => reject({ success: false, error: 'Send timeout' }), 30000);
            });
            return json(res, 200, result);
        } catch (err) {
            return json(res, 500, err);
        }
    }

    // ── POST /disconnect
    if (req.method === 'POST' && url === '/disconnect') {
        if (sock) {
            await sock.logout().catch(() => {});
            sock = null;
            isOnline = false;
            linkedPhone = null;
        }
        return json(res, 200, { success: true, message: 'Disconnected. Restart to generate new QR.' });
    }

    json(res, 404, { success: false, error: 'Not found' });
});

server.listen(CONFIG.PORT, CONFIG.HOST, () => {
    console.log(`[WA] DGV6.90 WhatsApp Bridge listening on ${CONFIG.HOST}:${CONFIG.PORT}`);
    connectWhatsApp();
});

// Graceful shutdown
process.on('SIGINT', async () => {
    console.log('\n[WA] Shutting down gracefully...');
    if (sock) await sock.logout().catch(() => {});
    server.close(() => process.exit(0));
});
