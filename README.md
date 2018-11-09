# TuiPageBundle

An API for managing rich, versioned, multilingual content.

## Requirements

* Symfony 4.1
* Doctrine ORM
* ElasticSearch 5 (optional as long as you don't want search)

## Installation

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

* Set up access control on your `security.yml`. You can dump the available routes with `bin/console debug:router | grep tui_page`. This is not done by the bundle because roles and permissions may vary between apps.

## Setting up the entities

TuiPageBundle uses two Doctrine ORM entities to represent your pages. A `PageData` entity that describes the content of a revision of a page, and a `Page` entity that maps a URL and namespace to a `PageData` revision. The bundle provides two interfaces and abstract versions of these classes. To use them, create concrete representations of the abstract classes in your app, then add them to the bundle configuration. You can use your classes to add extra fields and relations (for instance tags).

* Create an entity that extends `Tui\PageBundle\Entity\AbstractPage`:

```php
namespace App\Entity;

use Tui\PageBundle\Entity\AbstractPage;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Tui\PageBundle\Repository\PageRepository")
 * @ORM\Table(name="page")
 */
class Page extends AbstractPage {}
```

* Create an entity that extends `Tui\PageBundle\Entity\AbstractPageData`:

```php
namespace App\Entity;

use Tui\PageBundle\Entity\AbstractPageData;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Tui\PageBundle\Repository\PageDataRepository")
 * @ORM\Table(name="page_data")
 */
class PageData extends AbstractPageData {}
```

* Configure the Page -> PageData relation override:

```yaml
doctrine:
    orm:
        # ...
        resolve_target_entities:
            Tui\PageBundle\Entity\PageDataInterface: App\Entity\PageData
```

* If you name your entities anything other than `App\Entity\Page` and `App\Entity\PageData`, then name them in the configuration:

```yaml
tui_page:
  page_class: App\Entity\Page
  page_data_class: App\Entity\PageData
```

* Run migrations to add the required tables:

```sh
bin/console make:migration
bin/console doctrine:migrations:migrate
```


## Exposing your custom properties in API calls

The advantage of extending the `AbstractPage` and `AbstractPageData` is that you can add your own properties and methods. If you want these to appear in the serialized output of the bundle API calls, annotate the properties or methods you want to serialize with the `@Groups()` annotation. Each kind of view has its own serializer group so you can decide what to show and when.

Available serializer groups:

* `pageList`
* `pageGet`
* `pageCreate`

API calls and their serializer groups:

* `GET /pages` - `pageList`
* `POST /pages` - `pageCreate` (for deserializing), `pageGet` (for the response)
* `GET /pages/{slug}` - `pageGet`
* `GET /pages/{slug}/history` - `pageGet`
* `PUT /pages/{slug}` - `pageCreate` (for deserializing), `pageGet` (for the response)

## Content components

Every content component you create for the frontend should have a JSON Schema file describing its contents. The schema is used by TuiPageBundle to validate and sanitise its content. If you don't define a schema, that component will not be validated or sanitised beyond the required fields, so… define a schema!

You can also optionally supply [ElasticSearch mapping configuration](https://www.elastic.co/guide/en/elasticsearch/reference/5.6/mapping.html) for your component.

```yaml
tui_page:
  components:
    PageText:
      schema: '%kernel.project_root%/public/schemas/PageText.schema.json'
    PageImage:
      schema: '%kernel.project_root%/public/schemas/PageImage.schema.json'
      mapping:
        type: object
        properties:
          url: { enabled: false }
```

### Transforming content for search

During indexing, pages are transformed into an intermediate format that's better suited for search indexing. A `Tui\PageBundle\Search\TranslatedPage` instance is created for each language version of a page. You can define a transformer to hook into this process to modify the translated content before it's indexed, for instance to inject video transcripts, or to remove HTML formatting. The original page object is also included so that you can, for instance, add extra properties from your page object (like tags).

Transformers must implement `Tui\PageBundle\Search\TransformerInterface`, and be tagged with the `tui_page.transformer` tag, which you can do automatically in your `services.yml`:

```yaml
services:
    # this config only applies to the services created by this file
    _instanceof:
        Tui\PageBundle\Search\TransformerInterface:
            tags: ['tui_page.transformer']
```

```php
namespace App\SearchTransformer;

use Tui\PageBundle\Search\TransformerInterface;
use Tui\PageBundle\Entity\PageInterface;

class HtmlBlockTransformer implements TransformerInterface
{
  public function transform(TranslatedPage $translatedPage, ?PageInterface $page)
  {
    if (!isset($translatedPage->types['HtmlBlock'])) {
      return;
    }

    foreach ($translatedPage->types['HtmlBlock'] as $idx => $block) {
      $block['html'] = strip_tags($block['html']);
      $translatedPage->types['HtmlBlock'][$idx] = $block;
    }

    return $translatedPage;
  }
}
```


## Notes

* slugs are globally unique
* For the pageData `pageRef` property to be useful, it should be unique for all versions of a single document. You can use a UUID or the page slug, it doesn't matter.
* Theoretically you can reuse a block in multiple rows, but don't - the frontend renders the block id as the DOM ID attribute so that browsers can scroll to a piece of content, so you'll end up with invalid HTML and that functionality will break.

## Filtering & Validation

Page input (add/edit) is validated through a JSON Schema defined in `Resources/schema/tui-page.schema.json`. There are *also* Symfony validation rules applied as `@Assert/…` annotations on the `Page` and `PageData` entities.

Validation and sanitising of your content blocks is applied using the JSON Schema files from your configuration. Make sure you define all the properties on your content components EXCEPT for those already checked by the overall page schema: `id`, `component`, `languages` and `styles`.

The default string filter removes all HTML (it uses `filter_var()` under the hood, so it might also remove HTML characters like < entirely). If you need HTML, set a `"contentMediaType": "text/html"` property in the schema for the desired field and an anti-xss filter will be applied instead.

