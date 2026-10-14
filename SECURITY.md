# Security policy

This is the existing public QuixDevs repository for the maintainer's production gateway. Community release approval and supported verification scope are recorded separately. See [the security review](docs/security.md) for known blockers and [release readiness](docs/github-release-readiness.md).

Do not open a public issue containing merchant credentials, shared/verification keys, full PAN, CVV, Authorization headers, customer exports or raw processor requests/responses. Use a private reporting channel agreed directly with the maintainer. The portfolio at https://hasan.online/ identifies the maintainer; no dedicated security email, response SLA or enabled GitHub private-reporting feature is invented here.

On the existing GitHub repository, the maintainer may enable private vulnerability reporting and then document its verified report link. This task did not change repository security settings. Provide synthetic reproduction details, affected code paths, versions and impact without real payment data. Never test a finding against live merchants or submit charges without explicit authorization.

The original embedded verification value is excluded from this copy and must be treated as compromised for future distribution. Authorized merchant rotation/configuration remains outside this task. The unchanged source copies contain sensitive material and must stay private.
