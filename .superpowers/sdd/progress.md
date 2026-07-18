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
