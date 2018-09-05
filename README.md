# TuiPageBundle

An API for managing rich, versioned, multilingual content.

## Setup

* Add & enable the bundle. This isn't (yet?) available on packagist, so you'll have to add our satis repository to your `composer.json`:

```json
{
    "type": "project",
    "repositories": [{
      "type": "composer",
      "url": "https://useful.tuimedia.com:8082"
    }],
    "require": {
      "…": "etc"
    }
}
```

Then:

```sh
composer require tuimedia/page-bundle@dev-develop # Obviously don't get develop once stable releases are ready
```

* Add the routes, e.g. create `config\routes\page_bundle.yaml` (or edit your routes file, whatever):

```yaml
page_controllers:
    resource: "@TuiPageBundle/Controller/"
    type: annotation
    prefix: api
```

* Enable the property-info component in `config\packages\framework.yaml`:

```yaml
framework:
    #…
    property_info:
        enabled: true
```

* Run migrations to add the required tables:

```sh
bin/console make:migration
bin/console doctrine:migrations:migrate
```

## Notes

* adding/removing elements from a page is versioned, but renaming/deleting elements is not
* slugs are globally unique
