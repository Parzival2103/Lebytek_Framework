# SDD Progress — FPS

Worktree: `.worktrees/framework-portal-separation`
Branch: `consolidation/framework-portal-separation`
Base: `2c71d3f7f75eea2ee746bc271b9a3907dbbdd9cd`

## Plan 00 — Inventario y rama consolidación (2026-07-17)

Plan: `docs/superpowers/plans/2026-07-17-fps-00-inventory-consolidation-branch.md`

Task 1: complete (commits 2c71d3f..438b436, review clean; minor: leftover template phrase in FPS-git-baseline.md)
Task 2: complete (commits 438b436..a192941, review clean; minor: UTF-8 BOM on delta paths file)
Task 3: complete (commits a192941..1db4ed6, review clean; minor: BOM + git add -f for gitignored SDD path)

- [x] Rama `consolidation/framework-portal-separation` desde `main`
- [x] `FPS-git-baseline.md` con SHAs
- [x] `BOUNDARY-framework-vs-portal-fps.md` + delta paths
- [x] Sin cambios runtime
- Gate: N/A (docs only)
- Final review: With fixes → fixed in 9db3777 (BOM + baseline wording). Plan 00 complete.
- Siguiente: Plan 01 generic payments

## Plan 01 — Generic Payments Stripe (2026-07-17)

Plan: `docs/superpowers/plans/2026-07-17-fps-01-generic-payments-stripe.md`
Source SHA: dad059056d26b6eb527815f85cf71ecd507a57fe
Baseline before Task 1: 9db377790f7f3ee4d4b9e4730eaad47383709e17
Suite baseline: 615 passed, 6 failed (documented in FPS-git-baseline.md)

Task 1: complete (commits 9db3777..a6eaa25, review clean; minors: CheckoutRequest invalid-mode untested, CheckoutSession/PaymentFailed gaps, Money allows negative — brief-inherited)
Task 2: complete (commits a6eaa25..6f9584f, review clean; minors: prefer-stable flip, tryClaim reflection-only, driver() unknown quirk)
Task 3: complete (commits 6f9584f..9287785, review clean; minors: progress.md hygiene, static-only DI gate coverage)

