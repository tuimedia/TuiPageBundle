# Upgrading from 0.11 to 0.12

0.12 brings support for PHP 8.4 and 8.5, Symfony 7 and 8, Doctrine ORM 3 and DBAL 4, and drops Symfony 5.4 and DBAL 2. Most of that is just a `composer update`, but one column changes storage format, and that needs a short data conversion before you run your migrations.

## TL;DR checklist

* Check your app is on Symfony 6.4 or later, Doctrine ORM 2.20 or later, and DBAL 3.8 or later.
* Back up your `PageData` table (`tui_page_data`, or whatever your entity maps to).
* `composer update tuimedia/page-bundle` (plus any Symfony or Doctrine upgrades you're doing at the same time).
* Run `bin/console pages:convert-available-languages` **before** running any migrations. Try it with `--dry-run` first.
* Run `bin/console doctrine:migrations:diff`, check the migration (Postgres needs one line edited, see below), then run it.
* If your routes import the bundle's controllers with `type: annotation`, change it to `type: attribute`.

## `availableLanguages` is now stored as JSON

`PageData::$availableLanguages` used Doctrine's `array` column type, which stores PHP `serialize()` output. DBAL 4 removed that type, so the column is now mapped as `json`, like `content` and `metadata` already were.

The existing values in your database are still serialised PHP (`a:1:{i:0;s:5:"en_GB";}`), and the `json` mapping can't read them. Until they're converted, loading any page throws an error.

The bundle includes a command that rewrites them in place:

```sh
# See what it would do
bin/console pages:convert-available-languages --dry-run

# Do it
bin/console pages:convert-available-languages
```

It works on raw rows through DBAL rather than loading entities, so it runs fine while the data is still in the old format. It runs in a single transaction, skips values that are already JSON (so running it twice is harmless), and fails loudly, listing the row IDs, if it finds anything it can't read. Rows it did convert stay converted, so fix the listed rows and run it again.

### Deploy order matters

This is a one-way switch. 0.11 can't read the converted JSON values, and 0.12 can't read the unconverted serialised ones. So:

1. Deploy 0.12.
2. Run `pages:convert-available-languages` straight away, before the app serves page requests if you can manage it.
3. Run your migration.

If you need to roll back after converting, restore the table from your backup rather than just redeploying 0.11.

### The migration

After converting, generate a migration as usual:

```sh
bin/console doctrine:migrations:diff
```

What you get depends on your database:

* **MySQL and MariaDB**: the diff changes the column to `JSON`. MySQL validates every row as it alters the column, which is why the conversion has to come first. Run the migration as generated.
* **PostgreSQL**: the diff generates `ALTER TABLE page_data ALTER availableLanguages TYPE JSON`, which Postgres rejects with 'cannot be cast automatically to type json'. Edit that line of the migration to add a `USING` clause (swap in your table name):

  ```sql
  ALTER TABLE page_data ALTER availableLanguages TYPE JSON USING availableLanguages::json
  ```

* **SQLite**: nothing to change beyond the column comment.

If you're moving to DBAL 4 at the same time, the diff will probably also drop the `(DC2Type:…)` column comments on other columns. That's DBAL 4 no longer needing them, and it's safe.

## Dependencies

* Symfony 6.4, 7.x or 8.x. Symfony 5.4 is no longer supported.
* Doctrine ORM 2.20+ or 3.x, DBAL 3.8+ or 4.x, DoctrineBundle 2.12+ or 3.x. DBAL 2 is no longer supported.
* `doctrine/doctrine-bundle`, `symfony/yaml` and `psr/http-client` are now declared requirements. The bundle always needed them; they were just assumed to be installed.
* The minimum versions of `opis/json-schema` (now ^1.2) and `voku/anti-xss` (now ^4.1.43) have gone up. Older releases either lack methods the bundle calls or raise deprecation notices on PHP 8.4.

If you're upgrading to ORM 3 as well, bear in mind it only reads mapping from PHP attributes (or XML), not docblock annotations. The README's entity examples now use attributes.

## Routes

Symfony 7 removed the `annotation` route loader type. If your routes file says:

```yaml
page_controllers:
  resource: "@TuiPageBundle/Controller/"
  type: annotation
```

change `type: annotation` to `type: attribute`. That works on Symfony 6.4 too, so you can do it before upgrading.

## Behaviour changes

* Leaving `valid_languages` unset (or empty) now allows every language, as the documentation always said. Before, it rejected every language on translation export and import.
* An app with no `search_hosts` configured no longer fails when saving a page. The Typesense client is now only created when something actually uses search.
