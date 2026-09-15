# Verification Report

**Generated:** 2026-09-15
**Environment:** PHP 8.4.19 (cli + built-in server), MariaDB 10.11, Linux
**Scope:** Everything below was **actually executed** against a running instance with a real
database. Nothing in this report is asserted from reading the code. Where a feature could not be
exercised, it is listed under *Not tested* with the reason.

---

## 1. Static checks

| Check | Result |
|---|---|
| `php -l` across every PHP file (282 files) | **Pass** — 0 parse errors |
| View template parse check (`cli.php health`) | **Pass** — all 124 templates parse |
| Routes registered | 302 |

## 2. Installation

Installed from zero into an empty database (`scrapx_fresh`) using the non-interactive installer:

```
✓ Database connection      Connected to scrapx_fresh
✓ Write configuration      config.php created
✓ Create database tables   13 migrations applied (84 tables)
✓ Roles & permissions      10 roles seeded
✓ Platform settings        Default settings created
✓ Locations                36 states and major cities
✓ Scrap catalog            23 categories, 104 materials
✓ Pages, templates & plans CMS, FAQs, notification templates, cron jobs
✓ Administrator account    fresh@scrapx.test
✓ Site configuration       Site name, contact and scheduler key set
✓ Demo data                6 users, 12 listings, 3 auctions, 14 bids, 4 requirements, 1488 market rates
✓ Lock installer           storage/installed.lock created
Installation complete.
```

The freshly installed site was then served and every public page returned HTTP 200; the new
administrator signed in and reached the admin panel. `/install` returns **403** once the lock file
exists, so the installer cannot be replayed.

**Post-install health check:** 23 of 24 checks pass. The single failure is
`Scheduler — has never run`, which is correct and expected on a brand-new install; it tells the
operator to configure cron.

## 3. Page sweep

Every route below was requested against a live server with a real session. **0 non-200 responses,
0 errors written to the error log.**

| Group | Pages | Result |
|---|---|---|
| Public pages (incl. sitemap, robots, manifest) | 21 | all 200 |
| Seller/buyer dashboard | 26 | all 200 |
| Admin panel | 35 | all 200 |
| Detail pages (orders, offers, disputes, chat, auctions, invoices, listing editor tabs) | 12 | all 200 |
| Admin detail pages (order, user, KYC, dispute, auction, invoice, settings groups) | 13 | all 200 |
| Public detail pages (listing, business, requirement, auction, RFQ, category, material, rate) | 8 | all 200 |

## 4. Trade flows executed end to end

### 4.1 Registration, OTP and KYC
- Registered two buyers and one seller through the real form. Session established, dashboard reachable.
- `/dashboard/listings/create` correctly returned **302** for an unverified mobile, then **200**
  after OTP verification — the `verified` middleware works.
- OTP is stored **hashed** (`code_hash`); with no SMS provider configured the code is written to the
  log with a masked destination (`98••••001`) rather than being silently lost.
- Uploaded 4 KYC documents, submitted for review, approved from the admin panel.
  Result: `kyc_verifications.status = verified`, `users.kyc_status = verified`,
  `businesses.kyc_verified = 1`, `gst_verified = 1`.

### 4.2 Listing and sell wizard
Posted a listing through the real form. Derived pricing was computed correctly:

| Input | Stored |
|---|---|
| 20 MT @ ₹32,000/MT | `estimated_weight_kg = 20000.000` |
| | `price_per_kg = 32.0000` |
| | `price_per_mt = 32000.00` |
| | `total_price = 640000.00` |
| | `status = pending` (approval required) |

Admin approval moved it to `active`.

### 4.3 Auction engine — validation
Every rule below was triggered against a live auction and returned the correct error code:

| Attempt | Result |
|---|---|
| ₹6,01,000 (not a multiple of the ₹5,000 increment) | `invalid_increment` — "Try ₹6,05,000.00" |
| ₹6,05,000 (valid) | accepted |
| ₹6,05,000 again (same bidder, same amount) | `duplicate_bid` |
| Seller bidding on their own auction | `own_auction` |

