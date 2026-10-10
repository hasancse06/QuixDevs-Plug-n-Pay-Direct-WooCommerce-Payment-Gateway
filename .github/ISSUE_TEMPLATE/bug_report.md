---
name: Bug report
about: Report a reproducible issue using synthetic data
---

Before filing, review the known limitations and blocked release status in the README. Use the private reporting guidance in [SECURITY.md](../../SECURITY.md) for a security vulnerability; do not disclose it publicly.

**Environment:** PHP, WordPress, WooCommerce and plugin versions; classic checkout or REST; whether HTTP was mocked.

**Reproduction:** minimal steps using a disposable environment and fake data.

**Expected / actual behavior:** include only sanitized errors.

**Checks:** never include PAN, CVV, client/verification keys, passwords, Authorization headers, customer data or complete processor replies. Do not repeat uncertain real charges to reproduce an issue.
