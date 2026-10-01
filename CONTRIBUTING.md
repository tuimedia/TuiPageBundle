# Contributing

## Where to work

GitHub is the canonical repository: <https://github.com/tuimedia/TuiPageBundle>

Issues, pull requests and releases all live there. The Bitbucket repo
(`tui/TuiPageBundle`) is a read-only mirror, updated automatically by the
`Mirror to Bitbucket` workflow whenever a branch or tag is pushed to GitHub.
Anything pushed straight to Bitbucket will be overwritten the next time that
workflow runs, so don't.

### Already have a clone pointing at Bitbucket?

Repoint it — no need to re-clone, the history is identical:

```sh
git remote set-url origin git@github.com:tuimedia/TuiPageBundle.git
git fetch --prune --tags origin
```

Check it took:

```sh
git remote -v
```

## Branches and releases

The default branch is `master`. Branch off it, open a pull request against it.

Releases are plain git tags (`0.11.6` and so on) — Composer reads versions
straight off them. To cut one:

1. Add the entry to `CHANGELOG.md` and merge it to `master`.
2. Tag the merge commit with the bare version number, no `v` prefix:
   `git tag 0.11.7 && git push origin 0.11.7`

The mirror workflow carries the tag over to Bitbucket, so consumers on either
URL pick it up.

## Tests

```sh
composer install
composer test
```

That runs the whole suite on SQLite. The search tests skip themselves unless there's a
Typesense server to talk to, so start one first if you're touching anything search-related:

```sh
docker run -d --name typesense -p 8108:8108 typesense/typesense:29.0 --data-dir /tmp --api-key=tui-page-test
TYPESENSE_URL=http://127.0.0.1:8108 composer test
```

To run against a real database instead of SQLite, point `DATABASE_URL` at it. Anything
Doctrine accepts works; the test database is dropped and recreated for every test, so
don't point it at anything you care about:

```sh
docker run -d --name postgres -e POSTGRES_PASSWORD=pw -e POSTGRES_DB=app -p 5432:5432 postgres:17-alpine
DATABASE_URL="postgresql://postgres:pw@127.0.0.1:5432/app?serverVersion=17&charset=utf8" composer test
```

The suite fails on any deprecation the bundle causes, whether it's raised in `src/` or
in a library the bundle called. Deprecations libraries raise among themselves are
ignored. If a test fails with a deprecation, the report names the file and line.

The test app lives in `tests/App`: a small kernel, entities that extend the bundle's
(with an extra `tagData` property, as real apps tend to have), a search transformer and
custom sanitisers. Fixtures are in `tests/fixtures`. `extended-page.json` is a real
page with its text swapped for lorem ipsum, and `extended-page.response.json` is what
0.11.5 returned for it, so the API output can be checked byte for byte.

CI (`.github/workflows/tests.yml`) runs the suite across PHP 8.1 to 8.5, Symfony 6.4, 7.4
and 8, Doctrine ORM 2 and 3, DBAL 3 and 4, SQLite, PostgreSQL, MySQL and MariaDB, and with
every direct dependency at its lowest allowed version.

## Before you push

CI runs all of this, but it's quicker to catch things locally:

```sh
composer test
composer phpstan
composer rector
```

`php-cs-fixer` isn't a dev dependency — the repo just ships the config. Run it
from a global or PHIVE install if you have one:

```sh
php-cs-fixer fix --dry-run --diff
```
