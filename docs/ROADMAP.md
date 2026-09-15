# Roadmap

What exists today is listed in FEATURES.md. This is what is deliberately left for later, and why.

## Next

**Live WebSocket bidding.** Auctions currently poll over AJAX, which is correct and works on shared
hosting where no long-running process is allowed. The bid pipeline is already isolated behind a
service, so swapping the transport does not touch the bidding logic.

**Provider implementations.** The SMS, WhatsApp and push interfaces are defined and the queue drives
them; concrete providers (MSG91, Gupshup, Twilio, Meta Business API, VAPID push) are configuration
plus a class each.

**Escrow.** The wallet ledger, holds and reconciliation exist. Full escrow — funds held between
weighment acceptance and delivery confirmation — is a policy and compliance question before it is a
code one.

**e-Way bill API.** E-way bill numbers are recorded today. Generating them through the government
API requires GSP credentials.

## Later

- Seller storefront pages with a custom domain
- Multi-warehouse inventory for aggregators
- Recurring contracts (monthly supply) with scheduled order generation
- Transporter marketplace with freight bidding
- Buyer credit scoring and terms
- Native mobile apps on the existing REST API
- Regional language coverage beyond Hindi and Gujarati
- Bulk listing upload for large yards
- Advanced analytics: price forecasting from the rate history already collected

## Explicitly not planned

- Cryptocurrency payments
- Cross-border trade (customs, LC and INCOTERM handling is a different product)
- Consumer-to-consumer scrap sales — this is a B2B platform
