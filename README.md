# ScrapX — B2B Scrap Trading Marketplace for India

A production marketplace for scrap trading: listings, live auctions, buyer requirements, RFQs,
orders settled on actual weighbridge weight, GST invoices, and a full admin panel.

**Runs on ordinary shared hosting.** PHP 8.2+, MySQL/MariaDB, Apache. No Composer, no Node.js, no
build step, no SSH required to install or to update.

## Quick start

1. Upload the files to your hosting account
2. Create a MySQL database and user
3. Open `https://yourdomain.com/install`
4. Add one cron line (or use the built-in web fallback)

Full details in [docs/INSTALLATION.md](docs/INSTALLATION.md).

## What it does

| | |
|---|---|
| **Sell** | Nine-step listing wizard, fixed price, negotiable, make-an-offer, or auction |
| **Auction** | Forward and reverse, live bidding, anti-sniping extensions, reserve price, bidder eligibility |
| **Buy** | Filtered search, buyer requirements with automatic seller matching, multi-line RFQs |
| **Negotiate** | Offers and counter-offers, with the whole thread on record |
| **Fulfil** | 13-state order lifecycle, transport, e-way bill, **settlement on actual weight** |
| **Get paid** | UPI/NEFT/RTGS/cash/credit, platform commission, GST invoices with CGST/SGST or IGST |
| **Trust** | KYC, ratings, disputes, risk scoring, audit log |
| **Operate** | Admin panel with RBAC, scheduler, backups, health checks, one-click GitHub updates |

## Why weighment matters

Scrap is quoted on an estimate and settled on fact. ScrapX records the weighbridge slip — gross,
tare, net, deductions — and re-prices the order on the weight that actually arrived. Both sides see
the same numbers, and the platform commission follows the settled value, not the estimate.

## Documentation

| Document | For |
|---|---|
| [INSTALLATION.md](docs/INSTALLATION.md) | Installing and configuring |
| [USER_GUIDE.md](docs/USER_GUIDE.md) | Buyers and sellers |
| [ADMIN_GUIDE.md](docs/ADMIN_GUIDE.md) | Administrators |
| [FEATURES.md](docs/FEATURES.md) | Everything it does, including what is intentionally disabled |
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | How the code is organised |
| [DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md) | All 84 tables |
| [API.md](docs/API.md) | REST API |
| [SECURITY.md](docs/SECURITY.md) | Security model |
| [CRON.md](docs/CRON.md) | Scheduler setup |
| [UPDATE_SYSTEM.md](docs/UPDATE_SYSTEM.md) | GitHub updates, protected paths, rollback |
| [VERIFICATION_REPORT.md](docs/VERIFICATION_REPORT.md) | What was actually tested, and what was not |
| [ROADMAP.md](docs/ROADMAP.md) | What is deliberately left for later |
| [CHANGELOG.md](docs/CHANGELOG.md) | Release history |

## Command line (optional)

```bash
php cli.php migrate          # apply pending migrations
php cli.php migrate:status   # show applied and pending
php cli.php seed all         # run seeders
php cli.php cron             # run the scheduler
php cli.php health           # post-update health check
php cli.php backup full      # create a backup
php cli.php admin:password   # reset an administrator password
php cli.php routes           # list routes
```

Everything here is also available from the browser, because many shared hosts have no shell.

## Honest status

Some features are fully built but **inert until an operator supplies credentials** — SMS, WhatsApp,
web push, email delivery and online card payment. Each one states this on screen and records
undeliverable messages as *skipped*, rather than pretending to have sent them. See the table at the
end of [FEATURES.md](docs/FEATURES.md) and the *Not tested* section of
[VERIFICATION_REPORT.md](docs/VERIFICATION_REPORT.md).
