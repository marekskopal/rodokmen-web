# Changelog

All notable changes of rodokmen-web. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versions follow [Semantic Versioning](https://semver.org/).

**Versions.** The website (the Docker image `ghcr.io/marekskopal/rodokmen-web` and the git tags `vX.Y.Z`) has
the version in the headings below. The sync client `bin/rodokmen-sync` has a version of its own
(`rodokmen-sync --version`), attached to every GitHub release. Both talk through the data bundle, whose format
number changes only when old clients or servers could not read it – see *Bundle format* in each release.

## [Unreleased]

### Website

- **Painted tree: the width goes to the ancestors who are known.** The tree kept a place for everybody who could
  exist, so a line known far back was squeezed into a strip a few millimetres wide under an empty sheet. Now the
  trees of the father's and the mother's ancestors are pushed together and stretched over the width: a deep line
  reaches over its shorter neighbours, a lone known parent stands just a little to their side, and the plaques of
  the old generations are large enough to read (the demo family's seven generations fit an A1 at 6.5 pt). The
  trunk may stand a little off the middle of the sheet.

- **Painted tree: flatter ovals.** In the first generations too narrow for ribbons, where the names are still
  written across, the ovals were almost circles. They are now clearly wider than high (a little over half as
  high as wide, never lower than a ribbon), which leaves more room for the branches and leaves; only ovals with
  the name written along them stay tall.

- **Painted tree: forks continue the wood below.** Branches to the parents were as thick as the trunk they grew
  from and left the name ribbon sideways, so their cut ends showed above it. They now start behind the ribbon
  inside the wood that arrives there, never thicker than it, rise straight up and only then part.

- **Ancestor poster: the heading follows the person.** The poster of anybody but the website's own person is headed
  by their family ("Rodokmen Rybkových" for Miloslav Rybka, also from Rybková), not by the title of the website;
  a heading of your own still replaces it.

- **Ancestor poster: painted tree** ("Malovaný strom"): the person on a trunk growing from a grassy mound, a
  branch to every known ancestor and the names on ribbons in the colours of the family lines – like old painted
  family trees, especially with the historical style. The tree is grown, not stamped: every branch bends its own
  way and is as thick as the number of ancestors it carries, twigs end in sprays of leaves with an apple here and
  there, the ribbons sag and lean, and all outlines are drawn with an unsteady hand in ink over uneven washes.
  The same tree is drawn every time. Vector only (no filters), sharp up to A0. Notes on the historical models
  are in `docs/research/painted-trees.md`.

- **Ancestor poster: historical style and SVG download.** "Styl: Historický" draws the poster on parchment with a
  double frame and ornaments, the title in a cartouche, the names in EB Garamond (self-hosted, SIL Open Font
  License), the family lines in muted colours and, in the trees, labels with cut corners. "Stáhnout SVG"
  (`/predci/{slug}/plakat.svg`) downloads the same poster as an SVG file with the fonts inside, for a print shop or
  further editing.

- **Ancestor poster: tree layouts.** Besides the fans, the poster draws the ancestors as a tree growing upward
  (the person at the bottom) or from left to right, with lines between children and parents and dashed boxes for
  ancestors not found yet. All boxes of a generation have the same size of letters; narrow boxes write the names
  along themselves.

- **Ancestor poster** (`/predci/{slug}/plakat`, "Plakát předků" in the explorer, the fan chart and the vývod): a
  person's ancestors on one large sheet, A4 to A0, portrait or landscape, as a 360° fan or a 180° half fan, with the
  family lines in a legend and an own heading. The letters are sized for the paper; the page says how small the
  smallest ones will be and recommends the most generations that stay readable. "Tisk / PDF" prints just the sheet
  at its paper size and without margins (Chrome, Edge and Firefox set the size of "Save as PDF" by themselves).

## [1.1.0] - 2026-11-15

Scan viewer, WebP thumbnails, printing, GEDCOM export and rollback of a data release.

**Upgrading.** Docker: `docker compose pull && docker compose up -d` (the image now contains GD). Apache: optionally
install PHP's GD extension with JPEG and WebP (e.g. `php-gd`) for the WebP thumbnails – without it the pages load the
JPEGs as before; then `composer install --no-dev` and `pnpm install --frozen-lockfile --prod && pnpm build` as after
every update. Installed `rodokmen-sync` 1.1.0 keeps working; update it for `--rollback`.

### Website

- **GEDCOM export** ("Stáhnout GEDCOM" in the footer, `/export/rodokmen.ged`): the tree in GEDCOM 5.5.1 (UTF-8) for
  other genealogy software – the ancestors with birth, baptism, marriage and death (date and place from the facts,
  otherwise from the life span), the families of their parents with the full siblings, every register entry as a
  source cited with the scan number and the transcription, and everything else (trades, notes, evidence, other
  relatives) as notes.
- **Print and PDF**: a numbered list of a person's ancestors (vývod, `/predci/{slug}/tisk`, linked from the explorer
  and the person page) with their facts and register entries, optionally with the transcriptions, and a choice of
  generations; numbers count from the chosen person (father 2n, mother 2n + 1) and an ancestor met twice points to
  the first number. The print stylesheet prints in light colours without navigation and controls, keeps cards on one
  page, opens the transcriptions and writes out archive links; "Tisk / PDF" uses the browser's Save as PDF.
- **WebP thumbnails**: pages load smaller WebP copies of the scans and photos sized to the screen (`srcset`, 480,
  960 and 1440 px, never wider than the original). They are made with GD on the first request and kept in
  `var/cache/thumbs`; copies of changed images are replaced at once, and an upload through the API removes those of
  deleted images. Without GD's WebP support the pages load the JPEGs as before. The Docker image now has GD.
- **Scan viewer**: register scans and family photos open in a viewer with zoom (mouse wheel, pinch, double-click or
  double-tap, buttons, `+` / `-` / `0`) and pan (drag, arrow keys). The crop on the page shows at once and the full
  resolution replaces it when it has loaded.
- **Rollback** of a data release: `POST /api/sync/rollback` switches the website back to the previous release
  atomically. A commit now keeps the release shown before it (rather than the two newest), so a rollback is never
  undone by the next upload's cleanup.
- The attribute routes are cached in `var/cache/routes` instead of scanning the controllers with reflection on every
  request; the cache is rebuilt when the controllers or the installed packages change.
- The upload API compares files against the current `manifest.json` instead of hashing all the data on every
  plan and commit; only files changed after the manifest was written are hashed.

### rodokmen-sync 1.2.0

- `--rollback` switches the website back to the data of the previous upload (API target only).

### Bundle format

- Unchanged: format 1.

### Development

- Front-end tests: Vitest unit tests of the browser scripts (`tests/js`, part of `make check`) and Playwright
  end-to-end tests on the demo family (`tests/e2e`, `make test-e2e`, a new CI job) – a smoke test of every main page
  and the basic cases (login, logout, search, ancestor explorer, theme, mobile navigation).
- The demo family has an invented register scan (`tests/Support/demo`), so `make demo` and the end-to-end tests
  show the scan viewer.

## [1.0.0] - 2026-11-14

The first release.

### Website

- Private family tree website for genealogy projects made with the
  [rodokmen](https://github.com/sarsonj/rodokmen-skill) Claude Code skill: ancestor explorer, full tree, 360° fan
  chart, person pages with register entries, generations, map, register entries, photos and documents, stories,
  research log, open questions and search – Czech user interface, responsive, dark mode, print-friendly.
- **Password-only login** with a signed cookie. Wrong passwords are throttled per address (an IPv6 /64 counts as
  one) and for all visitors together; an attempt is checked and counted in one locked step, and browsers that have
  logged in before are not stopped by the shared limit, so an attack cannot lock the family out.
- **Everything is private**: scans and photos are streamed only to logged-in visitors; HTTPS with HSTS, a strict
  Content-Security-Policy that allows no third-party scripts (Leaflet is bundled, built with pnpm), and the other
  security headers.
- **Reverse proxies**: `SITE_TRUSTED_PROXIES` lists the proxies whose `X-Forwarded-For`, `-Proto` and `-Host` are
  believed; forwarded headers from anybody else are ignored (`SITE_TRUST_PROXY=1` still means "private networks").
- **Upload API** for `rodokmen-sync`: only changed files are sent, a new data release is built next to the current
  one and switched atomically; partial uploads are removed.
- **Operations**: Docker image for amd64 and arm64 with a health check (`/health`), pinned base images, Tracy log
  files rotated at 10 MB, old exception reports and throttle files removed.
- `make demo` serves a demo family on http://localhost:8081 (password `demo`).

### rodokmen-sync 1.1.0

- Exports a rodokmen project and publishes it on the website: over the upload API, rsync over SSH or into a local
  folder. Single file, Python 3.9+ standard library only.
- API requests are retried after a lost connection, a timeout or a temporary server error (honouring
  `Retry-After`), and missing files are uploaded in parallel (`--jobs`, default 4).

### Bundle format

- Format 1 (`manifest.json` with the SHA-256 and size of every file; `site.json`, `md/*.md`, `img/*.jpg`,
  `full/*.jpg`).

[Unreleased]: https://github.com/marekskopal/rodokmen-web/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/marekskopal/rodokmen-web/releases/tag/v1.1.0
[1.0.0]: https://github.com/marekskopal/rodokmen-web/releases/tag/v1.0.0
