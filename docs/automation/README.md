# Cursor Automations — Lebytek Framework

These files are the canonical prompts for the Framework audit pipeline.

| Stage | Prompt | Required repository/base |
|-------|--------|--------------------------|
| Daily audit | `AUTOMATION-01-daily-audit.md` | `Lebytek_Framework/main` |
| Audit to spec | `AUTOMATION-02-audit-to-spec.md` | `Lebytek_Framework/main` |
| Spec to plan | `AUTOMATION-03-spec-to-plan.md` | `Lebytek_Framework/main` |

## Invariants

- Configure every stage in the Cursor Automations editor with repository
  `Parzival2103/Lebytek_Framework` and branch `main`.
- A generated working branch must descend from the current `origin/main`.
- No commit exclusive to the frozen legacy feature may be an ancestor of a
  generated or source branch.
- Source PRs must target `main`; read their diff without checking out their
  head branch.
- Each stage is artifact-only: audit produces one report, spec produces one
  design spec, and plan produces one implementation plan.
- A failed branch, ancestry, mergeability, provenance, or diff check ends the
  run without creating an artifact. Reporting the error and continuing is not
  allowed.
- `feature/backoffice-api-integration` is historical migration evidence only.
- Marketing, memberships, landing and deployable-site work belongs to
  `Parzival2103/Lebytek_Portal/main`.
- Cross-repository evidence is read through authenticated GitHub API calls.
  Failure to retrieve current Portal evidence stops the downstream stage.
- Framework changes reach Portal through a tagged semver release and
  `composer.lock`.
- Automation runs do not deploy, use SSH, merge product PRs, edit production
  environment files, or execute production migrations.

Changing these files does not update an already-created Cursor Automation.
Replace the pasted instructions and verify the repository/branch in the
Automations editor whenever a canonical prompt changes.

For a lineage reset, disable the old automations or recreate them and do not
reuse prior automation memory. Old memory may preserve obsolete branch
instructions even after the pasted prompt changes.

The 2026-07-24 and 2026-07-25 lineage incident and reset procedure are recorded
in `INCIDENT-2026-07-25-ARTIFACT-LINEAGE.md`.

