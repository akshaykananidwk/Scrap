# Database schema

84 tables across 13 migrations. Every monetary column is `DECIMAL` — never `FLOAT`. All timestamps
are stored in UTC.

## Conventions

| Kind | Type |
|---|---|
| Money | `DECIMAL(15,2)` |
| Rates | `DECIMAL(15,4)` |
| Weights | `DECIMAL(15,3)` |
| Percentages | `DECIMAL(6,3)` |
| Timestamps | `DATETIME` (UTC); `DATETIME(3)` where sub-second ordering matters (bids, events) |
| Soft delete | `deleted_at DATETIME NULL` |
| Demo data | `is_demo TINYINT(1)` so it can be purged cleanly |

## 0001 — Identity
`users`, `roles`, `permissions`, `role_permissions`, `user_roles`, `login_history`,
`password_resets`, `otp_codes`, `api_tokens`, `rate_limits`, `settings`, `audit_logs`

Users hold `account_type` (buyer/seller/both), `status`, `kyc_status`, `risk_score`, and
`email_verified_at` / `mobile_verified_at`. OTP codes are stored **hashed** with an attempt counter.

## 0002 — Locations
`states` (with GST state codes), `cities`, `pincodes`

## 0003 — Business and KYC
`businesses`, `business_documents`, `kyc_verifications`, `business_follows`

Businesses carry GSTIN, PAN, registration number, verification flags, rating aggregates and
response-rate statistics.

## 0004 — Catalog
`categories` (self-referencing tree), `materials`, `material_grades`, `units`, `hsn_codes`

`units.kg_factor` is what makes weighbridge settlement work regardless of how a lot was quoted.

## 0005 — Listings
`listings`, `listing_images`, `listing_videos`, `listing_documents`, `listing_views`, `favorites`,
`saved_searches`

Listings store both the quoted price and derived `price_per_kg`, `price_per_mt`, `total_price` and
`estimated_weight_kg`, so search can sort across mixed units.

## 0006 — Requirements and RFQ
`wanted_requirements`, `requirement_documents`, `requirement_offers`,
`rfqs`, `rfq_items`, `rfq_quotes`, `rfq_quote_items`, `rfq_invites`

## 0007 — Auctions
`auctions`, `bids`, `auction_bidders`, `auction_events`, `auction_watchers`

`auctions` holds `starting_price`, `current_price`, `reserve_price`, `bid_increment`,
`extension_window_seconds`, `extension_duration_seconds`, `max_extensions`, `extension_count`,
`mask_bidders`, `requires_kyc`, `requires_approval`. `bids` uses `DATETIME(3)` so ordering is exact
under load.

## 0008 — Offers and chat
`offers` (self-referencing via `parent_offer_id` for counter-offers), `conversations`, `messages`,
`message_attachments`

## 0009 — Orders and logistics
`orders`, `order_items`, `order_status_history`, `weighments`, `transporters`, `vehicles`,
`deliveries`

`weighments` records expected, gross, tare and actual weight, the difference in kg and percent, the
rate per kg, deductions and the settled amount, plus the weighbridge name, slip number and images.

## 0010 — Finance
`payments`, `commissions`, `wallets`, `wallet_transactions`, `invoices`, `invoice_items`, `plans`,
`subscription_features`, `subscriptions`

`wallet_transactions` is append-only and stores `balance_before` and `balance_after` on every row,
so the ledger can always be reconciled against the stored balance.

## 0011 — Engagement
`reviews`, `dispute` tables, `notifications`, `notification_queue`, `market_rates`, `cms_pages`,
`faqs`, `email_templates`, `contact_messages`, `content_reports`, `fraud_flags`

## 0012 — System
`system_updates`, `system_update_files`, `system_backups`, `cron_jobs`, `cron_runs`

## 0013 — Deferred foreign keys
Cross-module constraints that can only be added once every table exists (orders ↔ auctions,
orders ↔ offers, orders ↔ RFQ quotes, and so on).

## Migrations

Each migration has a version, a description, `up()` and `down()`. All DDL is idempotent
(`CREATE TABLE IF NOT EXISTS`, `addForeignKeyIfMissing()`), so re-running is safe. Applied versions
are tracked in `migrations`.

```bash
php cli.php migrate          # apply pending
php cli.php migrate:status   # show applied and pending
```

Updates run pending migrations automatically; the admin health screen lists anything outstanding.
