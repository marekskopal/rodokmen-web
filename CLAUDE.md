# rodokmen-web

Public open-source website for family trees made with the rodokmen Claude Code skill
(https://github.com/sarsonj/rodokmen-skill). See `README.md` for features, the data flow and the layout.

- Code, comments, CLI output, README and commit messages in **English**; the website's user interface in
  **Czech** (like the skill).
- **Never commit personal data**: no real family names, places or scans in code, tests or examples. Test data
  are the fictional Novák family in `tests/Support/Fixture.php`. The demo family for `make demo` and the README
  screenshots (`tests/Support/Demo*.php`) has the author, Marek Skopal, as the home person at his own wish – only
  his name, no facts about him; everybody and everything else in it is invented, except that the places are
  real towns of Haná so that the map makes sense.
- **Before every commit run `make check`** (phpcs + phpstan max + phpunit + `tests/client`) and fix everything it
  reports – no baseline, no `@phpstan-ignore`. `make fix` runs phpcbf.
- Code style: tabs, Slevomat ruleset, readonly DTOs (`src/Data/Model`) instead of arrays. New behaviour gets a test.
- `bin/rodokmen-sync` must stay a single file using only the Python standard library (3.9+), because users
  download it alone into their rodokmen project.
- The bundle format (`src/Sync/Manifest.php` ↔ `FORMAT` in `bin/rodokmen-sync`) is a contract with installed
  clients: change it only with a new format number and keep reading the old one.
- The data come from the skill's exporter (`tools/export_web.py` → `web/src/data/site.json`, `web/src/generated/*.md`,
  `web/public/img|zdroje`); a change in the skill's format shows up as `InvalidDataException`.
