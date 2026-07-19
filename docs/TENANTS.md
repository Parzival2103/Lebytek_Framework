# Tenants vs Framework vs Portal

| Name | Repo | Role |
|------|------|------|
| Lebytek Framework | `Lebytek_Framework` | Reusable platform package — **not a deployable site** |
| Lebytek Portal | `Lebytek_Portal` | Company tenant (lebytek.com / waapi) |
| Customer X | new repo from `skeleton/` | Other client app |

Rule: **never start a customer project by cloning `Lebytek_Portal`.**

Local maintainer: Portal/skeleton `repositories` path → `../Lebytek_Framework`.
Production: VCS + semver/branch + committed `composer.lock` (see Plan 08 runbook).

Marketing (CRM, leads, memberships, landing) lives **only** in Portal until a second tenant justifies `lebytek/module-marketing` (YAGNI).
