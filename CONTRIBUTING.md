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

## Before you push

There are no automated checks yet, so run the tooling yourself:

```sh
composer install
vendor/bin/phpstan analyse src
vendor/bin/rector process --dry-run
```

`php-cs-fixer` isn't a dev dependency — the repo just ships the config. Run it
from a global or PHIVE install if you have one:

```sh
php-cs-fixer fix --dry-run --diff
```
