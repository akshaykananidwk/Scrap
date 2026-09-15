# Features

## Marketplace
- Dynamic scrap categories and subcategories, managed entirely from the admin panel
- 104 seeded materials with grades, default units, HSN codes and GST rates
- Listings with photos, videos, documents, condition, source and trade terms
- Nine-step sell wizard with per-step validation and a full non-JavaScript fallback
- Search and filter by category, material, grade, quantity, price band, condition, state, city,
  pincode and freshness; sort by price, quantity, views, ending soon
- Featured and sponsored placement
- Saved searches and a watchlist

## Auctions
- Forward (price up) and reverse (price down) auctions
- Live bidding over AJAX polling, with a countdown that survives a page refresh
- **Every bid re-validated on the server inside a row-level lock** — two simultaneous bids can
  never both win
- Anti-sniping: a bid inside the closing window extends the auction, with a configurable window,
  extension length and maximum number of extensions
- Reserve price, hidden from bidders; only "met / not met" is shown
- Bidder eligibility: KYC-only, seller-approved, deposit required, maximum bidders
- Optional bidder masking (Bidder #3) with the seller always seeing real identities
- Full bid history and an event log with IP addresses for fraud review

## Buying
- Buyer requirements ("wanted") with quantity ranges, target price, frequency and delivery needs
- Automatic notification of sellers who deal in that material
- RFQ workflow: multi-line requests, invited or public, side-by-side quote comparison, shortlisting
  and award
- Offers and counter-offers with the complete negotiation thread preserved

## Orders
- Thirteen-state lifecycle from pending to completed, with an enforced state machine
- **Settlement on the actual weighbridge weight**, not the estimate — gross, tare and net weight,
  slip number, weighbridge name, photos, and deductions with reasons
- The counterparty must accept a weighment before the order re-prices
- Transport: transporters, vehicles, drivers, LR numbers, e-way bills, freight and delivery status
- Payments: UPI, NEFT, RTGS, IMPS, cash, cheque, credit terms, wallet, and online (when configured)
- GST invoices with HSN codes, CGST/SGST or IGST worked out from state codes, round-off and the
  amount in words with lakh/crore grouping
- Sequential invoice numbering per Indian financial year
- Disputes with evidence, internal staff notes and a settlement amount
- Two-sided reviews with per-criterion ratings and a one-time public response

## Trust
- KYC with GST certificate, PAN, registration, address and bank proof
- GSTIN checksum validated offline against the GSTN algorithm
- Risk scoring with ten detection rules (duplicate mobile, shared GSTIN, bidding velocity,
  wins-but-never-pays, and others)
- Content reporting and moderation

## Admin
- Role-based access control: 10 roles, 29 permissions, every action permission-checked
- Full CRUD for categories, materials, grades, units, HSN codes, market rates, CMS pages and FAQs
- Moderation queues for listings, KYC, disputes, reports and reviews
- Finance: payments, commission ledger, invoices, wallets with reconciliation
- System: health check, audit log, error logs, scheduler, backups, updates, import/export

## Platform
- Installer wizard at `/install` — no SSH, no Composer, no build step
- Migration system with idempotent DDL and rollback
- GitHub-based updates with protected paths, automatic backup, automatic rollback and health check
- Scheduler with a web fallback for hosts without cron
- Backups with restore, retention and a pre-update snapshot
- Wallet with an immutable, reconcilable ledger
- Notification engine across web, email, SMS, WhatsApp and push behind one provider interface
- REST API with bearer tokens
- PWA with a conservative service worker that never caches prices
- Three languages (English, Hindi, Gujarati) with automatic fallback
- Mobile-first responsive UI with a bottom navigation bar

## Intentionally disabled until configured

These are fully built but inert until an operator supplies credentials. Each one says so on screen
rather than pretending to work:

| Feature | Needs | Behaviour until then |
|---|---|---|
| SMS delivery | Gateway credentials | Messages queued and marked *skipped* |
| WhatsApp delivery | Business API credentials | Messages queued and marked *skipped* |
| Web push | VAPID keys | Not offered in the UI |
| Email delivery | SMTP settings (or a host MTA) | Messages queued and marked *skipped* |
| Online payment | Razorpay keys | Manual payment recording only; the option is hidden |
| GitHub updates | Repository and token | Update screen explains what to enter |
| Wallet | Enabled in settings | Screen states it is off; no balances tracked |
