<?php

namespace Tui\PageBundle;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class PageSchema
{
    /** @var string[] */
    protected $schemas;

    /** @var string */
    protected $schemaPath = __DIR__ . '/Resources/schema/tui-page.schema.json';

    public function __construct(
        array $componentSchemas,
        /** @var iterable<SanitizerInterface> $customSanitizers */
        #[AutowireIterator('tui_page.sanitizer')]
        private readonly iterable $customSanitizers = [],
    ) {
        $this->schemas = $componentSchemas;
    }

    public function validate(string $data): ?array
    {
        $data = json_decode($data, null, 512, JSON_THROW_ON_ERROR);

        $result = $this->createValidator()->validate($data, $this->decodeSchema($this->schemaPath));

        if ($result->hasError()) {
            return $this->formatSchemaErrors([$result->error()]);
        }

        // Validate components against their schemas. Each file gets its own validator, because opis
        // caches schemas by $id and would check a copy-pasted schema against the original
        /** @var array<string, array{Validator, object}> $componentValidators */
        $componentValidators = [];
        foreach ($data->pageData->content->blocks as $block) {
            if (!array_key_exists($block->component, $this->schemas)) {
                return $this->formatSchemaErrors([
                    sprintf('No schema configured for component "%s"', $block->component),
                ]);
            }

            foreach ($block->languages as $language) {
                // Build the block by overlaying default language data and this language data
                $resolvedBlock = $this->resolveBlockForLanguage($data, $block->id, $language);

                // Check resulting object against the component schema
                try {
                    [$validator, $schema] = $componentValidators[$this->schemas[$block->component]] ??= [
                        $this->createValidator(),
                        $this->getSchemaObjectForBlock($resolvedBlock),
                    ];
                } catch (\Exception $e) {
                    return $this->formatSchemaErrors([$e->getMessage()]);
                }
                $result = $validator->validate($resolvedBlock, $schema);
                if ($result->hasError()) {
                    return $this->formatSchemaErrors([$result->error()], $resolvedBlock, $language);
                }
            }
        }

        return null;
    }

    private function createValidator(): Validator
    {
        $validator = new Validator();

        // Sanitising handles these, so for validation they're just strings
        $mediaTypes = $validator->parser()->getMediaTypeResolver();
        if ($mediaTypes) {
            $mediaTypes->registerCallable('text/html', static fn (): bool => true);
            foreach ($this->customSanitizers as $sanitizer) {
                $mediaTypes->registerCallable($sanitizer->getMediaType(), static fn (): bool => true);
            }
        }

        return $validator;
    }

    public function getSchemaObjectForBlock(\stdClass $block): object
    {
        if (!array_key_exists($block->component, $this->schemas)) {
            throw new \Exception(vsprintf('No schema defined for component %s', [$block->component]));
        }

        if (!file_exists($this->schemas[$block->component])) {
            throw new \Exception(vsprintf('Component schema for %s defined but not found', [$block->component]));
        }

        return $this->decodeSchema($this->schemas[$block->component]);
    }

    public function getSchemaForBlock(\stdClass $block): object
    {
        return $this->deepResolveSchema($this->getSchemaObjectForBlock($block));
    }

    protected function resolveBlockForLanguage(\stdClass $data, string $id, string $language): \stdClass
    {
        $resolvedBlock = new \stdClass();
        $defaultLang = $data->pageData->defaultLanguage;

        foreach ($data->pageData->content->blocks->$id as $prop => $value) {
            $resolvedBlock->$prop = $value;
        }

        if (isset($data->pageData->content->langData->$defaultLang->$id)) {
            foreach ($data->pageData->content->langData->$defaultLang->$id as $prop => $value) {
                $resolvedBlock->$prop = $value;
            }
        }

        if ($language === $defaultLang) {
            return $resolvedBlock;
        }

        if (
            !isset($data->pageData->content->langData->$language)
            || !isset($data->pageData->content->langData->$language->$id)
        ) {
            return $resolvedBlock;
        }

        foreach ($data->pageData->content->langData->$language->$id as $prop => $value) {
            $resolvedBlock->$prop = $value;
        }

        return $resolvedBlock;
    }

    private function formatSchemaErrors(array $errors, ?object $block = null, ?string $language = null): array
    {
        $error = [
            'type' => 'https://tuimedia.com/tui-page/errors/validation',
            'title' => 'Validation failed',
            'detail' => '',
            'errors' => [],
        ];

        if ($block) {
            if (!property_exists($block, 'id')) {
                throw new \Exception('Invalid block, no id');
            }
            $error['detail'] = sprintf('Component %s in language %s: ', $block->id, $language);
            $error['component'] = $block;
        }

        $error['errors'] = array_map(function ($error) {
            if (!$error instanceof ValidationError) {
                return $error;
            }

            // opis nests the failure under the keywords that led to it (properties, items, $ref…), so report the innermost one
            while ($error->subErrors()) {
                $error = $error->subErrors()[0];
            }

            return [
                'path' => implode('.', $error->data()->fullPath()),
                'keyword' => $error->keyword(),
                'keywordArgs' => $this->formatKeywordArgs($error),
            ];
        }, $errors);

        $error['detail'] .= implode('. ', array_map(fn ($error) => is_array($error) ? sprintf('[%s]: invalid %s.', $error['path'], $error['keyword']) : $error, $error['errors']));

        return $error;
    }

    /**
     * Keep the keywordArgs API clients already read, adding anything new opis reports.
     */
    private function formatKeywordArgs(ValidationError $error): array
    {
        $args = $error->args();

        return match ($error->keyword()) {
            'enum' => ['expected' => $error->schema()->info()->data()->enum ?? []] + $args,
            'type' => ['expected' => $args['expected'] ?? null, 'used' => $args['type'] ?? null],
            'required' => ['missing' => $args['missing'][0] ?? null],
            default => $args,
        };
    }

    public function getResolvedPageSchema(): object
    {
        return $this->deepResolveSchema($this->decodeSchema($this->schemaPath));
    }

    protected function deepResolveSchema(object $schema, ?object $rootSchema = null): object
    {
        if (!$rootSchema) {
            $rootSchema = $schema;
        }

        foreach ((array) $schema as $prop => $value) {
            if (is_object($value) && isset($value->{'$ref'})) {
                $schema->$prop = $this->resolvePointer($rootSchema, (string) $value->{'$ref'});
            } elseif (is_object($value)) {
                $schema->$prop = $this->deepResolveSchema($value, $rootSchema);
            }
        }

        return $schema;
    }

    /**
     * Follow a local reference such as #/definitions/link.
     */
    private function resolvePointer(object $rootSchema, string $ref): mixed
    {
        if (!str_starts_with($ref, '#')) {
            throw new \RuntimeException(sprintf('Only local schema references are supported, got %s', $ref));
        }

        $value = $rootSchema;
        foreach (array_filter(explode('/', substr($ref, 1)), static fn (string $part): bool => $part !== '') as $part) {
            $part = strtr(rawurldecode($part), ['~1' => '/', '~0' => '~']);
            if (is_object($value) && property_exists($value, $part)) {
                $value = $value->$part;
            } elseif (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                throw new \RuntimeException(sprintf('Unable to resolve schema reference %s', $ref));
            }
        }

        return $value;
    }

    private function decodeSchema(string $path): object
    {
        $schema = json_decode((string) file_get_contents($path), null, 512, JSON_THROW_ON_ERROR);
        if (!is_object($schema)) {
            throw new \RuntimeException(sprintf('Schema %s is not a JSON object', $path));
        }

        return $schema;
    }
}
