# Contributing to rodokmen-web

Thank you for helping! Bug reports, ideas and pull requests are welcome – please open an issue first for anything
bigger than a small fix, so that we can agree on the approach.

## Development

Requirements: PHP ≥ 8.5 with Composer, Node.js ≥ 20 with [pnpm](https://pnpm.io), Python ≥ 3.9 (for the sync
client's tests), optionally Docker.

```sh
make install    # composer install, pnpm install, pnpm build (Leaflet into public/assets/vendor)
make demo       # the demo family on http://localhost:8081, password: demo
make dev        # http://localhost:8080 with your own data (var/data) and .env
make check      # phpcs + phpstan + phpunit + the sync client's tests – must pass before every commit
make fix        # fixes what phpcbf can
```

See the README's *Development* section for the layout of the code.

## Rules

- **Never commit personal data.** No real family names, places, dates or scans in code, tests, screenshots or
  examples. Tests use the fictional Novák family (`tests/Support/Fixture.php`); the demo family
  (`tests/Support/Demo*.php`) is invented apart from the author's name, which is there at his own wish.
- **Languages:** code, comments, CLI output, README and commit messages in English; the website's user interface
  in Czech, like the skill.
- **Code style:** tabs, the Slevomat ruleset (`ruleset.xml`), PHPStan at level max – fix what they report, no
  baseline and no `@phpstan-ignore`. Readonly models (`src/Data/Model`) instead of arrays. Global functions are
  called unqualified (no `use function`).
- **Tests:** new behaviour gets a test (PHPUnit in `tests/`, the sync client in `tests/client`).
- **`bin/rodokmen-sync`** stays a single file using only the Python standard library (3.9+): users download it
  alone into their rodokmen project.
- **The bundle format** (`src/Sync/Manifest.php` ↔ `FORMAT` in `bin/rodokmen-sync`) is a contract with installed
  clients: change it only with a new format number, and keep reading the old one.
- **The data** come from the skill's exporter (`tools/export_web.py`); a change of its format shows up as an
  `InvalidDataException` – adapt the readers in `src/Data` and the fixture.

## Pull requests

1. Branch from `master`, keep one topic per pull request and one logical change per commit.
2. Run `make check`; add an entry to the *Unreleased* section of `CHANGELOG.md` for changes users notice.
3. CI runs the same checks and builds the front-end libraries; the Docker image is built when the Dockerfile,
   the dependencies or the release workflow change, and before every release.

## Releases

Maintainers: move the *Unreleased* entries of `CHANGELOG.md` under the new version and date, raise `VERSION` in
`bin/rodokmen-sync` if the client changed, then push a tag `vX.Y.Z`. The *Docker image and release* workflow pushes
the image to `ghcr.io/marekskopal/rodokmen-web` and creates the GitHub release with the notes and the sync client.
After the very first release, set the package's visibility to *Public* in its GitHub settings – a new package is
private, and `docker compose pull` would fail for everybody else.

## Security issues

Please do not open a public issue – see [SECURITY.md](SECURITY.md).
