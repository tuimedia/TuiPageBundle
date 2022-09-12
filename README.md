# TuiPageBundle

An API for managing rich, versioned, multilingual content.

[TOC]

## Requirements

* Symfony 4 or 5
* Doctrine ORM 2
* Typesense 0.22.1

## Installation

* Add & enable the bundle. This isn't (yet?) available on packagist, so you'll have to add our satis repository to your `composer.json`:

```json
{
    "type": "project",
    "repositories": [{
      "type": "vcs",
      "url": "https://bitbucket.org/tui/tuipagebundle.git"
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

* Set up access control. By default the edit, create, delete, history, and import routes require ROLE_ADMIN. See below for how to change this or provide secure fallbacks.

* Edit `config/packages/tui_page.yaml` to configure your component schemas and the search engine. Search is disabled by default; set at least one search host to enable it.

## Securing endpoints

The default configuration requires that the user have the ROLE_ADMIN role to access any of the write endpoints. You can configure different role (or roles) for each endpoint in `config/packages/tui_page.yaml`. The default parameters are shown below:

```yaml
tui_page:
  access_control:
    list: []
    retrieve: []
    export: []
    search: []
    edit: [ROLE_ADMIN]
    history: [ROLE_ADMIN]
    create: [ROLE_ADMIN]
    import: [ROLE_ADMIN]
    delete: [ROLE_ADMIN]
```

You can use any role or permission, including ones you create [custom voters](https://symfony.com/doc/current/security/voters.html) for. Wherever a page object exists, it is passed as the subject to the voter ('list' and 'search' don't have page objects)

You can also use `access_control` rules in your `config/packages/security.yaml` file as an alternative or fallback to secure any new endpoints:

```yaml
security:
    access_control:
        - { path: ^/api/pages, methods: [PUT, POST, DELETE], roles: [ROLE_ADMIN] }
        - { path: ^/api/translations, methods: [PUT, POST, DELETE], roles: [ROLE_ADMIN] }
```

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
* `GET /search` - `pageList`

## Adding custom serializer groups

You can provide additional serializer groups that TuiPageBundle uses for serializing and deserializing in its controllers. Edit `config/packages/tui_page.yaml`:

```yaml
tui_page:
    serializer_groups:
        list_response: ['myPageList']
        search_response: ['myPageSearch']
        get_response: ['myPageGet']
        history_response: ['myPageHistory']
        create_request: ['myPageCreate']
        create_response: ['myPageCreateResponse']
        update_request: ['myPageUpdate']
        update_response: ['myPageUpdate']
```

## Creating custom controllers

You may find yourself needing to return serialized TuiPage objects from your own controllers. To ensure consistent output, consider using the `Tui\PageBundle\Controller\TuiPageResponseTrait` and calling `$this->generateTuiPageResponse($page, $serializer, $groups = [], $statusCode = 200)` to create your response object.

The most notable thing this method does is to assert the type of certain object properties when they happen to be empty (`$.pageData.content.langData`, `$.pageData.content.blocks` and the `styles` property of any block).

## Content components

Every content component you create for the frontend must have a JSON Schema file describing its contents. The schema is used by TuiPageBundle to validate and sanitise its content. If you don't define a schema, that component will not be validated or sanitised beyond the required fields, so… define a schema!

A generic schema exists for all content components, so you don't need to (and shouldn't) include `id`, `component`, `languages` or `styles` in your schema file.

```yaml
tui_page:
  components:
    PageText:
      schema: '%kernel.project_dir%/public/schemas/PageText.schema.json'
    PageImage:
      schema: '%kernel.project_dir%/public/schemas/PageImage.schema.json'
```

An example schema for the minimal component might look like this:

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "http://api.example.com/MyGreeting.schema.json#",
  "type": "object",
  "properties": {
    "salutation": {
      "title": "Optional salutation",
      "type": "string",
      "maxLength": 1024
    },
    "greeting": {
      "title": "HTML greeting text",
      "type": "string",
      "contentMediaType": "text/html",
      "minLength": 1,
      "maxLength": 10240
    }
  },
  "required": [
    "greeting"
  ]
}
```

