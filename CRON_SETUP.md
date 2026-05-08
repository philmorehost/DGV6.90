# DGV6.90 — Cron Job & WhatsApp Bridge Deployment Guide

## Quick Reference: All Cron Jobs

| Cron Expression | File | Purpose |
|---|---|---|
| `* * * * *` | `cron/ai_model_check.php` | Auto-detect Ollama model download completion |
| `*/5 * * * *` | `cron/aggregator_monitor.php` | Monitor API provider success rates |
| `0 7 * * *` | `cron/ai_daily_briefing.php` | AI-generated daily WhatsApp briefing to vendors |
| `0 10 * * *` | `cron/dormant_user_alert.php` | Re-engage inactive users via WhatsApp |
| `0 8 1 * *` | `cron/ai_monthly_blueprint.php` | Monthly full platform AI Blueprint audit via email |

---

## Step 1: Set Up Cron Jobs in cPanel

1. Log into **cPanel** → scroll to **Advanced** → click **Cron Jobs**
2. Add each job below by selecting the frequency and pasting the command:

### Ollama Model Check (Every 1 minute)
```
* * * * * /usr/bin/php /home/YOUR_USERNAME/public_html/cron/ai_model_check.php >> /home/YOUR_USERNAME/logs/ai_model_check.log 2>&1
```

### API Aggregator Monitor (Every 5 minutes)
```
*/5 * * * * /usr/bin/php /home/YOUR_USERNAME/public_html/cron/aggregator_monitor.php >> /home/YOUR_USERNAME/logs/aggregator_monitor.log 2>&1
```

### AI Daily Briefing (7:00 AM daily)
```
0 7 * * * /usr/bin/php /home/YOUR_USERNAME/public_html/cron/ai_daily_briefing.php >> /home/YOUR_USERNAME/logs/daily_briefing.log 2>&1
```

### Dormant User Alerts (10:00 AM daily)
```
0 10 * * * /usr/bin/php /home/YOUR_USERNAME/public_html/cron/dormant_user_alert.php >> /home/YOUR_USERNAME/logs/dormant_alert.log 2>&1
```

### AI Monthly Blueprint Audit (8:00 AM on the 1st of every month)
```
0 8 1 * * /usr/bin/php /home/YOUR_USERNAME/public_html/cron/ai_monthly_blueprint.php >> /home/YOUR_USERNAME/logs/blueprint.log 2>&1
```

> **Note:** Replace `/usr/bin/php` with your server's PHP path. Find it with: `which php`
> Replace `YOUR_USERNAME` with your actual cPanel username.

---

## Step 2: Deploy WhatsApp Bridge (Node.js)

The WhatsApp bridge is a Node.js process that must run continuously on your server.

### Prerequisites
- Node.js 18+ installed on VPS (not shared hosting)
- PM2 installed globally: `npm install -g pm2`
- Your server's port `3001` is NOT blocked by firewall (it only listens on 127.0.0.1, so it's internal)

### Installation

```bash
# 1. Navigate to the bridge directory
cd /home/YOUR_USERNAME/public_html/vtu_whatsapp_ai

# 2. Install dependencies
npm install

# 3. Start with PM2 (auto-restart on crash)
pm2 start index.js --name vtu-whatsapp

# 4. Save PM2 process list (survives server reboots)
pm2 save

# 5. Set PM2 to start on server boot
pm2 startup
# Run the command PM2 outputs from the above
```

### Link Your WhatsApp Account

1. After starting the bridge, go to your admin panel:
   **Super Admin → WhatsApp AI Manager** (`/bc-spadmin/WhatsAppAIManager.php`)
2. A QR code will appear on the page
3. Open WhatsApp on your phone → **Settings → Linked Devices → Link a Device**
4. Scan the QR code
5. Status will change to ✅ **Connected**

### Useful PM2 Commands

```bash
pm2 status                    # Check if bridge is running
pm2 logs vtu-whatsapp         # View live logs
pm2 restart vtu-whatsapp      # Restart bridge
pm2 stop vtu-whatsapp         # Stop bridge
pm2 delete vtu-whatsapp       # Remove from PM2
```

---

## Step 3: Install Ollama (if not done)

```bash
# Linux (VPS)
curl -fsSL https://ollama.com/install.sh | sh

# Start Ollama service
systemctl enable ollama
systemctl start ollama

# Pull recommended model (choose based on RAM)
# 8GB+ RAM:
ollama pull phi4-mini

# 16GB+ RAM (higher quality):
ollama pull gemma3:4b

# Verify models loaded:
ollama list
```

> **Binding to localhost only** (security requirement):
> Ollama by default binds to 127.0.0.1:11434 — this is correct and safe.
> Never expose Ollama to the public internet.

---

## Step 4: Run Integration Tests

After deploying everything, run the test suite from SSH:

```bash
cd /home/YOUR_USERNAME/public_html
php tests/ai_integration_test.php
```

You should see all ✅ passes. Fix any ❌ failures before going live.

---

## Step 5: Database Auto-Migration

On the first page load after deployment, `bc-tables.php` will automatically run all pending schema migrations including:
- `sas_ai_transactions` — AI billing ledger
- `sas_ai_audit_log` — Security events
- `sas_ai_whitelist` — VIP user whitelist
- `sas_whatsapp_gateway` — WhatsApp send log
- `sas_rate_limits` — Rate limit tracker
- `sas_ai_page_guides` — Cached AI tips

No manual SQL migration is needed.

---

## Environment Variables (Optional)

You can set these in your server environment or a `.env` file:

| Variable | Default | Description |
|---|---|---|
| `WA_PORT` | `3001` | WhatsApp bridge listening port |
| `OLLAMA_HOST` | `127.0.0.1:11434` | Ollama API endpoint |
| `AI_DEFAULT_MODEL` | `phi4-mini` | Default Ollama model to use |

---

## Troubleshooting

| Problem | Solution |
|---|---|
| WhatsApp bridge shows "Offline" | Run `pm2 status` via SSH — restart if stopped |
| QR code not appearing | Bridge is starting — wait 10 seconds and refresh |
| Ollama returns no response | Run `ollama list` — ensure a model is downloaded |
| Cron jobs not running | Verify PHP path with `which php` in cPanel terminal |
| AI tokens not deducting | Check `sas_ai_transactions` table — if empty, AI calls aren't completing |
| High memory usage | Switch to `phi4-mini` model: `ollama pull phi4-mini` |
