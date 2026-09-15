# Security

## Passwords and sessions
- Passwords are hashed with `password_hash()` (bcrypt/Argon2 per your PHP build). Plaintext is never
  stored or logged.
- Sessions use `HttpOnly`, `SameSite=Lax` cookies, `Secure` over HTTPS, and the session ID is
  regenerated on login and on privilege change.
- Login is rate limited (default 20 attempts per 10 minutes per IP); every attempt, successful or
  not, is recorded in `login_history` with the IP.

## CSRF
Every state-changing request requires a token. A missing or forged token returns **419** and the
request never reaches the controller. AJAX requests send the token from the `csrf-token` meta tag.

## SQL injection
Every query uses PDO prepared statements with bound parameters. Where a table or column name must be
interpolated (ordering, dynamic `IN` lists), it passes through `safeTable()` / `safeColumn()`, which
allow only `[A-Za-z0-9_]` and throw otherwise. There is no string concatenation of user input into
SQL anywhere in the codebase.

## XSS
Output is escaped explicitly with `e()` at every interpolation point. The only unescaped output is
CMS content authored by staff, which is run through a tag whitelist on save (scripts, iframes and
event handlers are stripped). A Content-Security-Policy header is sent on every response.

## Uploads
- Extension **and** MIME type are both checked against a whitelist.
- Images are re-encoded through GD, which strips any embedded payload.
- Files are renamed to a random name; the original is kept only as a label.
- `uploads/.htaccess` turns the PHP engine off and denies any executable extension.
- Per-file and per-request size limits are enforced in PHP, not just in the browser.

## Secrets at rest
The GitHub token, SMTP password and payment gateway keys are encrypted with **AES-256-GCM** using a
per-installation key generated at install time and stored in `config.php` (which is outside version
control and protected from the web). Encrypted values are prefixed `enc:v1:`. A secret is never
returned to the browser: the settings form shows a placeholder and an unchanged field keeps the
stored value.

## Authorization
Every admin action checks a **permission**, not a role name, so a custom role gets exactly the
access you grant it. Ownership is checked on every record: a user requesting an order, auction,
listing, offer, dispute or invoice they are not party to gets **403**.

## Audit log
Every privileged action records the actor, IP, entity and before/after values. The log is readable
at Admin → Audit log and is filterable by action, user and date.

## Headers
Sent on every response:

```
Content-Security-Policy      X-Frame-Options: SAMEORIGIN
X-Content-Type-Options       Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy
```

HSTS is sent when the request is over HTTPS.

## Money
All monetary values are `DECIMAL` in the database (`15,2` for money, `15,4` for rates, `15,3` for
weights). Floating point is never used for money. Amounts are passed around as strings via `dec()`.

## Auction integrity
A bid is re-validated **on the server**, inside a transaction that holds a row lock on the auction
(`SELECT … FOR UPDATE`). Two simultaneous bids cannot both pass the "higher than current" check —
this is verified by an executed concurrency test in the verification report.

## Time
Everything is stored in UTC and displayed in Asia/Kolkata (configurable). Auction endings, OTP
expiry and rate limits are therefore immune to server timezone changes.

## Reporting a vulnerability
Email the address in Admin → Settings → General. Please do not open a public issue.
