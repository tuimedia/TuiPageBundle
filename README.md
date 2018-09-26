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
composer require tuimedia/page-bundle
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

* Define your component JSON schemas in `config\packages\tui_page.yaml`. These are used for validation and filtering:

```yaml
tui_page:
  components:
    PageImage: '%kernel.project_root%/public/schemas/PageImage.schema.json'
    PageText: '%kernel.project_root%/public/schemas/PageText.schema.json'
```

* Set up access control on your `security.yml`. You can dump the available routes with `bin/console debug:router | grep tui_page`. This is not done by the bundle because roles and permissions may vary between apps.

## Notes

* adding/removing elements from a page is versioned, but renaming/deleting elements is not
* slugs are globally unique
* For the pageData `pageRef` property to be useful, it should be unique for all versions of a single document. You can use a UUID or the page slug, it doesn't matter.
* Theoretically you can reuse a block in multiple rows, but don't - the frontend renders the block id as the DOM ID attribute so that browsers can scroll to a piece of content, so you'll end up with invalid HTML and that functionality will break.

## Filtering & Validation

Page input (add/edit) is validated through a JSON Schema defined in `Resources/schema/tui-page.schema.json`. There are *also* Symfony validation rules applied as `@Assert/…` annotations on the `Element`, `Page` and `PageData` entities.

Validation and sanitising of your content blocks is applied using the JSON Schema files from your configuration. Make sure you define all the properties on your content components EXCEPT for those already checked by the overall page schema: `id`, `component`, `languages` and `styles`.

The default string filter removes all HTML (it uses `filter_var()` under the hood, so it might also remove HTML characters like < entirely). If you need HTML, set a `"contentMediaType": "text/html"` property in the schema for the desired field and an anti-xss filter will be applied instead.

