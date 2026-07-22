# Portal cutover checklist (Framework maintainer view)

**NO PRODUCTION EXECUTION in FPS Plan 08.** Human sign-off required for each section.

## Evidence gates (must be green before cutover)

| Gate | Command | Owner repo |
|------|---------|------------|
| Package autoload purity | `php tests/run.php PackageAutoloadBoundary` | Framework |
| SQL resolution | `php tests/run.php PlatformSqlResolve` | Framework |
| Skeleton purity | `php tests/run.php SkeletonPurity` | Framework |
| Root not portal | `php tests/run.php FrameworkRootNotPortal` | Framework |
| Platform suite | `php tests/run.php` | Framework |
| Portal marketing | `php tests/run.php Marketing` | Portal |
| Portal ownership | `php tests/run.php PortalOwnership` | Portal |
| Composer | `composer validate` | Both |

## Publication readiness

- [ ] `docs/superpowers/FPS-publication-manifest-checklist.md` completed
- [ ] `Lebytek_Portal/docs/superpowers/FPS-portal-composer-checklist.md` completed
- [ ] Remote proposals reviewed (`FPS-remote-repo-proposal.md` both repos)
- [ ] `composer.lock` in Portal pins reproducible framework build

## VPS cutover (deferred)

- [ ] GitHub Portal repo created — **explicit user order only**
- [ ] VPS Composer auth for private `Lebytek_Framework`
- [ ] Staging smoke passed (landing, admin, api health)
- [ ] Rollback path documented in Portal `docs/DEPLOY-VPS.md` validated on staging
- [ ] Retire monorepo auto-pull on lebytek.com document root

## Rollback triggers

- Marketing suite fails on staging Portal after migrate
- Admin login broken after asset sync
- API health script fails against api.lebytek.com
- Unexpected platform SQL drift (consumer copied schema.sql)

rollback procedure: Portal DEPLOY-VPS § Rollback — restore previous web root + DB backup.

## Policy reminders

- **Never** merge `feature/backoffice-api-integration` → `main` without **explicit user order**
- Framework is not a deployable site; Portal is the consumer for lebytek.com
- Accepted debt A1–A8 remain until follow-up plans

## Sign-off

| Role | Name | Date | OK |
|------|------|------|-----|
| Maintainer | | | |
| Ops | | | |
