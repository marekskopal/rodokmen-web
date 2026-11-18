---
name: bump-deps
description: Bump minor/patch versions of all rodokmen-web dependencies (Composer, pnpm incl. pnpm itself, and the base images of the single Dockerfile) on one branch with one commit per category, verify with make check, e2e and a Docker build, then open a PR and merge it when CI is green. Use when asked to "bump dependencies", "update deps", "run the dependency bump" or similar. Majors are never auto-bumped — they are reported in a final outdated audit.
---

# Bump dependencies

Bump **minor and patch versions only** of all dependencies. Major versions are NEVER bumped
automatically — they are surfaced in the final audit for the user to decide on.

## Repo topology

- One repository, `marekskopal/rodokmen-web`, default branch `master`, remote `origin`. No second
  repo, no subset branch.
- One monolithic image: the root `Dockerfile` (multi-stage: `composer` → vendor, `node` + pnpm →
  front-end assets, `mlocati/php-extension-installer`, final `php:X.Y.Z-apache`).
  `docker-compose.yml` only references the released `ghcr.io/marekskopal/rodokmen-web:1` image —
  nothing to bump there.
- CI (`.github/workflows/ci.yml`) runs on every PR: composer audit, PHPCS/PHPStan/PHPUnit, pnpm
  build + `pnpm audit --prod` + Vitest, Playwright e2e, the Python sync-client tests.
  `release.yml` additionally **builds the Docker image on PRs** that touch `Dockerfile`,
  `composer.*`, `package.json` or `pnpm-lock.yaml` — so the PR checks the Dockerfile too.
- Work goes through a branch + PR, never a direct push to `master` (see the task-workflow memory).

## Workflow overview

```
0. branch from fresh master
1. Update composer dependencies   → make check
2. Update pnpm dependencies       → make check + make test-e2e
3. Update docker dependencies     → local docker build + /health smoke test
4. push, PR, watch CI, merge when green
5. outdated audit → report what is held back (majors)
```

Each category is its **own commit**, with these **exact** messages (same as in ukolio — do not
embellish):
- `Update composer dependencies`
- `Update pnpm dependencies`
- `Update docker dependencies`

Skip a category with no changes — no empty commits. Create a TaskCreate list mirroring the steps.

---

## Step 0 — branch

```bash
git status --short          # must be clean; if dirty, stop and ask the user
git checkout master && git pull
git checkout -b chore/bump-deps-$(date +%Y-%m-%d)
```

## Step 1 — Composer

```bash
composer update -W
composer outdated --direct
```
`composer update` moves the lock within the existing `^` constraints. For any **direct** dependency
(require and require-dev) whose latest is a higher minor/patch **within the same major**, raise the
floor of its constraint in `composer.json` (e.g. `^2.6` → `^2.8`; for `0.x` packages a higher
minor is acceptable) and run `composer update -W` again. Leave majors alone. Do not touch
`"php": ">=8.5"` or `config.platform.php`.

Verify and fix (PHPStan max and PHPCS are strict — new rules in `shipmonk/phpstan-rules`,
`phpstan-strict-rules`, `slevomat/coding-standard` or a new `tomasvotruba/cognitive-complexity`
often report new errors; fix the code, never add a baseline or `@phpstan-ignore`):
```bash
make check
composer audit --locked --no-dev --abandoned=report   # the CI audit job
```
If code had to change (`src/`, `tests/`, `templates/`), run the `code-review` skill with `--fix`
before committing; a pure manifest/lock change does not need it. Commit:
```bash
git add composer.json composer.lock src tests templates
git commit -m "Update composer dependencies"
```

## Step 2 — pnpm

`package.json` pins **exact versions** (no carets) — keep it that way. `ncu`/renovate are not
installed and `pnpm update --latest` would cross majors, so find the newest same-major version of
each package explicitly:
```bash
pnpm outdated
pnpm view <pkg>@^<current-major> version | tail -1      # e.g. pnpm view vitest@^5 version | tail -1
```
Write that exact version into `package.json` (dependencies: `@fontsource/eb-garamond`, `leaflet`;
devDependencies: `@playwright/test`, `jsdom`, `vitest`). Leaflet 1.x → 2.x is a major — hold it.

**pnpm itself** is pinned in four places, keep them identical: `packageManager` in `package.json`,
the `npm install --global pnpm@…` line in `Dockerfile`, and both such lines in
`.github/workflows/ci.yml`. Bump it to the newest same-major release (`npm view pnpm@^<major> version | tail -1`).

Then:
```bash
pnpm install
pnpm update            # refresh transitive deps within ranges (install alone never moves locked transitives)
pnpm build
pnpm audit --prod      # the CI audit step
gh api 'repos/marekskopal/rodokmen-web/dependabot/alerts?state=open' --jq '.[].security_advisory.summary'
```
**Minimum release age.** pnpm refuses versions younger than its `minimumReleaseAge` (a supply-chain
policy). `pnpm view` ignores it, `pnpm outdated` respects it — so when the two differ, take the
version `pnpm outdated` offers. If `pnpm install` creates a `pnpm-workspace.yaml` with a
`minimumReleaseAgeExclude` list, never commit it: delete it, step the too-fresh package back to the
newest allowed version, then `pnpm clean --lockfile && pnpm install` and confirm
`pnpm install --frozen-lockfile` passes (CI uses it and would reject the lock otherwise). The
too-fresh version goes into the next bump.