Money is rendered with Indian lakh grouping (₹6,05,000.00).

### 4.4 Auction engine — concurrency (the critical test)
Two different users posted a bid of **₹6,10,000 simultaneously** (parallel HTTP requests):

```
buyer1: ok=True   "Your bid has been placed."
buyer2: ok=False  bid_too_low — "Your bid must be at least ₹6,15,000.00"
WINNING ROWS: 1
```

The row-level lock (`SELECT … FOR UPDATE`) held: one bid won, the other was re-validated against
the new price and rejected. Exactly one `winning` row existed afterwards.

### 4.5 Auction engine — anti-sniping
A bid placed with **110 seconds left** inside a 120-second window extended the auction:

```
before: ends_at 08:03:00 | extension_count 0
after : ends_at 08:05:00 | extension_count 1
event : extended — "Extended to 2026-09-15 08:05:00"
```

### 4.6 Auction close and award
The scheduler closed the auction (`auction_lifecycle — 1 closed`). Because the top bid was below
the reserve the auction closed as **unsold**, which is correct. The seller then awarded it at their
discretion and an order was created.

### 4.7 Order lifecycle
Walked the order through `confirmed → processing → ready_for_pickup → picked_up → delivered →
payment_pending → paid → completed`. Every transition was accepted by the state machine and written
to `order_status_history`.

### 4.8 Weighment settlement (the core financial rule)
Recorded a weighbridge reading against a 20 MT order sold for ₹6,15,000:

| Field | Value | Hand-check |
|---|---|---|
| Expected | 20,000.000 kg | 20 MT |
| Actual | 19,450.000 kg | from the slip |
| Difference | −550.000 kg (−2.750%) | ✓ |
| Rate per kg | ₹30.7500 | 615000 ÷ 20000 ✓ |
| Deduction | ₹8,000.00 | moisture/mud |
| **Settled** | **₹5,90,087.50** | 19450 × 30.75 − 8000 ✓ |

The counterparty accepted the weighment (`status = accepted`), and the order re-priced to the
settled figure:

```
subtotal     590087.50
gst_amount   106215.75   (18%)
final_amount 696303.25
```

### 4.9 Commission
Booked automatically off the **settled** value, not the estimate:

| Fee | Base | Rate | Amount | GST | Total |
|---|---|---|---|---|---|
| sale_commission | ₹5,90,087.50 | 1.000% | ₹5,900.88 | ₹1,062.16 | ₹6,963.03 |
| auction_fee | ₹5,90,087.50 | 0.500% | ₹2,950.44 | ₹531.08 | ₹3,481.52 |

### 4.10 Payment
Buyer recorded a ₹6,96,303.25 RTGS payment with a UTR; the seller confirmed receipt.
Order moved to `payment_status = paid`, `amount_paid = 696303.25`.

### 4.11 GST invoice — both branches verified

**Inter-state** (seller state code 37, buyer 24):

```
INV/2026-27/000001   subtotal 590087.50
CGST 0.00  SGST 0.00  IGST 106215.75
round_off -0.25      total 696303.00
"Six Lakh Ninety Six Thousand Three Hundred Three Rupees Only"
```

**Intra-state** (both parties GSTIN `24…`):

```
INV/2026-27/000002   subtotal 315000.00
CGST 28350.00  SGST 28350.00  IGST 0.00
total 371700.00
"Three Lakh Seventy One Thousand Seven Hundred Rupees Only"
```

Invoice numbering is sequential per Indian financial year; the amount in words uses lakh/crore
grouping. Both the screen and print views render.

### 4.12 Review
Buyer rated the completed order 5★ with per-criterion scores. The review published and the seller's
business profile updated to `rating_avg 5.00, rating_count 1, completed_orders 1`.

### 4.13 Offers and counter-offers
Buyer offered ₹30,000 → seller countered ₹31,500 → buyer accepted. The counter was linked to the
parent (`parent_offer_id`), the original moved to `countered`, and an order was created:
10 MT @ ₹31,500 = ₹3,15,000 + 18% GST = **₹3,71,700**.