Check the [JSON Schema specification](https://json-schema.org/latest/json-schema-validation.html) for options. Use the `"contentMediaType": "text/html"` to allow (some) HTML in your fields. The [JSON Schema Validator](https://www.jsonschemavalidator.net) is pretty handy too.

### Transforming content for search

During indexing, pages are transformed into an intermediate format that's better suited for search indexing. A JSON document is created for each language version of a page. To add the searchable text from your components to this document, define a transformer to hook into this process.

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

class AppSearchTransformer implements TransformerInterface
{
  public function transformSchema(array $config): array
  {
    // You can define new fields for the searchable document here
    // For example if you'd like to facet search results by tags
    $config['fields'][] = [ 'name' => 'tags', 'type' => 'string[]', 'facet' => true];
    return $config;
  }

  public function transformDocument(array $translatedPage, PageInterface $page, string $language): array
  {
    // Set custom fields
    $translatedPage['tags'] = $page->getTags()->map(function ($tag) {
      return $tag->getSlug();
    })->toArray();

    // Add the content of your PageText blocks
    $content = $page->getPageData()->getContent();
    $langData = $content['langData'][$language] ?? [];

    foreach ($content['blocks'] as $blockId => $data) {
      if ($data['component'] === 'PageText') {
        $blockText = $langData[$blockId] ?? [];
        $translatedPage['searchableText'][] = $blockText['title'] ?? null;
        $translatedPage['searchableText'][] = html_entity_decode((string) strip_tags($blockText['copy'] ?? ''), ENT_QUOTES);
      }
    }

    return $translatedPage;
  }
}
```

## Excluding pages from the bulk reindex command

The `pages:reindex` command fetches all pages by default. By overriding the bundle's PageRepository class with your own, you can replace either the `getQueryForIndexing` method, or the `getPagesForIndexing` method that calls it. In this example, pages matching a URL pattern are excluded, and custom tags are included:

```php
public function getQueryForIndexing(): Query
    {
        $qb = $this->createQueryBuilder('p');
        return $qb
            ->select('p, pd, pt, t')
            ->join('p.pageData', 'pd')
            ->leftJoin('p.pageTags', 'pt')
            ->leftJoin('pt.tag', 't')
            ->where($qb->expr()->notLike('p.slug', ':pattern'))
            ->setParameter('pattern', 'landing-%')
            ->getQuery();
    }
```

To have your class provided to the indexer, override the bundle's repository service definition:

```yaml
# app/config/services.yaml
services:
  # ...
      Tui\PageBundle\Repository\PageRepository:
        class: App\Repository\PageRepository
        arguments:
            $pageClass: '%tui_page.page_class%'
```

## Excluding pages from the search subscriber

If your Page entity doesn't extend `Tui\PageBundle\Entity\AbstractPage` then update it to implement both `Tui\PageBundle\Entity\IsIndexableInterface`.

Now define the `isIndexable(): bool` method in your entity. If this method returns false, the page won't be indexed.

## Translation

XLIFF import and export endpoints exist for each page. This documentation needs fleshing out. TranslationController is annotated with API Doc comments, which is a good place to start.

* `GET /translations/{slug}/{lang}`
* `PUT /translations/{slug}`

### Limiting target languages

This applies to both the export and import endpoints.

```yaml
tui_page:
    valid_languages: ['en_GB', 'fr', 'es']
```

## Notes and known issues

* slugs are globally unique
* Theoretically you can reuse a block in multiple rows, but don't - the frontend renders the block id as the DOM ID attribute so that browsers can scroll to a piece of content, so you'll end up with invalid HTML and that functionality will break.
* JSON Schema allows `type` to be an array of acceptable types, but the sanitizer only supports single values, or an array of one value plus `null` (e.g. `["string", "null"]`). Rather than allow potentially dangerous input through, or mangling input, the sanitizer throws an exception when it finds a type array that it can't handle.

## Filtering & Validation

Page input (add/edit) is validated through a JSON Schema defined in `Resources/schema/tui-page.schema.json`. There are *also* Symfony validation rules applied as `@Assert/…` annotations on the `Page` and `PageData` entities.

Validation and sanitising of your content blocks is applied using the JSON Schema files from your configuration. Make sure you define all the properties on your content components EXCEPT for those already checked by the overall page schema: `id`, `component`, `languages` and `styles`.

The default string filter removes all HTML (it uses `strip_tags()` under the hood, so it might also remove HTML characters like < entirely). If you need HTML, set a `"contentMediaType": "text/html"` property in the schema for the desired field and an anti-xss filter will be applied instead.

There's no schema for the metadata section, so it's all recursively cleaned by the default string filter.
