# AGENTS.md — Lebytek Framework package source

This repository publishes the Composer package `lebytek/framework`. It is not
the deployable application for lebytek.com or waapi.lebytek.com.

## Canonical repositories and branches

| Scope | Repository | Canonical branch |
|-------|------------|------------------|
| Framework platform/package | `Parzival2103/Lebytek_Framework` | `main` |
| Lebytek business application and VPS sites | `Parzival2103/Lebytek_Portal` | `main` |
| WhatsApp API | `Parzival2103/WhatsApiLebytek` | `main` |

`feature/backoffice-api-integration` is a frozen legacy monolith reference. It
may be inspected for migration history, but it is not a valid base for new
audits, specs, plans, implementation branches, releases, or deploys.

Do not merge `feature/backoffice-api-integration` into `main` unless the user
explicitly requests that exact merge. Current Framework work still starts from
`main`; current Portal work starts from `Lebytek_Portal/main`.

## Ownership

- Framework platform changes: `src/`, platform SQL, `skeleton/`, package tests.
- Portal business changes: `Lebytek_Portal/app`, `config`, `routes`, business
  SQL and tests.
- Consumers install Framework through Composer. `vendor/` is always read-only.
- Framework capability reaches Portal through a tagged semver release and an
  updated `composer.lock`, not through a branch checkout in production.

## Automation branch preflight

Before an automation writes an audit, spec, or plan:

1. Fetch `origin/main`.
2. Require `git merge-base --is-ancestor origin/main HEAD` to exit `0`.
3. Fetch the frozen legacy feature and require no commit exclusive to that
   feature (`origin/main..origin/feature/backoffice-api-integration`) to be an
   ancestor of `HEAD` or a source PR head.
4. Confirm any source PR targets `main`, descends from `origin/main`, is
   `MERGEABLE` and has only the artifact type allowed for that stage.
5. Require a clean working tree before writing and after the artifact commit.
6. Never checkout or inherit ancestry from the legacy backoffice feature.
7. Keep every stage artifact-only: one audit report, one spec or one plan.
8. If a branch, source, provenance or diff check fails, stop artifact
   generation. Reporting the error and continuing is prohibited.

Canonical automation prompts live in `docs/automation/`.

