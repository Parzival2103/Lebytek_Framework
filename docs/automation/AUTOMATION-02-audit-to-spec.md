# AUTOMATION-02 — Framework Audit to FPS Spec

Configure this automation for `Parzival2103/Lebytek_Framework`, branch `main`.
Copy the prompt below into the Cursor Automations editor.

## Prompt

Convert the latest eligible Framework technical audit into a precise design
specification without inheriting its working branch.

### Mandatory branch and source preflight

1. Fetch `origin/main` and
   `origin/feature/backoffice-api-integration`. Require `origin/main` to be an
   ancestor of the current automation `HEAD`. Enumerate
   `git rev-list origin/main..origin/feature/backoffice-api-integration` and
   require that none of those legacy-exclusive commits is an ancestor of
   `HEAD`. Require `git status --porcelain` to be empty.
2. Find candidate audit PRs with number, title, head, `headRefOid`, base,
   state, mergeability, `updatedAt`, URL and files. Sort by `updatedAt`
   descending, then PR number descending.
3. Select only an open audit PR whose title begins with `docs(audit):`, whose
   `baseRefName` is `main`, whose mergeability is exactly `MERGEABLE`, and
   whose provenance identifies it as a Framework `main` audit.
   If mergeability is `UNKNOWN`, refresh it once; if it remains unknown, stop.
4. Fetch the candidate head without checking it out. Record its merge-base
   with `origin/main`. Run
   `git merge-base --is-ancestor origin/main <headRefOid>` and require exit
   code `0`. Require that none of the commits from
   `git rev-list origin/main..origin/feature/backoffice-api-integration` is an
   ancestor of `<headRefOid>`, then inspect
   `git diff --name-only origin/main...<headRefOid>`.
5. Require that complete three-dot diff to contain exactly one audit report
   under `docs/audits/`. Reject product code, migrations, configuration,
   routes, scripts, assets, env examples, specs, plans, extra documentation or
   unrelated history.
6. Read the source report through the PR diff or GitHub API. Do not checkout,
   merge, rebase onto, or create work from the source PR head.
7. Require provenance values to match verified data exactly: artifact type
   `audit`, Framework repository, base `main`, generated branch equal to the PR
   `headRefName`, and inspected-main SHA equal to current `origin/main`.
8. Reject any source whose head, diff or report treats
   `feature/backoffice-api-integration` as the implementation base or current
   production application.
9. If preflight fails, report the configuration/source error and create no
   spec.

### Current ownership and branch policy

- Framework package work starts from `Lebytek_Framework/main`.
- Portal business work starts from `Lebytek_Portal/main`.
- The backoffice feature is historical migration evidence only.
- Framework is not the deployed site.
- Portal consumes Framework by stable semver and a committed `composer.lock`.
- Never propose merging the backoffice feature into Framework `main`.

Verify current APIs, files, tests and Composer state against both repositories'
default branches before accepting claims from the audit. Inspect Portal `main`
through authenticated `gh repo view` / `gh api` calls without checking out or
merging a Portal branch. Record the Portal SHA and fail closed if current
Portal evidence cannot be retrieved. Current repository evidence overrides
archived plans, old VPS scripts and pre-cutover documents.

### Specification requirements

Write exactly one design spec under `docs/superpowers/specs/` on the current
main-based automation branch.

The spec must:

- separate Framework platform requirements from Portal business requirements;
- name the owning repository and required base branch for every requirement;
- identify missing public contracts instead of assuming APIs found only in
  legacy code;
- include a Framework semver/release boundary when Portal consumes a new
  package capability;
- describe safe migration behavior for both greenfield and existing Portal
  databases;
- define tests that must discover at least one test and fail for the intended
  reason before implementation;
- distinguish implementation, staging and production operations;
- keep production operations outside this unattended run;
- use the legacy feature only as explicitly labelled historical evidence.

Do not include implementation commits, deploys, issue closure or PR merges.
Do not copy Marketing, Portal, landing or membership code into Framework.

### Output

Record:

- an `Automation provenance` section with artifact type `spec`, repository,
  base branch `main`, inspected Framework `origin/main` SHA, generated branch
  and UTC timestamp;
- source audit PR and base branch;
- source audit `headRefOid` and inspected Framework `origin/main` SHA;
- current Framework and Portal SHAs inspected;
- branch preflight evidence;
- ownership map;
- dependencies and compatibility constraints;
- risks, rollback and acceptance criteria.

The next plan stage must read this spec without checking out this branch and
must create its artifact from Framework `main`.

Before committing, require `git status --porcelain` to list exactly the expected
design spec and no other staged, unstaged or untracked path. Commit only that
file. Then require `git status --porcelain` to be empty and inspect
`git diff --name-only origin/main...HEAD`; it must contain exactly the expected
design spec. If any check fails, stop and create no PR. Any PR produced by this
stage must target Framework `main` and its title must begin with `docs(spec):`.

