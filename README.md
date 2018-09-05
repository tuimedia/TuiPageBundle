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

* Set up access control on your `security.yml`. You can dump the available routes with `bin/console debug:router | grep tui_page`. This is not done by the bundle because roles and permissions may vary between apps.

## Notes

* adding/removing elements from a page is versioned, but renaming/deleting elements is not
* slugs are globally unique
* For the pageData `pageRef` property to be useful, it should be unique for all versions of a single document. You can use a UUID or the page slug, it doesn't matter.
