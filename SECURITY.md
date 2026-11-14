# Security policy

rodokmen-web guards private family data, so security reports are very welcome.

## Reporting a vulnerability

Please **do not open a public issue**. Report it privately through GitHub:
[Security → Report a vulnerability](https://github.com/marekskopal/rodokmen-web/security/advisories/new).
Describe what an attacker can do and how to reproduce it; you will get an answer within a week, and a fixed release
as soon as possible, with credit if you wish.

## Supported versions

Only the latest release gets security fixes – update with `docker compose pull && docker compose up -d`
(or `git pull` and the installation commands without Docker).

## Hardening tips

- Keep the website behind HTTPS and list your reverse proxy in `SITE_TRUSTED_PROXIES`.
- Use a long password and a random `SITE_SECRET` and `SITE_SYNC_TOKEN`; changing the password or the secret logs
  everybody out.
- Keep `rodokmen-web.env` (with the upload token) out of git in your rodokmen project.
