# SDD Progress — Invoicing Facturapi Production Hardening (Tasks 2–10)

Plan: `docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md`
Branch: `cursor/invoicing-hardening-p02-p10-896b`
Base: `154fe17f46a4b4ac85f80c47a522266d09f2540b` (origin/main, includes Task 1 / #109)

Task 1: complete on main (154fe17, PR #109) — ledger start 1/10

Task 2: complete (commits 154fe17..1e68d44, review clean; minors: case-variant meta keys untested, depth-2 intentional, style blank line)
Task 3: complete (commits 1e68d44..e663378, review clean; minor: empty idempotencyKey omits Facturapi fields)
Task 4: complete (commits e663378..63f9877, review clean after InvoiceProviderIdConflict fix; minor: triple-failure untested; mark() A25 merge deferred to Task 5)
Task 5: complete (commit pending; retrieve/listByExternalId, InvoiceClaimRow read model, orphan recovery + forceReissueOrphanClaim, mark() A25 merge, A16 pending fidelity); minors: forceReissueOrphanClaim retry-mark cascade untested beyond first fallback, InMemoryInvoiceEventLog de-finaled for test double composition
