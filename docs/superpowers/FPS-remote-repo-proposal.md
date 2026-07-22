# FPS — Remote repository proposal (Framework)

**Status:** PROPOSAL ONLY — requires explicit user approval before any remote operation.

## Current state

| Item | Value |
|------|-------|
| Existing remote | `https://github.com/Parzival2103/Lebytek_Framework` |
| Publication branch candidate | `consolidation/framework-portal-separation` |
| Package name | `lebytek/framework` |
| Tag policy | Do **not** overwrite `v1.0.0`; new semver only on user order |

## Proposed actions (NOT executed in Plan 08)

1. Open PR: `consolidation/framework-portal-separation` → `main` — **only if user explicitly orders merge**
2. Tag `v1.1.0` (or approved version) after PR merge + full CI green
3. Configure Composer VCS auth on VPS for private repo (document in Portal DEPLOY-VPS)

## Framework consumer contract after publish

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
],
"require": {
  "lebytek/framework": "^1.1"
}
```

Exact constraint follows user-approved branch/tag — do not invent tags in automation.

## Explicit prohibitions

- No `gh repo create` for Framework (already exists)
- No force-push to `main`
- No merge `feature/backoffice-api-integration` → `main` without explicit user chat order
