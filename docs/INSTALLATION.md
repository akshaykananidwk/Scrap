# Installation

ScrapX installs through a browser. You do **not** need SSH, Composer, Node.js or a build step.

## Requirements

| Requirement | Minimum | Notes |
|---|---|---|
| PHP | 8.2 | 8.3 / 8.4 also supported |
| MySQL / MariaDB | MySQL 8.0 or MariaDB 10.4 | InnoDB required |
| Apache | with `mod_rewrite` | nginx works with an equivalent rewrite |
| Extensions | `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl` | `gd` and `zip` strongly recommended |
| Disk | ~60 MB plus uploads | |

`gd` enables image resizing on upload; `zip` enables backups and the GitHub updater.
Without them the rest of the application still runs, and each screen says plainly which
feature is unavailable.

## 1. Upload

Upload every file to your hosting account.

- **If your domain points at `public_html`**, upload the contents there. The front controller
  (`index.php`) and `.htaccess` sit at the top level, so this works as-is — no docroot change needed.
- **If you can point the docroot anywhere**, point it at the project root all the same. The
  `app/`, `config/`, `database/`, `resources/` and `storage/` folders each carry a
  `Require all denied` rule.

## 2. Permissions

These must be writable by PHP (usually `755`, or `775` on some hosts):

```
/storage
/storage/logs
/storage/cache
/storage/backups
/storage/tmp
/uploads
```

The project root must be writable **once** so the installer can create `config.php`. You may
tighten it to read-only afterwards — but note that the GitHub updater needs write access to replace
files, so leave it writable if you intend to use it.

## 3. Create the database

In cPanel (or your host's panel) create:

1. A database, e.g. `myuser_scrapx`
2. A database user with a strong password
3. Grant that user **all privileges** on the database

Keep the name, user and password to hand. ScrapX does not need `CREATE DATABASE` rights — it only
needs to work inside a database you have already created.

## 4. Run the installer

Open `https://yourdomain.com/install`.

The wizard has six steps:

1. **Welcome** — what is about to happen
2. **Requirements** — a live check of PHP version, extensions and folder permissions
3. **Database** — connection details, tested before it continues
4. **Application** — site name, URL, timezone, administrator account
5. **Run** — writes `config.php`, applies 13 migrations (84 tables), seeds roles, permissions,
   settings, 36 states, cities, 23 categories, 104 materials, CMS pages, FAQs, notification
   templates and scheduler jobs, then creates your administrator
6. **Complete** — next steps

Optionally tick **Install demo data** on step 4 to get sample businesses, listings, auctions and
market rates you can delete later in one click (Admin → Import & export → Purge demo data).

When the installer finishes it writes `storage/installed.lock`. `/install` then returns **403**, so
nobody can replay it.

## 5. Set up the scheduler

Auctions close, notifications send and listings expire **only when the scheduler runs**. Pick one:

**Option A — real cron (recommended).** In your host's cron manager:

```
* * * * * php /home/youruser/public_html/cli.php cron >> /dev/null 2>&1
```

Every minute is fine: each job still respects its own interval.

**Option B — web fallback.** If your host has no cron, copy the secret URL from
Admin → Scheduler and point an external uptime monitor at it (every 5 minutes is plenty).
Treat that URL as a secret; rotate the key in Admin → Settings → Scheduler.

Admin → System health tells you at a glance whether the scheduler is running.

## 6. First things to configure

1. **Settings → General** — site name, contact details, logo
2. **Settings → Email / SMTP** — until this is configured, email is queued and marked *skipped*
3. **Settings → Commission** — your percentage, fixed fee and who pays
4. **Settings → Auctions** — anti-sniping window, default increments
5. **Settings → Backups** — enable automatic backups
6. **Admin → Updates** — connect your GitHub repository if you want one-click updates

## Installing from the command line

If you do have shell access:

```bash
DB_NAME=scrapx DB_USER=scrapx DB_PASS=secret DB_HOST=127.0.0.1 \
ADMIN_EMAIL=you@example.com ADMIN_MOBILE=9876543210 ADMIN_PASSWORD='Strong@123' \
SITE_URL=https://yourdomain.com DEMO_DATA=0 \
php cli.php install
```

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| Blank page or 500 on every URL | `mod_rewrite` is off, or `.htaccess` is not being read (`AllowOverride All`) |
| "Database connection failed" | Wrong credentials, or the user lacks privileges on that database |
| Installer says a folder is not writable | Fix permissions on the listed folder and press Re-check |
| Auctions never close | The scheduler is not running — see step 5 |
| Email never arrives | No SMTP configured; the queue marks those messages *skipped*, not sent |
| Uploads fail on large photos | Raise `upload_max_filesize` and `post_max_size` in your host's PHP settings |
