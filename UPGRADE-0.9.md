# Upgrading from 0.8 to 0.9

PageBundle 0.9 uses Typesense instead of ElasticSearch, and provides support for doctrine/dbal versions 2 and 3. There are a few significant differences between the way these services work which unfortunately means some work for you.

## TL;DR checklist

* Set up a typesense service.
* Set `search_api_key` in `config/packages/tui_page.yaml` (strongly consider using a secret or environment var as the value)
* Remove the `mapping` config option from each component in `config/packages/tui_page.yaml`
* If you set up a search transformer, replace its existing `transform` method with `transformSchema` and `transformDocument` methods. If you don't have one, I'm afraid it's time to make one; the transformer is now responsible for adding your components' content to the search document.
* If you extended the TranslatedPage class, you'll probably need to add fields with your transformer instead.
* Run `bin/console doctrine:migrations:diff`, check the resulting migration, then run it.
* If you had custom query classes, you'll need to replace them.
* If you call `Tui\PageBundle\Search\TranslatedPageFactory`'s `createFromPage()` method in your code, inject `Tui\PageBundle\Search\TypesenseClient` and use its `createSearchDocument()` method instead.
* Reindex your pages

## Entities

In order to support Doctrine DBAL 3.x, the obsolete UUID generator strategy has been replaced with a custom UUID generator supplied by the Symfony UID component, and the deprecated `json_array` data type has been replaced by the (as far as I can tell) identical `json` type. You'll need to generate and run a diff migration in your project. You should consider changing the deprecated `json_array` type to `json` in all your entities.

Upgrading doctrine/dbal is optional! If your project was created with dbal version 2, you don't need to upgrade it to keep using TuiPageBundle.

## Configuration

* There is a new `search_api_key` config parameter to fill into your `config/packages/tui_page.yaml` file
* The default value for `bulk_index_threshold` has changed to match Typesense's default of 40. Make sure yours makes sense given this.
* When configuring your components, there is now no `mapping` config. To include content from your components, create a class that implements `Tui\PageBundle\Search\TransformerInterface` and use its `transformDocument` method to add the searchable content to the `searchableText` array of strings. Use the `transformSchema` method to add facets and/or custom fields to the search document.

## Search

Typesense is a very different beast to Elasticsearc. Where Elasticsearch indexes deeply nested JSON documents, Typesense doesn't support nesting at all, only scalar values and arrays of scalar values. So the internal JSON document which represents a translated TuiPageBundle page inside the search engine has changed quite dramatically. The new document is radically more simple in structure:

```json
{
    "id": "bbf87b6b-eaae-4436-ae8d-74fa595a3b13",
    "revision": "95194f71-42f8-48a2-9a25-64c0db0d5b30",
    "slug": "my-page",
    "state": "live",
    "searchableText": [
        "some text",
        "lorem ipsum dolor sit amet"
    ]
}
```

TuiPageBundle no longer automatically adds your page's language data to this document. You'll need to define a search transformer and use its `transformDocument` method to add strings from your components to the `searchableText` array. Empty values are removed before indexing. Look at the README to see an example search transformer.

### Custom queries

Get the `Tui\PageBundle\Search\TypesenseClient` and call `search($indexName, $query)` to get the raw server response as an array. The fields of interest in the response are probably the `found` count of results, and the `hits` array of results.

### Extending TranslatedPage

The `Tui\PageBundle\Search\TranslatedPage` class which represented an ElasticSearch document is now an array representing a Typesense document. If you extended this class to add fields, use your search transformer instead (both `transformSchema()` to define the field, and `transformDocument()` to set it).
