# Changelog

All notable changes are recorded here. Versions follow semantic versioning.

## [1.1.0] — 2026-10-07

### Changed
- Renamed the platform to **Scraptrading**. The name is read from the `site_name` setting
  everywhere — header, page titles, emails, SMS, invoices and the installer — so renaming the site
  in Admin → Settings renames it throughout, with nothing left hard-coded.
- Seeded CMS content (About, Terms & Conditions, KYC Policy, Commission Policy, How It Works) and
  the FAQ headlines now use a `{{site_name}}` placeholder resolved at display time, so those pages
  follow the setting instead of freezing the name they were installed with. Migration `0014`
  converts content already in the database.
- In-site links, form actions and internal redirects are root-relative instead of absolute. Under
  `Content-Security-Policy: form-action 'self'` an absolute action built from a configured URL that
  disagreed with the browsing host made the browser refuse the submission silently; a relative
  target cannot disagree. Emails, the sitemap, canonical and Open Graph tags stay absolute.
- The public navbar expands at `xl` rather than `lg`. Measured, the one-line header needs about
  1200px; between 992 and 1199px the search box was being squeezed to a few pixels, so below that
  everything stays in the drawer where it has full width.

### Added
- Marathi, Bengali, Tamil and Punjabi, alongside English, Hindi and Gujarati — seven languages,
  each a complete translation of all 74 interface strings.
- A language switcher inside the mobile navigation drawer. The existing one sits in the desktop
  utility bar, which is hidden on a phone, so there had been no way to change language there.
- `Default Language` in Admin → Settings is a dropdown of the installed languages, and the save
  handler rejects any other value, so the site cannot be left pointing at a language that has no
  translations.

### Fixed
- The route compiler matched a placeholder's closing brace with `[^}]+`, which cut the constraint
  in `/api/v1/pincode/{pincode:\d{6}}` short at `\d{6` and produced a pattern that matched
  nothing. The pincode lookup was unreachable, so address forms never filled in city and state
  from a PIN code. The compiler now counts brace depth.
- The language, profile-preference and admin-default language lists were three separate hard-coded
  copies, and the profile validation rule a fourth. All four now derive from `Lang::SUPPORTED`, so
  adding a language is one file plus one line.
- A long rupee figure has no spaces to break at, so a statistic card's value overflowed its card
  and pushed `/admin/invoices` sideways on a phone. It now wraps.
- The admin top bar did not tolerate a longer site name: the brand pushed the row 31px past a
  390px screen. The brand now shrinks and truncates while the menu and account controls keep
  their size.

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
