# Changes

## 0.12.0

Read [UPGRADE-0.12.md](UPGRADE-0.12.md) before upgrading: there's a data conversion to run before your migrations.

### BREAKING

- Minimum PHP version is now 8.3. PHP 8.1 reached end of life on 31 December 2025, and 8.2 does on 31 December 2026.
- Supports Symfony 6.4, 7.x and 8.x. Symfony 5.4 is no longer supported.
- Supports Doctrine ORM 2.20+ and 3.x, DBAL 3.8+ and 4.x, and DoctrineBundle 2.12+ and 3.x. DBAL 2 is no longer supported.
- `PageData::$availableLanguages` is now stored as JSON instead of with Doctrine's `array` type (removed in DBAL 4). Existing rows must be converted with `pages:convert-available-languages` before running the schema migration.
- `doctrine/doctrine-bundle`, `symfony/yaml`, `psr/http-client` and the Symfony components the bundle uses directly are now declared requirements.
- Minimum versions raised to `opis/json-schema` ^1.2 and `voku/anti-xss` ^4.1.43.

### ADDED

- `pages:convert-available-languages` command, which rewrites stored `availableLanguages` values from PHP-serialised arrays to JSON. Supports `--dry-run` and is safe to run more than once.

### FIXED

- PHP 8.4 and 8.5 deprecations, including every use of the deprecated `FILTER_SANITIZE_STRING` filter.
- Symfony 7 and 8 deprecations: uses the `Attribute` namespaces for `Route` and `Groups`, and `AutowireIterator` instead of `TaggedIterator`.
- Saving a page failed when search was disabled (no `search_hosts`), because the Typesense client was built with no nodes. The client is now created on first use.
- Leaving `valid_languages` unset rejected every language on translation export and import. An empty list now allows all languages, as documented.
- Leaving `serializer_groups` or `search_api_key` out of the config raised "Undefined array key" warnings on every container build.
- `PageDataRepository::getAllLanguages()` no longer uses `SELECT DISTINCT` on a JSON column, which PostgreSQL can't compare.
- Removed the `doctrine.event_subscriber` tag on `SearchSubscriber`. Its `#[AsDoctrineListener]` attributes already register it.

### CHANGED

- Dev tooling updated to PHPStan 2 (now at level 6) and Rector 2.
- Added a PHPUnit test suite and a GitHub Actions matrix covering PHP 8.3 to 8.5, Symfony 6.4 to 8, Doctrine ORM 2 and 3, DBAL 3 and 4, and SQLite, PostgreSQL, MySQL and MariaDB. It fails on any deprecation the bundle causes.

## 0.11.5

### FIXED

- Fixed some PHP 8.4 deprecations

## 0.11.4

### ADDED

- Added `Tui\PageBundle\MetadataSanitizerInterface` to allow custom sanitisation of page & langData metadata. Create a class that implements this interface, tag it with `tui_page.metadata_sanitizer` service tag, and it will be applied to all metadata objects before saving.

## 0.11.3

### ADDED

- Apply custom sanitisers to block fields by creating a class that implements `Tui\PageBundle\Sanitizer\SanitizerInterface` and tagging it with `tui_page.sanitizer` service tag, then adding `contentMediaType` to the block field definition in the component schema.

## 0.11.2

### CHANGED

- Pin `typesense/typesense-php` to `^4.7 | ^5.0` instead of just `^4.7`
- Allows upgrading your Typesense server version from e.g. `0.24.0` to `29.0`.
- However, you can upgrade to `0.12.0` in your projects and use `^5.0` without upgrading your underlying Typesense server version
- If you stay on Typesense `0.24.0`, new `v5` PHP SDK methods like Stopwords / Conversation AI will 404, and basic features will continue to work

## 0.11.1

### ADDED

- Add `getClient()` method to `TypesenseClient` to access underlying SDK

## 0.11.0

### FIXED

### BREAKING

- Minimum PHP version is now 8.1
- Minimum Symfony version is now 6.4

### FIXED

- Using `[#AsCommand]` attributes instead of `$defaultName` / `$defaultDescription`
- Using `#[AsDoctrineListener]` instead of `implements EventSubscriber` (introduced in
  DoctrineBundle 2.7.2)
- Using specific EventArgs classes instead of `LifecycleEventArgs` (deprecated in Doctrine ORM 2.14)

## 0.10.5

### FIXED

- Bulk import could trigger with 0 documents, causing an error.

## 0.10.4

### FIXED

- Fixed SearchSubscriber failing to unindex docs on entity deletion

## 0.10.3

### FIXED

- Tolerate missing or empty block langdata

## 0.10.2

### BREAKING

- Minimum PHP version is now 8.1

## 0.10.1

### FIXED

- Moved source into `src/` folder to resolve a composer autoloader issue with rector config file

## 0.10.0

### BREAKING

- Minimum PHP version is now 8.0
- Minimum Symfony version is now 5.4
- No longer includes Swagger annotations
- Removed SchemaController

### CHANGED

- Route annotations are now attributes

