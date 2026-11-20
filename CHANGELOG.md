# Changelog

All notable changes of rodokmen-web. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versions follow [Semantic Versioning](https://semver.org/).

**Versions.** The website (the Docker image `ghcr.io/marekskopal/rodokmen-web` and the git tags `vX.Y.Z`) has
the version in the headings below. The sync client `bin/rodokmen-sync` has a version of its own
(`rodokmen-sync --version`), attached to every GitHub release. Both talk through the data bundle, whose format
number changes only when old clients or servers could not read it – see *Bundle format* in each release.

## [Unreleased]

## [1.3.0] - 2026-11-20

The historical painted tree is the default poster, drawn like an illuminated manuscript, and the heading and the
footer of the poster can be left out.

**Upgrading.** Docker: `docker compose pull && docker compose up -d`. Apache: `composer install --no-dev` and
`pnpm install --frozen-lockfile --prod && pnpm build` as after every update. Installed `rodokmen-sync` 1.2.0 keeps
working.

### Website

- **Ancestor poster: the historical painted tree by default.** A poster opened without choices is the painted
  tree in the historical style (it was the fan in the simple style); A1 landscape and the readable number of
  generations as before.

- **Ancestor poster: heading and footer to choose.** Checkboxes under the heading's text leave out the heading, or
  the person or the number of generations in its subtitle; those on their own row leave out the footer, or its
  name, date or legend (the family lines and the note on uncertain ancestors). Everything is shown by default, and
  the parts of a hidden heading or footer keep their ticks, greyed out, for when it comes back. Without a heading
  the tree starts at the top margin. The download keeps the choice.

- **Ancestor poster: the footer says the poster's heading.** Beside the date it wrote the website's title, even
  when the heading was another person's family or the visitor's own.

- **Ancestor poster: trees rise beside the title.** A tree used to start below the band of the title, which left an
  empty strip across the top when it is shallow on the left – often the painted tree, whose lines grow higher on
  one side, or the tree from left to right. It now reaches as high as its names keep clear of the title; branches
  and leaves may grow behind it. The taller tree may fit one more readable generation.

- **Checkboxes look like the other fields:** a rounded square, filled with the accent colour and ticked when on
  (on the poster, the generations and the numbered list of ancestors).

- **README:** a screenshot of the historical painted tree.

- **Docker image** builds again after the `composer` base image dropped the mbstring extension: the PHP extensions
  the libraries need are checked in the image that runs the site.

- **Historical poster: an illuminated border.** The double rule with diamonds in the corners gave way to a border
  like the margin of a medieval manuscript: a vermilion band between ink and gold rules, a gold vine with leaves,
  tendrils, berries and flowers winding along it, and a gold quatrefoil on lapis in every corner. Drawn by an
  unsteady hand and the same every time for the same person; all vector, so it prints sharp up to A0. The margin
  of the historical style is a little wider (5 % of the short side instead of 3.5 %), so a crowded poster may
  fit one generation less on the same paper.

- **Historical poster: a title like the opening of a manuscript.** The heading lies on a parchment scroll rolled up
  at both ends instead of a rounded panel, its first letter is an illuminated initial (a gold letter on lapis in a
  gold square with white pen work) as tall as both lines, the subtitle is written in red, and the footer is dated
  "dne 20. 11. léta Páně MMXXVI". The simple style is unchanged.

- **Painted tree: a flowering meadow and pen-hatched wood.** The mound under the tree is strewn with little
  flowers in white, vermilion and lapis with golden hearts, tufts of grass and wild strawberries, like the
  millefleurs grounds of old illuminations, as dense on A0 as on A3. The trunk and the thick branches are shaded
  with short strokes of the pen along their shaded side, and their ink outline is a little stronger.

- **Historical poster: aged parchment.** The paper is no longer one even gradient: its corners are yellowed more
  than the middles of the sides, a few large faint stains with tide lines lie on it, and rusty spots of foxing
  gather towards the edges. Different for every person, the same every time, all vector, and the ink keeps its
  contrast (WCAG AA) on the darkest spot.

- **Painted tree: larger names in the ovals.** The names on the oval plaques are fitted to the oval itself rather
  than to a box inside it, and may take a line for the given names and one for the surname (a birth surname
  after "roz." stays with it, a note in brackets moves to the line of the years). On a crowded A1 the letters are
  almost twice as large. In the historical style the names are measured letter by letter in EB Garamond rather than
  by an average letter, so the usual names, narrower than the average, get larger letters still. When
  the number of generations is left to the poster, the room gained may go to one more generation instead. The
  flat ovals are a little taller (two thirds of their width), the lines of a split name closer together, and the
  text may reach a little further along an oval than across it, as its inner ring does – so the names on the flat
  ovals are about a sixth larger still. The ribbons are unchanged.

### rodokmen-sync

- Unchanged: 1.2.0.

### Bundle format

- Unchanged: format 1.

## [1.2.1] - 2026-11-19

The person page tells a life in order, and the dependencies are up to date.

**Upgrading.** Docker: `docker compose pull && docker compose up -d`. Apache: `composer install --no-dev` and
`pnpm install --frozen-lockfile --prod && pnpm build` as after every update. Installed `rodokmen-sync` 1.2.0 keeps
working.

### Website

- **Person page: the life from birth to death.** The facts were listed in the order of the data, not of the life.
  They are now sorted by the date they mention: within a year births and baptisms come first and deaths and
  burials last; a birth without a date opens the list, a death without a date closes it, and an undated fact
  such as a trade stays after the fact it follows, but before the death.

- **Dependencies:** PHP libraries within their versions; the Docker image builds its front-end libraries with
  Node.js 26 LTS (was 24) and the current pnpm. The PHP 8.5 base image is unchanged.

### rodokmen-sync

- Unchanged: 1.2.0.

### Bundle format

- Unchanged: format 1.

## [1.2.0] - 2026-11-17

Ancestor poster: fans, trees and a painted tree on one sheet for printing, in a simple or a historical style.

**Upgrading.** Docker: `docker compose pull && docker compose up -d`. Apache: `composer install --no-dev` and
`pnpm install --frozen-lockfile --prod && pnpm build` as after every update – the build now also copies the EB
Garamond font of the historical style. Installed `rodokmen-sync` 1.2.0 keeps working.

### Website

- **Ancestor poster** (`/predci/{slug}/plakat`, "Plakát předků" in the explorer, the fan chart and the vývod): a
  person's ancestors on one large sheet, A4 to A0, portrait or landscape, as a 360° fan or a 180° half fan, with the
  family lines in a legend and a heading of the person's family ("Rodokmen Rybkových" for Miloslav Rybka, also from
  Rybková) or your own. The letters are sized for the paper; the page says how small the smallest ones will be and
  recommends the most generations that stay readable. "Tisk / PDF" prints just the sheet at its paper size and
  without margins (Chrome, Edge and Firefox set the size of "Save as PDF" by themselves). Every choice redraws the
  preview at once, and printing and the download always follow it; without JavaScript a "Zobrazit" button applies
  the choices.

