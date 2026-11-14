# rodokmen-web

A private, password-protected family tree website for genealogy projects made with the Claude Code skill
**[rodokmen](https://github.com/sarsonj/rodokmen-skill)** – the skill that researches your ancestors in the
digitised Czech parish registers and keeps every fact backed by a register entry.

The skill ships its own static website (Astro). rodokmen-web is an alternative for those who want to host the
tree themselves on any PHP server or in Docker: everything – pages, register scans, family photos – is shown only
after logging in, and new research is published with one command, without rebuilding or redeploying the site.

The user interface is in Czech, like the skill.

## Features

- **Password-only login** for the family (no user accounts). The password is stored only as a hash, the login is
  a signed cookie, repeated wrong attempts are throttled.
- **Everything is private** – scans and photos live outside the web root and are streamed only to logged-in
  visitors.
- **Ancestor explorer** (`/predci`): one person in focus with three generations of ancestors, click anyone to move
  up. Every visitor can choose their own starting person (remembered in a cookie).
- **Full tree** (`/strom`) drawn left to right with collapsible branches, and a **360° fan chart** (`/vejir`) with
  zoom, pan and search.
- **Person pages** with a life timeline, family, siblings, places on a map, every register entry with the
  highlighted crop of the scan, transcription and translation, and family photos.
- **Generations**, **map** of places (OpenStreetMap), **register entries**, **photos and documents**, **stories**,
  **research log**, **open questions** and **search**.
- HTTPS only with HSTS, a strict Content-Security-Policy and the other security headers; responsive, dark mode,
  print-friendly.

## How the data get to the website

The research project (made by the skill) and the website are separate: the website never needs access to your
research repository. The skill's exporter (`tools/export_web.py`) turns `data/*` into `site.json`, notes and
images; `bin/rodokmen-sync` packs them into a **data bundle** and publishes it:

```
rodokmen project ──tools/export_web.py──▶ site.json, notes, crops, scans
                 ──bin/rodokmen-sync────▶ bundle (manifest.json with SHA-256 of every file)
                                          ├─▶ https://your-site/api/sync/*   upload API (token)
                                          ├─▶ user@server:/path/var/data     rsync over SSH
                                          └─▶ ../rodokmen-web/var/data       local folder
```

Only changed files are sent. With the API, the server builds a complete new release next to the current one
(unchanged files are hard-linked) and switches to it atomically, so visitors never see half-uploaded data;
the previous release is kept for a manual rollback.

The bundle format (`manifest.json`, `format: 1`) is the contract between the two sides. It allows only
`site.json`, `md/*.md`, `img/*.jpg` and `full/*.jpg`; the server rejects anything else.

### Publishing from your project

`bin/rodokmen-sync` needs only Python 3.9+ (already required by the skill); the SSH target also needs `rsync`.
Download it, or use it from a clone of this repository:

```sh
curl -fsSLO https://raw.githubusercontent.com/marekskopal/rodokmen-web/master/bin/rodokmen-sync
```

In your rodokmen project folder create `rodokmen-web.env` (see `rodokmen-web.env.example`) and **add it to
`.gitignore`** – it holds the upload token:

```sh
RODOKMEN_WEB_TARGET=https://rodokmen.example.org
RODOKMEN_WEB_TOKEN=<the website's SITE_SYNC_TOKEN>
```

Then, after every round of research:

```sh
python3 rodokmen-sync              # export + upload
python3 rodokmen-sync --dry-run    # only show what would be sent
python3 rodokmen-sync --no-export  # publish the last export as it is
python3 rodokmen-sync --jobs 8     # more parallel uploads (default 4)
python3 rodokmen-sync --help
```

Uploads to the API run in parallel, and every request is retried a few times (2, 4, 8, 16 s apart, or as the server's
`Retry-After` says) after a lost connection, a timeout or a temporary server error (HTTP 429, 500, 502, 503, 504).
Files already uploaded stay on the server, so running the command again after a failure sends only the rest.

Options `--target` and `--token` and the environment variables `RODOKMEN_WEB_TARGET` / `RODOKMEN_WEB_TOKEN`
override the file. Targets: `https://…` (API), `user@host:/path` or `ssh://user@host:port/path` (rsync over SSH),
anything else is a local folder.

## Installation

### Docker

The image is based on `php:8.5-apache`. TLS is expected to be terminated by a reverse proxy in front of the
container, which listens on plain HTTP (port 8080 on localhost in `docker-compose.yml`).

```sh
git clone https://github.com/marekskopal/rodokmen-web.git && cd rodokmen-web
cp .env.example .env           # set SITE_PASSWORD_HASH, SITE_SECRET and SITE_SYNC_TOKEN (see below)
docker compose up -d --build
```

The data live in `./var` (mounted into the container), not in the image. Upload them with `rodokmen-sync` –
no restart is needed. Logs are in `./log` (Tracy's `error.log`, `warning.log`, `info.log` and exception
reports, `php-error.log`) and in `docker compose logs`. Updating the website: `git pull && docker compose up -d --build`.

### Apache without Docker

Requirements: PHP ≥ 8.5 and Composer. Point the virtual host's `DocumentRoot` to `public/`, enable `mod_rewrite`
and allow `.htaccess` (`AllowOverride All`), run `composer install --no-dev`, copy `.env.example` to `.env` and
make `var/` writable by the web server.

### Configuration

Environment variables, or `.env` in the project root:

| Variable | Meaning |
|---|---|
| `SITE_PASSWORD_HASH` | Hash of the site password – `php bin/hash-password.php`. Put it in **single quotes** (it contains `$`). |
| `SITE_SECRET` | Random secret for signing the login cookie (≥ 32 characters) – `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` |
| `SITE_SYNC_TOKEN` | Token of the upload API for `rodokmen-sync`; empty disables the API (use rsync then). Generate it like the secret. |
| `SITE_DEFAULT_PERSON` | Optional slug of the default starting person (default: the root of the tree). |
| `SITE_SEARCH_HINT` | Optional example in the search field, e.g. `Např. Novák, Lomnice, tesař…`. |
| `SITE_TRUST_PROXY` | `1` behind a reverse proxy (uses `X-Forwarded-Proto`, and `X-Forwarded-For` when the request comes from a private address: the rightmost public address in it is the client, entries a client forged on the left are ignored). |
| `SITE_LOGIN_DAYS` | How long a login stays valid (default 30). |
| `SITE_FORCE_HTTPS` | `1` (default) redirects HTTP to HTTPS and sends HSTS; `0` only for a site without TLS. `localhost` is never redirected. |
| `SITE_LOG_LEVEL` | `production` (default) or `debug` (also debug records). |
| `SITE_LOG_DIR` | Log folder (default `log/`). [Tracy](https://tracy.nette.org) writes `error.log`, `warning.log` (failed logins), `info.log` (data uploads), `critical.log` and an HTML report `error--*.html` for every exception. |
| `SITE_LOG_STDERR` | `1` (default) also copies every record to stderr – Apache's error log, the container log in Docker. |
| `SITE_DATA_DIR`, `SITE_CACHE_DIR` | Data and cache folders (default `var/data`, `var/cache`). The upload API keeps its releases next to the data folder. |

The site title, footer, contact and the texts come from the project's `data/web.json` and data, through the
export. Changing the password or the secret logs everybody out.

## Development

```sh
make install                # composer install
python3 bin/rodokmen-sync -p ../my-rodokmen --target var/data
cp .env.example .env        # set SITE_PASSWORD_HASH and SITE_SECRET
make dev                    # http://localhost:8080
make check                  # phpcs + phpstan + phpunit + tests of the sync client
make fix                    # phpcbf
```

A small PHP application without a framework or a Node build: one front controller, routes declared with PHP
attributes via [marekskopal/router](https://github.com/marekskopal/router), plain PHP templates, plain CSS and JS.

```
public/            web root: index.php, .htaccess, assets/
src/
├── Kernel.php     bootstrap: config, container, attribute routes, middleware, error pages
├── Config.php     settings from environment variables / .env
├── Auth/          password check, signed login cookie, login throttling
├── Http/          authentication middleware, HTTPS redirect and security headers
├── Logger/        Tracy logging behind PSR-3 LoggerInterface
├── Data/          Repository (read-only access to site.json) and readonly models
├── Sync/          data bundle manifest and the store behind the upload API
├── View/          template rendering, HTML helpers, fan chart
└── Controller/    pages, images, login and the sync API
templates/         PHP templates
bin/               rodokmen-sync (Python), hash-password.php, dev-router.php
tests/             PHPUnit tests (fixture data in tests/Support/Fixture.php), tests/client for rodokmen-sync
```

Code style: PHP_CodeSniffer with the Slevomat standard (tabs), PHPStan at level max with strict rules, readonly
models instead of arrays. The exported data are read through `ArrayReader`, which checks every type, so an
exporter change that breaks the format fails loudly instead of rendering wrong pages.

Contributions are welcome – please run `make check` before opening a pull request.

## License

[MIT](LICENSE)
