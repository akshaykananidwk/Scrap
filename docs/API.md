# REST API

Base URL: `https://yourdomain.com/api/v1`

All responses are JSON:

```json
{ "success": true, "data": { } }
{ "success": false, "error": "Message", "status": 422 }
```

## Authentication

```http
POST /api/v1/auth/login
Content-Type: application/json

{ "identifier": "9876543210", "password": "secret", "device_name": "mobile app" }
```

```json
{ "success": true, "data": { "token": "…", "token_type": "Bearer", "expires_at": "2026-12-14 08:05:00" } }
```

Send it on every authenticated request:

```http
Authorization: Bearer <token>
```

A missing or invalid token returns **401**. Tokens are stored hashed; the plaintext is shown once,
at creation. Users can create and revoke tokens from Dashboard → Security & API.

## Public endpoints (no token)

| Method | Path | Purpose |
|---|---|---|
| GET | `/` | API index |
| GET | `/health` | Liveness check |
| GET | `/categories` | Category tree |
| GET | `/categories/{id}/materials` | Materials in a category |
| GET | `/materials/{id}/grades` | Grades for a material |
| GET | `/units` | Units of measure |
| GET | `/states` | Indian states with GST codes |
| GET | `/states/{id}/cities` | Cities in a state |
| GET | `/cities/search?q=` | City lookup |
| GET | `/pincode/{pincode}` | Resolve a 6-digit pincode |
| GET | `/market-rates` | Published scrap rates |
| GET | `/listings` | Search listings |
| GET | `/listings/{id}` | One listing |
| GET | `/auctions` | Auctions |
| GET | `/auctions/{id}` | One auction |
| GET | `/auctions/{id}/state` | Live price, bid count, seconds remaining |
| GET | `/auctions/{id}/bids` | Bid history (respects masking) |
| GET | `/requirements` | Buyer requirements |
| GET | `/requirements/{id}` | One requirement |

## Authenticated endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/auth/me` | Current user and business |
| POST | `/auth/logout` | Revoke the current token |
| POST | `/auth/register` | Create an account |
| POST | `/auth/otp/send`, `/auth/otp/verify` | Mobile verification |
| POST | `/listings` | Create a listing |
| POST | `/listings/{id}/status` | Publish, pause, mark sold |
| POST | `/bids` | Place a bid |
| GET/POST | `/offers`, `/offers/{id}/accept`, `/offers/{id}/reject` | Offers |
| POST | `/requirements`, `/requirements/{id}/offers` | Requirements and supply offers |
| GET | `/orders`, `/orders/{id}` | Orders |
| POST | `/orders/{id}/status` | Advance an order |
| POST | `/orders/{id}/weighment` | Record a weighbridge reading |
| GET | `/users/me/stats` | Dashboard counters |
| GET | `/notifications` | Notifications |

## Placing a bid

```http
POST /api/v1/bids
Authorization: Bearer <token>

{ "auction_id": 12, "amount": "605000.00" }
```

Success returns the new state; failure returns a machine-readable error code:

`not_live`, `ended`, `not_started`, `own_auction`, `ineligible`, `bid_too_low`, `bid_too_high`,
`invalid_increment`, `duplicate_bid`, `rate_limited`, `max_bidders`

Every response — success or failure — includes the current auction `state`, so a client always has
fresh numbers even when its bid was rejected.

## Rate limits

Bidding is limited to 40 requests per minute, login to 20 per 10 minutes, OTP to 6 per hour.
Exceeding a limit returns **429**.

## Pagination

List endpoints accept `page` and `per_page` (max 100) and return `meta` with `total`, `page`,
`per_page` and `last_page`.
