# DGV6.90 — Cloud AI & WhatsApp Bridge Deployment Guide

## Quick Reference: All Cron Jobs

| Cron Expression | File | Purpose |
|---|---|---|
| `*/5 * * * *` | `cron/aggregator_monitor.php` | Monitor API provider success rates |
| `0 7 * * *` | `cron/ai_daily_briefing.php` | AI-generated daily WhatsApp briefing to vendors |
| `0 10 * * *` | `cron/dormant_user_alert.php` | Re-engage inactive users via WhatsApp |
| `0 8 1 * *` | `cron/ai_monthly_blueprint.php` | Monthly full platform AI Blueprint audit via email |

---

## Step 1: Set Up Cron Jobs in cPanel

1. Log into **cPanel** → scroll to **Advanced** → click **Cron Jobs**
2. Add each job below by selecting the frequency and pasting the command:

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

---

## Step 2: Deploy WhatsApp Bridge (Node.js)

The WhatsApp bridge is a Node.js process that must run continuously on your server.

### Prerequisites
- Node.js 18+ installed on VPS (not shared hosting)
- PM2 installed globally: `npm install -g pm2`

### Installation

```bash
# 1. Navigate to the bridge directory
cd /home/YOUR_USERNAME/public_html/vtu_whatsapp_ai

# 2. Install dependencies
npm install

# 3. Start with PM2
pm2 start index.js --name vtu-whatsapp
pm2 save
pm2 startup
```

---

## Step 3: Configure Cloud AI API Keys

This platform uses pure Cloud AI (Gemini, DeepSeek, or Groq). No local binaries are required.

1. Go to **Super Admin → AI Management** (`/bc-spadmin/AIManagement.php`)
2. Select your preferred provider (Google Gemini is recommended)
3. Paste your API Key and click **Update Cloud Connection**
4. Click the **Test Connection** button to verify reachability.

---

## Step 4: Run Integration Tests

After deploying everything, run the test suite from SSH:

```bash
cd /home/YOUR_USERNAME/public_html
php tests/ai_integration_test.php
```

---

## Troubleshooting

| Problem | Solution |
|---|---|
| AI engine offline | Check your API Key in Super Admin panel and ensure the server has outbound internet access. |
| WhatsApp bridge offline | Run `pm2 status` via SSH — restart if stopped |
| QR code not appearing | Bridge is starting — wait 10 seconds and refresh |
| AI tokens not deducting | Check `sas_ai_transactions` table in DB. |
