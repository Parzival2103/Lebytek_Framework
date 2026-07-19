# FPS — Publication manifest checklist (Framework)

**Date:** 2026-07-19  
**Branch:** `consolidation/framework-portal-separation`  
**Status:** local validation only — **NO PRODUCTION EXECUTION**

## Composer package purity

- [x] `composer.json` name = `lebytek/framework` — verified 2026-07-19
- [x] autoload **only** `Lebytek\Framework\` → `src/` (no `App\`) — `App\` autoload null/absent (Step 3)
- [x] `composer validate` passes — `./composer.json is valid` (2026-07-19)
- [x] `stripe/stripe-php` present (Payments platform module) — `"stripe/stripe-php": "^16.0"` in composer.json

## Test gates (record last line output)

| Filter | Required | Actual (2026-07-19) |
|--------|----------|---------------------|
| PackageAutoloadBoundary | 0 failed | 4 passed, 0 failed |
| PackagePaths | 0 failed | 6 passed, 0 failed |
| SkeletonPurity | 0 failed | 13 passed, 0 failed |
| PlatformSqlResolve | 0 failed | 2 passed, 0 failed |
| FrameworkRootNotPortal | 0 failed | 3 passed, 0 failed |
| Kernel | 0 failed | 46 passed, 0 failed |
| Payments | 0 failed | 17 passed, 0 failed |
| Full suite | 0 failed | 552 passed, 0 failed (post-CUTOVER; FpsPublicationReadiness 2/0 after Task 3) |
| FpsPublicationReadiness | 0 failed | 2 passed, 0 failed |

## Boundary

- [x] No `app/Domain/Marketing` in Framework root — `Test-Path` → False (2026-07-19)
- [x] No `database/schema/modules/marketing.sql` in Framework — `Test-Path` → False (2026-07-19)
- [x] `docs/PACKAGE-ROOT.md` forbids deploy — contains "no se despliega" / deploy = Portal
- [x] `docs/ARCHITECTURE-CONSUMER.md` present — verified 2026-07-19

## Explicit NO

- [x] No merge `feature/backoffice-api-integration` → `main` without user order
- [x] No VPS deploy from this plan
