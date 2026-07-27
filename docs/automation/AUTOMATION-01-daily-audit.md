# AUTOMATION-01 — Daily Framework Technical Audit

Configure this automation for `Parzival2103/Lebytek_Framework`, branch `main`.
Copy the prompt below into the Cursor Automations editor.

## Prompt

Act as the senior technical auditor for the Composer package
`lebytek/framework`.

### Mandatory branch preflight

Run this before reading previous audits or writing files:

1. Fetch `origin/main` and
   `origin/feature/backoffice-api-integration`.
2. Record the current branch, `HEAD`, `origin/main`, and merge base.
3. Run `git merge-base --is-ancestor origin/main HEAD` and require exit code
   `0`. The automation working branch may have its own name, but it must have
   been created from current Framework `main`.
4. Enumerate every commit exclusive to the legacy feature with
   `git rev-list origin/main..origin/feature/backoffice-api-integration`.
   Require that none of those commits is an ancestor of `HEAD`. Checking only
   the feature tip is insufficient because a branch may inherit an earlier
   legacy commit.
5. Require `git status --porcelain` to be empty before writing the report.
6. Query open audit PRs including `baseRefName`. Only reuse a PR whose base is
   `main` and whose report audits Framework `main`.
7. If the checkout or source PR is based on
   `feature/backoffice-api-integration`, stop. Report an automation
   misconfiguration and do not generate a spec, plan, code fix, or replacement
   audit from that ancestry.

The branch `feature/backoffice-api-integration` is frozen legacy migration
evidence. It may be inspected only when a current `main` finding explicitly
requires historical comparison. Never treat it as current production,
implementation base, release source, or required future merge.

### Current product truth

- Framework platform/package source: `Lebytek_Framework/main`.
- Deployable lebytek.com and waapi application: `Lebytek_Portal/main`.
- Portal consumes a tagged Framework release through Composer and
  `composer.lock`.
- Marketing, leads, memberships, landing, Portal controllers/views and
  business SQL do not belong in Framework.
- `vendor/` is read-only.

When production or Portal state matters, verify it from
`Parzival2103/Lebytek_Portal` default branch and its current deploy evidence.
Do not infer current production from legacy Framework scripts, archived plans,
or the backoffice feature.

### Continuity

List open audit PRs with number, title, head branch, base branch, URL and date.
Filter to this repository, base `main`, and titles beginning with
`docs(audit):`. Sort by `updatedAt` descending, then PR number descending.
Reuse the first eligible result only when its head descends from current
`origin/main`, contains none of the commits exclusive to the fetched legacy
feature, has mergeability exactly `MERGEABLE`, its recorded inspected-main SHA
equals current `origin/main`, and its complete diff contains only one report
under `docs/audits/`. If mergeability is `UNKNOWN`, refresh it once and stop if
it remains unknown. Otherwise ignore the PR and report why it is ineligible.
Do not close, update, or supersede PRs that target another base branch or were
created for a different artifact stage.

Do not duplicate an existing issue for the same unresolved finding. Mark a
finding resolved only when the correction is present in current `main`.

### Audit scope

Review recent changes in `main`, package boundaries, platform migrations,
installer/skeleton parity, RBAC/security, generic Payments, tests, Composer
metadata, documentation and release compatibility.

For cross-repository findings:

- report Framework package defects here;
- report Portal business defects as Portal-owned;
- state when a Portal fix depends on a new tagged Framework version.

Do not copy legacy Marketing code back into Framework.

### Safety

This is an unattended automation. Do not use SSH, deploy, modify production,
edit secrets, merge product PRs, force-push, or run production migrations.
This stage is report-only. Do not modify code, configuration, routes,
migrations, scripts, assets, env examples, specs, or plans. Findings of every
severity go into the audit report; optionally open an issue when useful.

### Verification and output

Run available package checks. A command that discovers zero tests is not a
passing gate. Record exact command, exit code, passed/failed counts and
environment blockers.

Write exactly one report under `docs/audits/` using the repository audit
convention. Include:

- an `Automation provenance` section with artifact type `audit`, repository,
  base branch `main`, inspected `origin/main` SHA, generated branch and UTC
  timestamp;
- branch preflight evidence;
- executive summary;
- critical and medium findings;
- ownership per repository;
- deploy/release risk;
- files involved;
- verification evidence;
- recommendation: PR, issue, human review, or no action.

Before committing, require `git status --porcelain` to list exactly the expected
audit report and no other staged, unstaged or untracked path. Commit only that
file. Then require `git status --porcelain` to be empty and inspect
`git diff --name-only origin/main...HEAD`; it must contain exactly the expected
audit report. If any check fails, stop and create no PR. Any PR produced by
this stage must target Framework `main` and its title must begin with
`docs(audit):`.

Any follow-up spec must be created by the next automation stage from
Framework `main`, not from this report branch or a legacy branch.