### 4.14 Buyer requirements
Posted a requirement (50 MT monthly, target ₹30,000). A seller submitted a supply offer at
₹30,500 which the buyer accepted — order created for **₹17,99,500** (₹15,25,000 + GST) and the
requirement marked `fulfilled`.

### 4.15 RFQ
Created a 3-line RFQ with an invited supplier, the supplier quoted all three lines, and the buyer
awarded it:

| Line | Qty | Rate | Amount |
|---|---|---|---|
| MS Heavy Melting Scrap | 100 MT | ₹31,000 | ₹31,00,000 |
| Aluminium Utensil Scrap | 25 MT | ₹1,48,000 | ₹37,00,000 |
| Copper Armature Scrap | 5 MT | ₹6,20,000 | ₹31,00,000 |
| **Quote total** | | | **₹99,00,000 + ₹17,82,000 GST = ₹1,16,82,000** |

The awarded order totals **exactly ₹1,16,82,000.00**.

### 4.16 Chat
Started a conversation, sent messages from both sides, and polled for new messages over AJAX.
Unread counters and `last_message_preview` updated correctly.

### 4.17 Disputes
Buyer raised a weight-mismatch dispute claiming ₹25,000. An administrator resolved it with a
settlement of ₹18,000 and priority `high`. Both the party view and the staff view (which shows
internal notes) render.

## 5. Authorization

| Test | Result |
|---|---|
| Losing bidder opening an order they are not party to | **403** |
| Unauthenticated request to a private API endpoint | **401** |
| API request with an invalid bearer token | **401** |
| Seller bidding on their own auction | rejected (`own_auction`) |
| Weighment accepted by the same person who recorded it | rejected |

## 6. Security

| Test | Result |
|---|---|
| POST with no CSRF token | **419** |
| POST with a forged CSRF token | **419** |
| `/buy?q=' OR 1=1--` | 200, no SQL executed, `users` table intact |
| `/buy?category_id=1;DROP TABLE users` | 200, table intact |
| Reflected XSS `<script>alert(1)</script>` | 0 raw script tags in the response |
| Login brute force (limit 20 / 600s) | attempts 1–20 processed, 21+ returned **429** |
| Direct request to `/config.php`, `/.env`, `/composer.json`, `/.git/config` | **404** |
| Security headers | CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy all present |
| Maintenance mode | public **503**, administrator retains access |

### GitHub token confidentiality
A token was saved and then searched for everywhere:

| Location | Contains the token? |
|---|---|
| Database (`settings.value`) | No — stored as `enc:v1:…` (AES-256-GCM) |
| Rendered admin HTML | No — shows "Stored — leave blank to keep it" |
| API responses | No |
| Log files | No |

## 7. Subsystems

| Subsystem | Test | Result |
|---|---|---|
| Scheduler | `php cli.php cron` | All 10 jobs ran; auction closed on time |
| Notification queue | drained with no provider configured | **10 skipped (no provider)** with reason `php_mail is not configured` — not falsely reported as sent or failed |
| Backup | `php cli.php backup database` | 86 KB archive, 84 tables |
| Restore | round-trip | inserted a row, restored, row gone; `status = restored` |
| CSV export | 6 datasets | all download with correct headers |
| CSV import | dry run + real run | dry run wrote nothing; real run imported the valid row and skipped the invalid one |
| Update check (unconfigured) | check with no repo saved | reports "Save your GitHub owner, repository and branch first" |
| REST API | login → token → 5 authenticated endpoints | all 200; bad/missing token 401 |
| PWA / SEO | manifest, service worker, sitemap, robots, offline | all 200 and well-formed |

## 8. Defects found during testing and fixed

These were found **by running the software**, not by reading it.

1. **Weighment settlement was silently reverted.** `WeighmentService::record()` wrote the settled
   amount and then called `OrderService::recalculate()`, which rebuilt the subtotal from
   `quantity × rate` — undoing the settlement. The headline feature did not work.
   *Fixed:* `recalculate()` now uses the latest non-rejected weighment's settled amount as the
   taxable base, so settlement survives every later recalculation (charges edited, freight added).

