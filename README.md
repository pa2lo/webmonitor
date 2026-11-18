# Webmonitor

**Webmonitor** is a **Laravel-based web application** for monitoring website availability and uptime.
It automatically checks specified URLs every minute and provides a **Vue-powered dashboard** to:

- Manage and organize monitored URLs
- View uptime statistics
- Track response times and downtimes
- Manage daily or monthly email reports

## Installation

1. **Download the source code**
2. **Deploy the website** on your server
3. Go to **Settings** in the app and set a secure **Cron key**
4. Create a **CRON job** to call the **Monitoring Cron URL** (found in **Settings**) every minute

> **Note:**
> The Monitoring Cron URL route is **rate-limited** to **2 calls per minute**.

> **Note:**
> If you want to send email reports, create a **second CRON job** to call the **Reports Cron URL** (found in **Settings**) **once per day**.

## Node.js Integration (Optional)

For faster and more precise monitoring, Webmonitor supports an optional **Node.js** service that sends multiple asynchronous requests in parallel.

1. **Download and deploy** the [Webmonitor Node Service](https://www.github.com/pa2lo/webmonitor-node-service)
2. In Webmonitor app, go to **Settings** and set the **Node server** option to **Enabled**

Once enabled, you’ll gain access to Node settings for each monitored URL, allowing you to:

- Choose whether to use the **PHP** or **Node.js** server for monitoring
- Configure the **number of requests per attempt** for more accurate availability checks

## PHP monitoring updates

The website overview chart marks PHP monitoring algorithm updates on its date axis. Hover a dot to see what changed. Add entries to [`config/monitoring.php`](config/monitoring.php) under `php_algorithm_updates`, using the deployment date (`YYYY-MM-DD`) in the reporting timezone.

## Daily website statistics - update 11/26

1. Run the schema migration from the application directory.

   ```sh
   php artisan migrate
   ```

2. Build the existing daily summaries through yesterday.

   ```sh
   php artisan stats:backfill
   ```

   Run the same command again to resume after an interruption. The stats page includes outstanding raw history while backfill is in progress.

3. Create a **CRON job** to call **Daily stats Cron URL** (found in **Settings**) once a day after midnight, within the same time zone as `APP_TIMEZONE`.