- [x] Domain + Application + Infrastructure in src/
- [x] pay_events schema + stripe/stripe-php
- [x] Config OFF; no app/** stripe wiring
- Gate: `php tests/run.php Payments` → 0 failed (17 passed)
- Whole-branch: 9db3777..9287785 — Ready for Plan 02 (not merge to main)
- Full suite: 632 passed, 6 failed (baseline fail count unchanged; was 615/6)
- Important deferred (pre-enablement / Plan 05): StripeGateway non-mxn webhook zeros amount but still CheckoutCompleted — prefer Ignored/fail-closed before live webhooks
- Minors open: invalid-mode test, CheckoutSession/PaymentFailed fixtures, Money negative, prefer-stable flip, driver() unknown
- Accepted Plan 01: tryClaim reflection-only; static DI config tests
- Siguiente: Plan 02 platform stabilization

## Plan 02 — Platform stabilization (2026-07-17)

Task 1: complete (commits 9287785..00d0f4c, review clean; minor: brief said don't touch get/all/cargarCache — instance migration necessary on this branch)
Task 2: complete (commits 00d0f4c..1b868f8, review clean after provenance fix; minor: Contexto wording vs provenance)
Task 3: complete (commits 1b868f8..a46949c, review clean; minors: progress hygiene, Marketing assert_false shim redundant)

- [x] ConfiguracionService cache fix + characterization tests
- [x] LEGACY seeds/migrations inventory (no deletion; provenance on consolidation)
- Gate ConfiguracionServiceCache: 0 failed (3 passed)
- Gate Payments: 0 failed (17 passed)
- Gate Kernel: 0 failed (15 passed; harness fix: `assert_false` in `tests/lib/microtest.php`)
- Gate Auth: 0 failed (48 passed)
- Gate full suite (diagnostic): 638 passed, 3 failed (baseline M_baseline=6; D2/D8 preexisting: Install EstandarizacionIntegridad + SchemaBootstrap x2 — deferred to Plan 03)
- Final review: Ready for Plan 03 (9287785..a46949c); minors deferred; no merge to main
- Siguiente: Plan 03 PackagePaths + Installer

## Plan 03 — PackagePaths + Installer (2026-07-17)

Plan: `docs/superpowers/plans/2026-07-17-fps-03-package-paths-installer-sql.md`
Baseline before Task 1: a46949ccfba8a8c1fc1ecc61def2ea9de7565d56

Task 1: complete (commits a46949c..27171c4, review clean; minors: no RuntimeException test, monorepo fallback probe limitation)
Task 2: complete (commits 27171c4..43ebd01, review clean; minors: source-only tests, preamble duplication)
Task 3: complete (commits 43ebd01..c1710cb, review Approved with Important deferred)
  - Important deferred: resolveInstallFile BC fallback kept for install_fixture_dir temp paths
  - Important deferred: Install orphan/undeclared migrations remain (18 active files vs manifests; D2/D8 inventory from Plan 02)
Task 4: complete (commits c1710cb..bc142fe, review clean after progress hygiene fix; minors: inventory wording, orphan migrations remain)
  - Option A: git mv seeds 010–035 → `database/seeds_legacy/` (no deletes)
  - `InstallGreenfieldTest` + `FPS-legacy-archival-decision.md`
  - Primary gates: InstallGreenfield 4/0, PackagePaths 6/0, PlatformSqlResolve 2/0
  - Smoke: Composer path consumer without local `database/schema/` → SCHEMA_OK

- [x] PackagePaths + tests
- [x] migrate.php / seed.php / install.php package-first
- [x] Installer + bootstrap_sql via resolveDataFile
- [x] Greenfield smoke + legacy decision doc
- Plan 03 complete (a46949c..bc142fe); Ready for Plan 04 minimal skeleton
- Minors open: orphan migrations documented only; broader `php tests/run.php Install` may still report ~2 failures on undeclared migrations (out of Task 4 primary gates); inventory wording around baseline-2026-06
- No merge to main

## Plan 03 — Final review (2026-07-17)

- Whole-branch: a46949c..57741a5 — Ready for Plan 04 (not merge to main)
- Gates: PackagePaths 6/0, PlatformSqlResolve 2/0, PlatformMigratePaths 3/0, InstallGreenfield 4/0
- Full suite diagnostic: 653 passed, 3 failed (Install orphan/undeclared migrations debt)
- Important deferred: resolveInstallFile BC fallback; Install 2 orphan-migration failures
- Siguiente: Plan 04 minimal consumer skeleton
Task 1: complete (commits eb2612b..555872c, review Approved; publico/ deferred to Task 2; minor: stale Marketing comments in CLAUDE.md/settings_sections.php)
Task 2: complete (commits 555872c..d995759, review Approved; minor: redundant marketing SQL test plan-mandated; migrations README stale out of scope)
Task 3: complete (commits d995759..ff02c42, review Approved; minor: doc/test path list duplication plan-mandated)

## Plan 04 — Minimal skeleton (2026-07-17)

- [x] Marketing/mkt_/publico/LebytekApi removed
- [x] Platform SQL duplication removed
- [x] Canonical assets checklist + test
- [x] Bootstrap + wrappers + standalone smoke
- Gate SkeletonPurity: 0 failed
- Siguiente: Plan 05 Lebytek_Portal

Task 4: complete (commits ff02c42..2771733, review Approved; smoke used string URL rewrite vs ConvertTo-Json; minor: progress.md spacing; note: skeleton/tests need local composer install in skeleton/)

## Plan 04 - Final review (2026-07-17)

- Whole-branch: eb2612b..2771733 - Ready for Plan 05 (not merge to main)
- Gate SkeletonPurity: 13 passed, 0 failed
- Important deferred: skeleton/database/migrations/*.sql SoT triage (Task 2 title mentioned migrations; steps did not delete them)
- Minors open: stale Marketing comments CLAUDE.md/settings_sections.php; redundant marketing SQL test; asset path list duplication; smoke string-rewrite; skeleton composer install operational
- No merge to main
- Siguiente: Plan 05 Lebytek_Portal

## Plan 05 — Lebytek_Portal local (2026-07-17)

Task 1: complete (commits empty..2589b43 Portal, review clean; minor: FPS-git-baseline.md missing upstream)
Task 2: complete (commits 2589b43..c975261 Portal, review Approved after log hygiene fix; deferred: seed.php Marketing test until Task 3 wrappers)
Task 3: complete (commits c975261..6074510 Portal, review Approved; minors: transient worktree path, dual App\\ autoload)
Task 4: complete (Portal Marketing + PortalOwnership gates green; explicit freeze delta vs dad0590 documented in Portal FPS-portal-source-sha.md)

- [x] SHA congelado dad0590 documentado
- [x] Árbol Portal sibling sin src/schema plataforma SoT
- [x] composer path repo → consolidation/framework-portal-separation
- [x] Marketing baseline 0 failed
- [x] PortalOwnership 0 failed
- Siguiente: Plan 06 boundary cutover

Task 4: complete (Portal 6074510..513b66f after split+freeze delta docs, Framework 2771733..a85cd72, review Approved; minors: FPS delta path list incomplete, InvoicePaid deferred)


## Plan 05 - Final review (2026-07-18)

- Whole-branch: Portal 2589b43..513b66f; Framework 2771733..a85cd72 - Ready for Plan 06 (not merge to main)
- Gates: Marketing 251/0, PortalOwnership 3/0, smoke OK
- Composer path: worktree (controller 2A); freeze dad0590 + documented aa9954a Portal delta
- Siguiente: Plan 06 local boundary cutover


Task 1: complete (commits a85cd72..6efb972, review clean; minors: README monorepo table contradiction + composer-setup feature-branch pin leftover — Plan 07)


Task 2: complete (commits 6efb972..8ac5680, review clean after fix 8ac5680; minors: VPS mkt script refs, test-delete-instance not in Portal)


Task 3: complete (commits 8ac5680..b7af937, review clean; minors: guard doesn't assert publico/ absence, uploads/productos/.gitkeep remains)

Task 4: complete (Framework gates + Portal gates green; archived orphan migrations; churn SQL ownership → Portal; SettingsSectionVista Marketing assertion removed)

## Plan 06 — Boundary cutover local (2026-07-17)

- [x] App\\ removed from package autoload
- [x] Marketing/Portal business removed from Framework root
- [x] PACKAGE-ROOT harness documented
- [x] Framework platform suite 0 failed
- [x] Portal composer validate + Marketing 0 failed
- Siguiente: Plan 07 documentation and agent rules


Task 4: complete (commits b7af937..362252a, review clean; minors: archival-decision doc stale, Portal fix on local main, progress backfill)


## Plan 06 - Final review (2026-07-19)

- Whole-branch: a85cd72..362252a - Ready for Plan 07 (not merge to main)
- Gates: Framework 546/0; Portal Marketing 251/0; composer validate OK
- Important deferred to Plan 07: Framework VPS scripts still mkt-oriented; README/CLAUDE/rules/composer-setup package-only rewrite; archival-decision doc stale
- Minors deferred: publico assert, uploads gitkeep, test-delete-instance, skeleton vendor noise
- Companion Portal: 06c0b09
- Siguiente: Plan 07 documentation and agent rules

Task 1: complete (commits 362252a..84025ad, review clean)
Task 2: complete (Portal commits 06c0b09..7ce6348, review clean; CLAUDE unchanged — solo lectura preexisting)
Task 3: complete (commits 84025ad..81ecddb, review clean after Important fix scoping reglas-para-ia; minor: sibling alwaysApply rules still consumer-app)

## Plan 07 — Documentation and agent rules (2026-07-17)

- [x] ARCHITECTURE-CONSUMER, TENANTS, SCHEMA-OWNERSHIP, ASSETS-PLATFORM
- [x] Portal schema ownership mirror
- [x] CLAUDE + Cursor rules updated (Framework package source, Portal consumer)
- [x] Payments → Framework; Marketing → Portal documented
- Gate FpsDocumentation: 0 failed
- Siguiente: Plan 08 publication readiness (docs only)
Task 4: complete (Portal 7ce6348..98d62c6; Framework 81ecddb..db514f5, review clean; minor: progress backfill)
Plan 07 final review: With fixes → fixed in d86da1e..5ef0307 (README + sibling alwaysApply rules); Ready for Plan 08 (not merge to main)
Framework HEAD: cf51f4d
Portal HEAD: 98d62c6
## Plan 08 - Publication readiness (2026-07-17)
Task 1: complete (FW cf51f4d..2d21de4; Portal 98d62c6..833d638; review clean; expected RED CUTOVER until Task 3; minor: stale full-suite row wording)
Task 2: complete (FW 2d21de4..56d3dcc; Portal 833d638..0ac430b, review clean)

## Plan 08 — Publication readiness (2026-07-17)

- [x] Framework + Portal manifest checklists
- [x] Remote repo proposals (deferred execution)
- [x] DEPLOY-VPS + CUTOVER-PORTAL runbooks (docs only)
- [x] FpsPublicationReadiness 0 failed
- [x] NO gh repo create / push / merge / deploy / SSH / DNS executed
- FPS roadmap Plans 00–08: **documentation and local separation complete**
- Next: explicit user order for GitHub publish + VPS cutover ops plan
