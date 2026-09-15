# Administrator guide

## Dashboard
Live counters for users, listings, auctions, orders, GMV, commission revenue, pending KYC and open
disputes, with 30-day trend charts. Operational alerts appear at the top and are real state, not
decoration: the scheduler being stopped, a notification backlog, open risk flags, or an available
update.

## Daily work

| Queue | Where |
|---|---|
| Listings awaiting approval | Admin → Listings (filter: pending) |
| KYC submissions | Admin → KYC |
| Open disputes | Admin → Disputes |
| Content reports | Admin → Reports |
| Flagged reviews | Admin → Reviews |
| Risk flags | Admin → Risk & fraud |
| Contact messages | Admin → Contact messages |

## Users
Search by name, mobile, email or business; filter by status, role, KYC state and location. On a user
you can see their listings, orders, sign-in history, audit trail, risk flags and KYC file, change
their roles, suspend or block them, reset their password and re-evaluate their risk score.

## KYC
Open a submission to see every document. Approve or reject each document individually with a reason,
then approve or reject the whole file. Approving sets the verified badge, marks the business
GST-verified and notifies the user. The GSTIN checksum is validated offline; live government API
lookup only happens if you configure a provider — the platform never fabricates a verification.

## Catalog
Categories, materials, grades, units and HSN codes are all editable — no code changes and no
deployment. The `kg_factor` on a unit is what lets weighbridge settlement work across mixed units,
so set it carefully.

## Finance
- **Payments** — confirm manually recorded payments
- **Commission ledger** — every fee with its base, percentage, GST and status; filter by period and
  mark entries invoiced, paid, waived or cancelled
- **Invoices** — every GST invoice raised on the platform
- **Wallets** — balances with a reconciliation check that proves each stored balance matches the sum
  of its ledger entries; manual adjustments are append-only and audited

## Settings
Sixteen groups. Two things to know:

- **Secrets** (SMTP password, payment keys, GitHub token) are encrypted and never shown again. Leave
  a secret field untouched to keep the stored value.
- **Providers** report their real state. If SMS is not configured, the SMS settings page says so and
  the queue marks those messages *skipped* — it does not pretend to send them.

## Roles and permissions
Every admin action checks a permission, not a role name. Tick exactly what a role should be able to
do; a staff member gets that and nothing more.

## System health
PHP version, extensions, configuration, database, migrations, writable folders, hardened uploads,
registered routes, template parsing, scheduler and the notification queue. Check it after every
update and after any hosting change.

## Scheduler
See CRON.md. If it is not running, auctions do not close on time — the health screen warns you.

## Backups
Create a full, database-only or files-only backup at any time; enable scheduled backups; set how
many to keep. Restoring requires typing **RESTORE**, puts the site into maintenance mode and
overwrites current data — so it asks deliberately. A backup is taken automatically before every
update.

## Updates
See UPDATE_SYSTEM.md. Connect your GitHub repository once and you can update from the browser, with
protected paths, an automatic pre-update backup, automatic rollback on failure and a health check
afterwards.

## Import and export
Export any of 13 datasets to CSV (streamed, so large tables do not exhaust memory). Import
categories, materials, grades, market rates, cities and pincodes — always run the **dry run** first:
it validates and reports without writing anything.

Demo data is tagged, so Purge demo data removes it cleanly without touching anything real.

## Audit log
Every privileged action with the actor, IP, entity and before/after values. This is the first place
to look when someone asks "who changed this?".
