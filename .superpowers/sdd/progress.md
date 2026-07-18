# SDD Progress — FPS Plan 00

Plan: `docs/superpowers/plans/2026-07-17-fps-00-inventory-consolidation-branch.md`
Worktree: `.worktrees/framework-portal-separation`
Branch: `consolidation/framework-portal-separation`
Base: `2c71d3f7f75eea2ee746bc271b9a3907dbbdd9cd`

## Tasks

Task 1: complete (commits 2c71d3f..438b436, review clean; minor: leftover template phrase in FPS-git-baseline.md)
Task 2: complete (commits 438b436..a192941, review clean; minor: UTF-8 BOM on delta paths file)

## Plan 00 — Inventario y rama consolidación (2026-07-17)

- [x] Rama `consolidation/framework-portal-separation` desde `main`
- [x] `FPS-git-baseline.md` con SHAs
- [x] `BOUNDARY-framework-vs-portal-fps.md` + delta paths
- [x] Sin cambios runtime
- Gate: N/A (docs only)
- Siguiente: Plan 01 generic payments
Task 3: complete (commits a192941..1db4ed6, review clean; minor: BOM + git add -f for gitignored SDD path)
Final review: With fixes → fixed in 9db3777 (BOM + baseline wording). Plan 00 complete.

## Plan 01 — Generic Payments Stripe (in progress)

- Worktree: .worktrees/framework-portal-separation
- Branch: consolidation/framework-portal-separation
- Plan: docs/superpowers/plans/2026-07-17-fps-01-generic-payments-stripe.md
- Source SHA: dad059056d26b6eb527815f85cf71ecd507a57fe
- Baseline before Task 1: 9db377790f7f3ee4d4b9e4730eaad47383709e17
- Suite baseline: 615 passed, 6 failed (documented in FPS-git-baseline.md)

Task 1: complete (commits 9db3777..a6eaa25, review clean; minors: CheckoutRequest invalid-mode untested, CheckoutSession/PaymentFailed gaps, Money allows negative — brief-inherited)

Task 2: complete (commits a6eaa25..6f9584f, review clean; minors: prefer-stable flip, tryClaim reflection-only, driver() unknown quirk)

Task 3: complete (commits 6f9584f..6a9f354, review clean)

## Plan 01 — Generic Payments (2026-07-17)

- [x] Domain + Application + Infrastructure in src/
- [x] pay_events schema + stripe/stripe-php
- [x] Config OFF; no app/** stripe wiring
- Gate: `php tests/run.php Payments` → 0 failed

Task 3 review: clean (commits 6f9584f..9287785; minors: progress.md hygiene, static-only DI gate coverage)


## Plan 01 — Final review (2026-07-17)

- Whole-branch: 9db3777..9287785 — Ready for Plan 02 (not merge to main)
- Payments gate: 17 passed, 0 failed
- Full suite: 632 passed, 6 failed (baseline fail count unchanged; was 615/6)
- Important deferred (pre-enablement / Plan 05): StripeGateway non-mxn webhook zeros amount but still CheckoutCompleted — prefer Ignored/fail-closed before live webhooks
- Minors open: invalid-mode test, CheckoutSession/PaymentFailed fixtures, Money negative, prefer-stable flip, driver() unknown, progress.md hygiene
- Accepted Plan 01: tryClaim reflection-only; static DI config tests


## Plan 02 — Platform stabilization (in progress)

Task 1: complete (commits 9287785..00d0f4c, review clean; minor: brief said don't touch get/all/cargarCache — instance migration necessary on this branch)

Task 2: complete (commits 00d0f4c..1b868f8, review clean after provenance fix; minor: Contexto wording vs provenance)

## Plan 02 — Platform stabilization (2026-07-17)

- [x] ConfiguracionService cache fix + characterization tests
- [x] LEGACY seeds/migrations inventory (no deletion)
- Gate ConfiguracionServiceCache: 0 failed (3 passed)
- Gate Payments: 0 failed (17 passed)
- Gate Kernel: 0 failed (15 passed; harness fix: `assert_false` in `tests/lib/microtest.php`)
- Gate Auth: 0 failed (48 passed)
- Gate full suite (diagnostic): `638` passed, `3` failed (baseline `M_baseline=6`; D2/D8 preexisting: `Install/EstandarizacionIntegridadTest` orphan SQL ownership; `Install/SchemaBootstrapTest` loose seeds + undeclared incremental migrations — deferred to Plan 03 PackagePaths/Installer)
- Siguiente: Plan 03 PackagePaths + Installer
Task 3: complete (commits 1b868f8..a46949c, review clean; minors: progress hygiene, Marketing assert_false shim redundant)

## Plan 02 — Platform stabilization (2026-07-17)

- [x] ConfiguracionService cache fix + characterization tests
- [x] LEGACY seeds/migrations inventory (no deletion; provenance on consolidation)
- Gate ConfiguracionServiceCache: 0 failed (3 passed)
- Gate Payments: 0 failed (17 passed)
- Gate Kernel: 0 failed (15 passed)
- Gate Auth: 0 failed (48 passed)
- Gate full suite (diagnostic): 638 passed, 3 failed (baseline M_baseline=6; D2/D8 preexisting: Install EstandarizacionIntegridad + SchemaBootstrap x2)
- Final review: Ready for Plan 03 (9287785..a46949c); minors deferred; no merge to main
- Siguiente: Plan 03 PackagePaths + Installer

## Plan 03 — PackagePaths + Installer (in progress)

- Worktree: .worktrees/framework-portal-separation
- Branch: consolidation/framework-portal-separation
- Plan: docs/superpowers/plans/2026-07-17-fps-03-package-paths-installer-sql.md
- Baseline before Task 1: a46949ccfba8a8c1fc1ecc61def2ea9de7565d56
Task 1: complete (commits a46949c..27171c4, review clean; minors: no RuntimeException test, monorepo fallback probe limitation)
Task 2: complete (commits 27171c4..43ebd01, review clean; minors: source-only tests, preamble duplication)
Task 3: complete (commits 43ebd01..c1710cb, review Approved with Important deferred)
  - Important deferred: resolveInstallFile BC fallback kept for install_fixture_dir temp paths
  - Important deferred: Install suite 3 failed (loose seeds 010-035 + orphan/undeclared migrations) — Task 4 must clear greenfield / document; same D2/D8 inventory from Plan 02

## Plan 03 — PackagePaths + Installer (2026-07-17)

- [x] PackagePaths + tests
- [x] migrate.php / seed.php / install.php package-first
- [x] Installer + bootstrap_sql via resolveDataFile
- [x] Greenfield smoke + legacy decision doc
- Gate PackagePaths: 0 failed
- Gate PlatformSqlResolve: 0 failed
- Siguiente: Plan 04 minimal skeleton
