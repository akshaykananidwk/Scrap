# The scheduler

Auctions close, notifications send, listings expire and backups run **only when the scheduler
runs**. Without it the marketplace still serves pages, but nothing time-based happens.

## Option A — real cron (recommended)

```
* * * * * php /path/to/cli.php cron >> /dev/null 2>&1
```

Run it every minute. Each job has its own interval and is skipped until it is due, so this is cheap.

## Option B — web fallback

For hosts with no cron access. Copy the secret URL from **Admin → Scheduler**:

```
https://yourdomain.com/cron/run?key=<secret>
```

Point an external uptime monitor (UptimeRobot, cron-job.org, a monitoring service you already use)
at it every 5 minutes. Treat the key as a password — anyone holding it can trigger the scheduler.
Rotate it in Admin → Settings → Scheduler.

## The jobs

| Job | Default interval | What it does |
|---|---|---|
| `auction_lifecycle` | 1 min | Starts scheduled auctions, closes ended ones, marks unsold/awarded |
| `auction_alerts` | 5 min | "Ending soon" alerts to watchers and bidders |
| `expire_offers` | 15 min | Expires offers past their validity |
| `expire_listings` | 60 min | Expires listings and requirements past their expiry date |
| `close_rfqs` | 15 min | Moves RFQs to closing-soon, then closed |
| `notification_queue` | 1 min | Drains the outbound queue with retry and backoff |
| `update_check` | daily | Checks GitHub for a new version (when configured) |
| `auto_backup` | daily | Scheduled backup (when enabled) |
| `cleanup` | hourly | Prunes rate limits, expired OTPs, password resets, old views, runs and logs |
| `refresh_stats` | hourly | Recomputes business statistics and risk scores |

## Monitoring

**Admin → Scheduler** shows every job with its last run, duration, run count, failure count and last
message, plus a log of recent runs. Any job can be run on demand from that screen.

**Admin → System health** warns if the scheduler has not run in the last hour, because that is the
point at which auctions stop closing on time.
