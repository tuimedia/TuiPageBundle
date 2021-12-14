# Upgrading from 0.8 to 0.9

PageBundle 0.9 uses Typesense instead of ElasticSearch, and provides support for doctrine/dbal versions 2 and 3. There are a few significant differences between the way these services work which unfortunately means some work for you.

## Entities

In order to support Doctrine DBAL 3.x, the obsolete UUID generator strategy has been replaced with a custom UUID generator supplied by the Symfony UID component, and the deprecated `json_array` data type has been replaced by the (as far as I can tell) identical `json` type. You'll need to generate and run a diff migration in your project. You should consider changing the deprecated `json_array` type to `json` in all your entities.

Upgrading doctrine/dbal is optional! If your project was created with dbal version 2, you don't need to upgrade it to keep using TuiPageBundle.