2. **Category filter crashed the marketplace.** The filter bound the same placeholder set twice
   (`l.category_id IN (…) OR l.subcategory_id IN (…)`), which native prepared statements reject
   ("Invalid parameter number"). Any filter by category returned a 500.
   *Fixed:* two distinct placeholder sets.

3. **Messages page crashed whenever a conversation existed.** `ChatService::conversations()`
   passed a dotted column (`u.id`) to `compileWhere()`, whose identifier guard rejects dots.
   *Fixed:* unqualified column, alias applied in the SQL.

4. **Offer thread crashed.** The recursive counter-offer query produced an ambiguous `id` column
   across a join. *Fixed:* qualified the column.

5. **Unconfigured email was reported as 10 failures.** `MailProvider::isConfigured()` returned true
   whenever `function_exists('mail')`, which is always true — so mail was queued on a host with no
   MTA and then failed. *Fixed:* the provider now checks that a real transport exists
   (`sendmail_path` resolves to an executable, or Windows SMTP), so messages are honestly marked
   **skipped**.

6. **Restore button could never work.** `BackupController::restore()` requires a typed `RESTORE`
   confirmation, but the admin view posted without it. *Fixed:* the view now collects the typed
   confirmation (and an optional "restore files" choice) in a modal.

7. **CSV export and import were broken on PHP 8.4.** `fgetcsv()`/`fputcsv()` without the explicit
   `$escape` argument is deprecated and raised an error. *Fixed:* all calls pass `',', '"', ''`.

8. **RFQ awards were off by one paisa.** A multi-line quote was collapsed into a blended per-unit
   rate and multiplied back, so ₹1,16,82,000.00 became ₹1,16,82,000.01.
   *Fixed:* the order is priced as a lot at the quote's exact total.

9. **Layout section collision.** `View::render()` overwrote a template's own `content` section with
   the (empty) raw output, blanking every page that used `View::section('content')`.
   *Fixed:* a template that declares its own content section wins.

10. **Several views referenced columns that do not exist** (`unit_price`, `cgst_amount`,
    `offered_quantity`, `resolved_amount`, `mobile_verified`, `is_popular`, `type` on commissions
    and messages, and others). Each was corrected against the real schema and the page re-tested.

11. **Maintenance mode locked the administrator out.** `/login` was served the 503 page, and `/admin`
    redirects to `/login`, so nobody could reach the switch that turns maintenance off.
    *Fixed:* sign-in, sign-out, the admin panel, cron and the installer stay reachable.

12. **A PHP deprecation notice took the whole site down.** The error handler converted every notice
    into an exception, so `curl_close()` — deprecated in PHP 8.5, and harmless — produced a 500 on
    the update screen. *Fixed:* the redundant `curl_close()` calls were removed, and deprecations are
    now logged as warnings instead of thrown. Real errors still produce a 500.

13. **Extra protected paths were silently discarded.** The update settings form posted
    `update_protected_paths` and the controller read `protected_paths`, so anything an operator added
    to the never-overwrite list was wiped on save. *Fixed:* the controller reads the posted name.

14. **The admin menu was unusable on a phone.** The off-canvas drawer shared a CSS class with the
    desktop sidebar rail and inherited its 240px width, sticky position and clipped height, so
    tapping the menu opened a half-drawn panel over an empty page. *Fixed:* sizing belongs to the
    desktop rail alone; the drawer now measures a full 390×844 with all 35 items reachable.

15. **`.min-w-0` was used 26 times and defined nowhere.** Bootstrap ships no such utility. A flex item
    defaults to `min-width: auto`, so every `text-truncate` inside a flex row silently failed and one
    long title widened the whole page. *Fixed:* defined once in `app.css`.

16. **Asset URLs could be blocked by the site's own Content-Security-Policy.** Links were built from
    the configured site URL rather than the host being browsed, so any mismatch (http vs https, www
    vs bare, a proxy terminating TLS) made the browser refuse the site's own CSS and JS.
    *Fixed:* `base_url()` follows the request host when it resolves to the configured host — a forged
    `Host` header still cannot rewrite links in a password-reset email — and assets are emitted
    relative to the install path. Open Graph images stay absolute.