- Fix SearchSubscriber failing to delete documents from index on entity deletion

## 0.9.8

### FIXED

- Bulk import could trigger with 0 documents, causing an error.

## 0.9.7

### FIXED

- Fix SearchSubscriber failing to delete documents from index on entity deletion

## 0.9.6

### CHANGED

- Add Symfony 6.x to supported versions (actual support not guaranteed yet)

## 0.9.5

### FIXED

- SearchSubscriber no longer checks `isIndexable` when deleting Page entities.

## 0.9.4

### FIXED

- Fix error in pages:reindex command when indexing fewer pages than the bulk_index_threshold

## 0.9.3

### CHANGED

- Fix page list endpoint

## 0.9.2

## CHANGED

- Uses the official typesense client instead of making its own http calls. This adds retries, multi-server support, etc.

## 0.9.1

### NEW

- Defined `Tui\PageBundle\Entity\IsIndexableInterface`. Implement this in your Page entity to control whether a page is indexed or not. For convenience, AbstractPage implements this so you can simply override the `function isIndexable(): bool` method.

## 0.9.0

### BREAKING

Read [`UPGRADE-0.9.md`](./UPGRADE-0.9.md) for instructions on how to upgrade to this release.

- Search now uses Typesense instead of ElasticSearch. You'll need to set up a Typesense service and update your config and search transformer(s).
- If you've got any custom query classes, they'll need to be rewritten.
- The search functionality no longer indexes all page content. You must explicitly add the searchable content from your page components and metadata to the index document using a search transformer.
- `Tui\PageBundle\Search\TransformerInterface` has changed. The `transform()` method has been replaced with `transformDocument()` and a new `transformSchema()` method allows you to modify the Typesense index mapping during a reindex.
- Typesense currently has no "did you mean" functionality, so this has been removed from the search result output.
- UUID ids for AbstractPage and AbstractPageData are now generated by the symfony/uid component, and `json_array` properties have been changed to `json`. You will need to generate and run a doctrine diff migration. This is to add (optional!) support for doctrine/dbal version 3. See UPGRADE-0.9.md for details.
- The `Tui\PageBundle\Search\TranslatedPage` class which represented an ElasticSearch document is now an array representing a Typesense document. If you extended this class to add fields, use your search transformer instead (both `transformSchema()` to define the field, and `transformDocument()` to set it).
- `Tui\PageBundle\Search\TranslatedPageFactory->createFromPage` has been replaced with `Tui\PageBundle\Search\TypesenseClient->createSearchDocument()`

### NEW

- `Tui\PageBundle\Search\TypesenseClient` is a new search index and indexing client

### CHANGED

- Fix various deprecations in PHP 8.1 and Symfony 5.4

## 0.8.15

### CHANGED

- XLIFF translation export no longer includes empty or non-string values
- XLIFF translation import now initialises the target language with a copy of the default language langData

## 0.8.14

### NEW

- Added (e.g.) `valid_languages: ['en_GB', 'fr']` parameter to the config so you can reject unexpected translations.

### CHANGED

- Downgraded some bulk indexing messages from `info` to `debug`

## 0.8.13

### FIXED

- Bump http-foundation minimum version to 4.x or 5.0.4

## 0.8.12

### CHANGED

- A new `bulk_index_threshold` config setting allows you to set the number of pages in each indexing batch. The default is now 50.

## 0.8.11

### CHANGED

- The `pages:reindex` command now performs bulk indexing of 20 pages at a time for better performance.

## 0.8.10

### CHANGED

- The XLIFF import endpoint with the destination "original" will create a new revision

## 0.8.9

### CHANGED

- Include default & available languages in page lists
- Component schema docs have been moved into this package from @tuimedia/vue-page

## 0.8.8

### FIXED

- Disabled broken cache headers & checking in PageController::retrieve
- Fixed deprecated (and then broken) SearchSubscriber argument type

## 0.8.7

### FIXED

- Fix deprecation in checkTuiPagePermissions

## 0.8.6

### FIXED

- Exceptions thrown within the SearchSubscriber are no longer fatal. Instead they're logged as errors and execution continues.

## 0.8.5

I forgot how to count, I guess. This version never existed.

## 0.8.4

### FIXED

- Implement missing `search` permission check

## 0.8.3

### NEW

- A new `access_control` configuration array contains roles to check before performing each of the write actions on the API. This works in addition to the existing advice to set up an `access_control` rule on the security component. See the README for details on how to configure this.

## 0.8.1

### FIXED

- Fixed an exception in PageSchema while trying to validate a block with no langData in the default language.

## 0.8

### BREAKING

- The data format has changed:
  - `PageData.content` has an additional integer property: `schemaVersion`. If not provided, the old format is assumed.
  - `PageData.content.layout` is now an array of block ids. Layout blocks were always another kind of block, so keeping them together makes sense and reduces the amount of code.

### NEW

- A `pages:upgrade` command to migrate content from the old format to the new. This command is repeatable without problems, so you can make it part of your deployments. BACK UP YOUR DATABASE BEFORE RUNNING THIS.
