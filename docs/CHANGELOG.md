# Changelog

All notable changes are recorded here. Versions follow semantic versioning.

## [1.0.0] — 2026-09-15

First production release.

### Added
- Composer-free MVC framework: router, container-free kernel, PDO wrapper, view engine, validator,
  session, auth, uploader, crypto, logger, migrator
- Browser installer at `/install` — six steps, no SSH, no build step
- 13 migrations creating 84 tables; 6 seeders (roles, settings, locations, catalog, content, demo)
- Registration, login, OTP mobile verification, password reset, API tokens
- Business profiles with KYC (documents, GSTIN checksum validation, admin review)
- Listings with a nine-step sell wizard, images, videos, documents
- Marketplace search with filters across category, material, grade, price, quantity, condition and
  location; saved searches and watchlist
- Forward and reverse auctions with server-validated bidding under row-level locking,
  anti-sniping auto-extension, reserve price, bidder eligibility and masking
- Buyer requirements with automatic seller matching
- RFQ workflow: multi-line requests, invitations, quote comparison, award
- Offers and counter-offers with the full negotiation thread
- Orders with a 13-state lifecycle
- Weighment-based settlement on actual weighbridge weight
- Transport, vehicles, drivers, LR numbers, e-way bill fields and delivery tracking
- Payments (UPI, NEFT, RTGS, IMPS, cash, cheque, credit, wallet) with seller confirmation
- Platform commission engine with GST
- Wallet with an immutable, reconcilable ledger
- GST invoices with HSN codes, CGST/SGST/IGST split by state code, round-off and amount in words
- Two-sided reviews with per-criterion ratings
- Disputes with evidence, internal notes and settlement
- Risk scoring with ten detection rules; content reporting and moderation
- Admin panel with RBAC (10 roles, 29 permissions), catalog management, market rates, CMS, FAQs
- Notification engine (web, email, SMS, WhatsApp, push) behind one provider interface
- Scheduler with 10 jobs and a web fallback for hosts without cron
- Backups with restore, retention and pre-update snapshots
- GitHub-based update system with protected paths, automatic backup, automatic rollback and a
  post-update health check
- REST API with bearer tokens
- PWA with a conservative service worker
- English, Hindi and Gujarati language files
- CSV import and export

### Security
- AES-256-GCM encryption for secrets at rest
- CSRF protection on every state-changing request
- PDO prepared statements throughout, with identifier whitelisting
- Upload hardening: MIME and extension checks, image re-encoding, PHP disabled in `uploads/`
- Rate limiting on login, OTP and bidding
- Security headers including CSP
- Full audit log
