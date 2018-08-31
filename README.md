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

* Run migrations to add the required tables:

```sh
bin/console make:migration
bin/console doctrine:migrations:migrate
```

* If you want your pages to have tags, create an entity that extends `Tui\PageBundle\Entity\Element`, for example:

```php
<?php

namespace App\Entity;

use Tui\PageBundle\Entity\Element;

/**
 * @ORM\Entity()
 */
class Keyword extends Element
{
    protected $type = 'keyword';
}
```

**Note:** beware of adding non-nullable properties, because the inheritance type is single-table and so *all* your element types will require that property.

## Notes

* adding/removing elements from a page is versioned, but renaming/deleting elements is not
* slugs are globally unique