When a vulnerable transitive is held back by an exact upstream pin, add a patch-level entry under
`pnpm.overrides` (or `overrides:` in a `pnpm-workspace.yaml` if one exists by then), and drop it
again once upstream catches up.

Verify — the front end is affected, so e2e runs too:
```bash
make check
pnpm exec playwright install chromium     # needed after a @playwright/test bump
make test-e2e                             # starts make e2e-server itself via playwright.config.mjs
```
Fix breakages (`public/assets`, `tests/js`, `tests/e2e`); if code changed, `code-review --fix`.
Commit:
```bash
git add package.json pnpm-lock.yaml Dockerfile .github/workflows/ci.yml public/assets tests
git commit -m "Update pnpm dependencies"
```
(The pnpm pin in `Dockerfile`/`ci.yml` belongs to this commit, not the docker one.)

## Step 3 — docker dependencies

Bump the pinned tags in `Dockerfile` to the latest **minor/patch within the current major**, keeping
the exact-version style (the Dockerfile header says images are pinned for reproducible rebuilds —
never switch to a floating tag like `8.5-apache`):

| Image | Tag shape | Note |
|---|---|---|
| `composer` | `2.X.Y` | |
| `node` | `26.X.Y-alpine` | stay on the major CI uses (`node-version: '26'` in `ci.yml`) |
| `mlocati/php-extension-installer` | `2.X.Y` | |
| `php` | `8.5.Z-apache` | stay on 8.5 (`composer.json` platform, CI `php-version: '8.5'`); a new PHP minor is a deliberate decision → report in Step 5 |

Query Docker Hub for published tags (official images live under `library/`):
```bash
tags() { curl -s "https://hub.docker.com/v2/repositories/$1/tags?page_size=100&name=$2" \
  | python3 -c "import json,sys; print('\n'.join(t['name'] for t in json.load(sys.stdin)['results']))"; }
tags library/php 8.5. | grep -E '^8\.5\.[0-9]+-apache$' | sort -V | tail -1
tags library/node 26. | grep -E '^26\.[0-9]+\.[0-9]+-alpine$' | sort -V | tail -1
tags library/composer 2. | grep -E '^2\.[0-9]+\.[0-9]+$' | sort -V | tail -1
tags mlocati/php-extension-installer 2. | grep -E '^2\.[0-9]+\.[0-9]+$' | sort -V | tail -1
```

Verify the image builds and serves (the GD/WebP check runs inside the build):
```bash
docker build -t rodokmen-web:bump-test .
docker run -d --rm --name rodokmen-bump-test -p 127.0.0.1:8099:80 \
  -e SITE_FORCE_HTTPS=0 -e SITE_SECRET=demo-secret-only-for-the-local-demo-server-0000 \
  -e 'SITE_PASSWORD_HASH=$2y$12$JloCvcq/xvCOGUKjMt3MKek0wcT5WN30tmUz0t4GcxTnvyJv0xq/q' \
  rodokmen-web:bump-test
curl -fsS http://127.0.0.1:8099/health; echo
docker exec rodokmen-bump-test php -v | head -1     # the new PHP patch is really in
docker stop rodokmen-bump-test
```
(The secret and hash are the demo ones from the `Makefile`'s `DEMO_ENV`.) If Docker is not running, say so and
rely on the `release.yml` image build on the PR. Commit:
```bash
git add Dockerfile
git commit -m "Update docker dependencies"
```

## Step 4 — PR, CI, merge

```bash
git push -u origin HEAD
gh pr create --base master --title "Update dependencies" --body "<per-category list of what moved: package old → new; held-back majors from Step 5>"
gh pr checks --watch
```
Both `CI` and `Docker image and release` (image build) must be green. If something fails,
investigate, fix in the matching category commit (or a follow-up commit), push, re-watch.

Merge only when the user's request already said so ("… and merge if green"); otherwise stop at the
green PR and offer to merge in one line:
```bash
gh pr merge --merge --delete-branch
git checkout master && git pull
```

No version bump, no `Release vX.Y.Z` commit, no tag and no CHANGELOG entry — releasing is a
separate step the user triggers.

## Step 5 — outdated audit

Surface everything that could NOT be updated so the user can decide on majors:
```bash
composer outdated --direct
pnpm outdated
```
plus Docker images with a newer major/minor than allowed (`php` 8.6/9, `node` 28, `composer` 3, …)
and GitHub Actions in `.github/workflows/*.yml` with a newer major (`actions/checkout@v7`, …; they
are pinned to majors, so only majors can be behind). Report a concise summary: dependency, current
vs latest, and whether it is a **major** (manual decision) or held by something else. Do **not**
apply major bumps.

## Guardrails

- Minor/patch only, everywhere. When in doubt whether a bump crosses a major, hold it and report it.
- `bin/rodokmen-sync` has no dependencies (Python standard library only) — nothing to bump there.
- Never commit personal data — irrelevant here, but do not regenerate or add fixtures/demo data.
- Fix any check failure caused by the update in the same commit as the bump; never push known-red code.
- Dependabot PRs on the repo may overlap — fine, prefer the lock state this flow produces and
  mention the overlapping PRs (they close themselves or can be closed).
