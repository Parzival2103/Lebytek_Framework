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