17. **Two auction countdowns never moved.** Those templates invented `data-countdown` /
    `data-ends-at` attributes while `app.js` drives `.js-countdown-box` with `data-seconds`.
    *Fixed:* markup aligned; both now tick.

18. **Long rupee figures pushed pages sideways on a phone.** A statistic card's value has no spaces
    to break at, so ₹1,23,45,678.00 is wider than a third of a 390px screen.
    *Fixed:* the value wraps inside its card.

19. **The pincode lookup API could never be reached.** The router matched a placeholder's closing
    brace with `[^}]+`, so the constraint in `/pincode/{pincode:\d{6}}` was cut short at `\d{6` and
    the route compiled to a pattern that matches nothing. Address forms therefore never auto-filled
    city and state from a PIN code. *Fixed:* the router counts brace depth. Verified end to end:
    CSV template → admin import → `/api/v1/pincode/361001` → `{"city":"Jamnagar","state":"Gujarat"}`.

## 9. Not tested

Stated plainly rather than implied:

- **SMS, WhatsApp and web-push delivery** — no provider credentials exist in this environment. The
  queue, templates, retry/backoff and the provider interface were exercised; actual delivery was
  not. Messages are recorded as `skipped` with a reason.
- **Razorpay online payment** — no API keys. The gateway interface, the "not configured" reporting
  path and manual payment recording were tested; a live charge was not.
- **Applying a GitHub update** — no repository is connected to this installation. The configuration
  screen, the unconfigured-state reporting, token encryption and the protected-path list were
  tested; a real `check → download → apply → migrate → health-check → rollback` cycle was not run
  end to end.
- **Email delivery** — no MTA on this host. Template rendering and queueing were tested; SMTP
  transmission was not.
- **Real Apache/`.htaccess` behaviour** — testing used PHP's built-in server, which ignores
  `.htaccess`. The rules are present and were reviewed, but their enforcement was not observed.
- **Load and stress behaviour** — concurrency was proven for the specific two-bid race that matters
  most; no sustained load test was performed.
- **Real devices** — the browser testing below ran in headless Chromium at phone and desktop sizes.
  No physical phone, and no Safari or Firefox, was used.

## 9a. Browser testing

This section was originally part of *Not tested*. It now records what was actually run.

Every page was loaded in headless Chromium at 390×844 (phone) and 1280×844 (desktop) — 67 URLs at
each size across the public site, the member dashboard and the admin panel, signed in where the page
requires it. Each load was checked for its HTTP status, for uncaught JavaScript errors, for console
errors, and for horizontal overflow (a page wider than the screen, which is what makes a site feel
broken on a phone).

Final result: **0 problems across all 134 page loads** — no failed navigation, no JavaScript error,
no console error, no horizontal overflow.

Interactive behaviour was driven rather than inspected:

| Behaviour | Result |
|---|---|
| Sell wizard (9 steps) | Next advances to step 2, Back returns to step 1 |
| Auction countdown, public page | Ticking |
| Auction countdown, seller console | Ticking |
| Public mobile navigation drawer | Opens, 23 links reachable |
| Admin mobile menu | Full-screen overlay at 0,0 390×844, 35 items, last item reachable |
| Fixed bottom bar vs page content | Body padding 68px ≥ bar height 65px, nothing covered |
| Pincode → city/state auto-fill | CSV import → `/api/v1/pincode/361001` → `{"city":"Jamnagar","state":"Gujarat"}` |

Seven faults were found this way and fixed; they are listed in §8.

## 10. Summary

| Metric | Value |
|---|---|
| PHP files | 282 |
| Lines of PHP | 46,578 |
| Database tables | 84 |
| Routes | 302 |
| View templates | 124 |
| Parse errors | 0 |
| Pages returning non-200 in the final sweep | 0 |
| Errors logged during the final sweep | 0 |
| Defects found by testing and fixed | 19 |
| Browser page loads checked (67 URLs × 2 screen sizes) | 134 |
| Browser problems remaining | 0 |
