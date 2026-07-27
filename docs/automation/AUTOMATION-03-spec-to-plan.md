# AUTOMATION-03 — FPS Spec to Implementation Plan

Configure this automation for `Parzival2103/Lebytek_Framework`, branch `main`.
Copy the prompt below into the Cursor Automations editor.

## Prompt

Turn the latest eligible FPS design spec into an executable, cross-repository
implementation plan grounded in current `main` branches.

### Mandatory branch and source preflight

1. Fetch Framework `origin/main` and
   `origin/feature/backoffice-api-integration`. Require `origin/main` to be an
   ancestor of the current automation `HEAD`. Enumerate
   `git rev-list origin/main..origin/feature/backoffice-api-integration` and
   require that none of those legacy-exclusive commits is an ancestor of
   `HEAD`. Require `git status --porcelain` to be empty.
2. Select an open spec PR only when its title begins with `docs(spec):`, its
   base is Framework `main`, its mergeability is exactly `MERGEABLE` and its
   provenance identifies it as a Framework `main` spec; obtain its
   `headRefName`, `headRefOid`, `updatedAt`, files and commits. Sort candidates
   by `updatedAt` descending, then PR number descending. If mergeability is
   `UNKNOWN`, refresh it once; if it remains unknown, stop.
3. Fetch the candidate head without checking it out. Record its merge-base
   with `origin/main`. Run
   `git merge-base --is-ancestor origin/main <headRefOid>` and require exit
   code `0`. Require that none of the commits from
   `git rev-list origin/main..origin/feature/backoffice-api-integration` is an
   ancestor of `<headRefOid>`, then inspect
   `git diff --name-only origin/main...<headRefOid>`.
4. Require the complete three-dot diff to contain only the expected design
   spec under `docs/superpowers/specs/`. Reject PRs that also carry plans,
   product code, migrations, configuration, routes, scripts, assets or legacy
   application history.
5. Read the spec through its PR diff or GitHub API. Never checkout or inherit
   the spec branch.
6. Require provenance values to match verified data exactly: artifact type
   `spec`, Framework repository, base `main`, generated branch equal to
   `headRefName`, inspected-main SHA equal to current `origin/main`, source
   audit PR and source audit OID.
7. Verify the spec's source audit also targeted Framework `main` and passed
   the audit-source ancestry, provenance and diff restrictions from
   AUTOMATION-02.
8. Inspect current `Lebytek_Framework/main` and `Lebytek_Portal/main` before
   writing tasks. Confirm referenced paths, signatures, routes, tests,
   migrations, Composer constraints and lock state. Retrieve Portal `main`
   through authenticated `gh repo view` / `gh api` calls without checking out
   or merging it; record its SHA and fail closed if evidence is unavailable.
9. Reject a source or checkout based on
   `feature/backoffice-api-integration`. Report the problem and create no plan.

### Canonical execution bases

- Framework tasks: new implementation branch from `Lebytek_Framework/main`.
- Portal tasks: new implementation branch from `Lebytek_Portal/main`.
- WhatsApi tasks, if any: new implementation branch from
  `WhatsApiLebytek/main`.
- Legacy backoffice code may be consulted for migration history only. It is
  never a prerequisite branch and must not be merged into `main`.

### Plan quality requirements

Write exactly one plan under `docs/superpowers/plans/` on the current
main-based automation branch.

Start it with an `Automation provenance` section containing artifact type
`plan`, repository, base branch `main`, inspected Framework `origin/main` SHA,
generated branch, UTC timestamp, source spec PR and source spec `headRefOid`.

For every task include:

- repository, base branch, dependencies and exact owned files;
- interfaces verified against current code;
- a test-first red step with an expected non-zero failure;
- focused verification and relevant regression commands;
- a guard that rejects `0 passed, 0 failed` as success;
- a focused commit boundary;
- compatibility and rollback notes.

When Portal requires a new Framework capability, order the work as:

1. add and test the complete generic Framework contract on `main`;
2. merge through normal reviewed delivery;
3. publish an appropriate stable semver tag;
4. update Portal's Composer constraint only when needed;
5. update and commit Portal `composer.lock`;
6. implement and test the Portal behavior.

Do not assume methods, enum cases, routes, feature flags or migrations merely
because they exist in legacy code. If a prerequisite is absent from current
`main`, add an explicit prerequisite task.

For database work, cover both bootstrap schema and upgrade migrations. Verify
that the actual migration runner applies upgrades to existing databases.

For deploy documentation, treat Portal `main` plus its locked Framework version
as the current application model. Historical monorepo rollback material must be
labelled historical and must not become the default deploy path.

### Safety and scope

This unattended stage creates documentation only. Do not modify product code,
use SSH, deploy, edit environments/secrets, run production migrations, merge
PRs, close issues or change DNS/cron.

The final plan must be executable without merging or basing work on
`feature/backoffice-api-integration`.

### Final self-check

Before finishing, confirm:

- current branch descends from Framework `main`;
- every task uses the correct repository and base;
- all named files and APIs exist or are explicitly created by prior tasks;
- Composer release ordering is complete;
- no verification command can pass by discovering zero tests;
- no current production claim relies only on a pre-cutover document;
- the plan contains no feature-to-main merge or feature-based implementation.

Before committing, require `git status --porcelain` to list exactly the expected
implementation plan and no other staged, unstaged or untracked path. Commit
only that file. Then require `git status --porcelain` to be empty and inspect
`git diff --name-only origin/main...HEAD`; it must contain exactly the expected
implementation plan. If any check fails, stop and create no PR. Any PR
produced by this stage must target Framework `main` and its title must begin
with `docs(plan):`.