- **Ancestor poster: tree layouts.** Besides the fans, the poster draws the ancestors as a tree growing upward
  (the person at the bottom) or from left to right, with lines between children and parents and dashed boxes for
  ancestors not found yet. All boxes of a generation have the same size of letters; narrow boxes write the names
  along themselves.

- **Ancestor poster: painted tree** ("Malovaný strom"): the person on a trunk growing from a grassy mound, a
  branch to every known ancestor and the names on ribbons and ovals – like old painted family trees, especially
  with the historical style. Men are in shades of blue and women in shades of old rose, turned a little by the
  family line. The tree is grown, not stamped: every branch bends its own way and is as thick as the number of
  ancestors it carries, forks rise out of the wood below, twigs end in sprays of leaves with an apple here and
  there, the ribbons sag and lean, and all outlines are drawn with an unsteady hand in ink over uneven washes. The
  width goes to the ancestors who are known, so a line known far back reaches over its shorter neighbours (the demo
  family's seven generations fit an A1 at 6.5 pt). The same tree is drawn every time. Vector only (no filters),
  sharp up to A0. Notes on the historical models are in `docs/research/painted-trees.md`.

- **Ancestor poster: historical style and SVG download.** "Styl: Historický" draws the poster on parchment with a
  double frame and ornaments, the title in a cartouche, the names in EB Garamond (self-hosted, SIL Open Font
  License), the family lines in muted colours and, in the trees, labels with cut corners. "Stáhnout SVG"
  (`/predci/{slug}/plakat.svg`) downloads the same poster as an SVG file with the fonts inside, for a print shop or
  further editing.

- **Numbered list of ancestors**: the choice of generations and transcriptions applies at once, like on the poster.

- **Generations: one table to read.** The columns of the generations stand under each other (every table used to
  size its columns by its own content), and from the 6th generation on the relation is just "předek v 6. generaci"
  without the chain of "pra" in brackets, which made the column far too wide.

- **Select boxes look like the text inputs** (same border, corners, background and font, with a drawn arrow).

### rodokmen-sync

- Unchanged: 1.2.0.

### Bundle format

- Unchanged: format 1.

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

[Unreleased]: https://github.com/marekskopal/rodokmen-web/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/marekskopal/rodokmen-web/releases/tag/v1.3.0
[1.2.1]: https://github.com/marekskopal/rodokmen-web/releases/tag/v1.2.1
[1.2.0]: https://github.com/marekskopal/rodokmen-web/releases/tag/v1.2.0
[1.1.0]: https://github.com/marekskopal/rodokmen-web/releases/tag/v1.1.0
[1.0.0]: https://github.com/marekskopal/rodokmen-web/releases/tag/v1.0.0
