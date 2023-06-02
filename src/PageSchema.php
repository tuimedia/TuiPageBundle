<?php
namespace Tui\PageBundle;

use Opis\JsonSchema\JsonPointer;
use Opis\JsonSchema\MediaTypeContainer;
use Opis\JsonSchema\MediaTypes\Text;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationError;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;

class PageSchema
{
    /** @var string[] */
    protected $schemas;

    /** @var string */
    protected $schemaPath = __DIR__ . '/Resources/schema/tui-page.schema.json';

    public function __construct(array $componentSchemas)
    {
        $this->schemas = $componentSchemas;
    }

    public function validate(string $data): ?array
    {
        $data = json_decode($data, null, 512, JSON_THROW_ON_ERROR);
        $schema = Schema::fromJsonString((string) file_get_contents($this->schemaPath));

        $validator = new Validator();
        // omg hax, just use the existing plain text type for html
        $mediaType = $validator->getMediaType();
        if ($mediaType instanceof MediaTypeContainer) {
            $mediaTypes = $mediaType->add('text/html', new Text());
        }

        /** @var ValidationResult $result */
        $result = $validator->schemaValidation($data, $schema);

        if ($result->hasErrors()) {
            return $this->formatSchemaErrors($result->getErrors());
        }

        // Validate components against their schemas
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
                    $schema = $this->getSchemaObjectForBlock($resolvedBlock);
                } catch (\Exception $e) {
                    return $this->formatSchemaErrors([$e->getMessage()]);
                }
                $result = $validator->schemaValidation($resolvedBlock, $schema);
                if ($result->hasErrors()) {
                    return $this->formatSchemaErrors($result->getErrors(), $resolvedBlock, $language);
                }
            }
        }

        return null;
    }

    public function getSchemaObjectForBlock(\stdClass $block): Schema
    {
        if (!array_key_exists($block->component, $this->schemas)) {
            throw new \Exception(vsprintf('No schema defined for component %s', [$block->component]));
        }

        if (!file_exists($this->schemas[$block->component])) {
            throw new \Exception(vsprintf('Component schema for %s defined but not found', [$block->component]));
        }

        return Schema::fromJsonString((string) file_get_contents($this->schemas[$block->component]));
    }

    public function getSchemaForBlock(\stdClass $block): object
    {
        $schema = $this->getSchemaObjectForBlock($block);

        return $this->deepResolveSchema($schema->resolve());
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

    private function formatSchemaErrors(array $errors, object $block = null, string $language = null): array
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

        $error['errors'] = array_map(fn ($error) => $error instanceof ValidationError ? [
            'path' => implode('.', $error->dataPointer()),
            'keyword' => $error->keyword(),
            'keywordArgs' => $error->keywordArgs(),
        ] : $error, $errors);

        $error['detail'] = implode('. ', array_map(fn ($error) => is_array($error) ? sprintf('[%s]: invalid %s.', $error['path'], $error['keyword']) : $error, $error['errors']));

        return $error;
    }

    public function getResolvedPageSchema(): object
    {
        $schema = Schema::fromJsonString((string) file_get_contents($this->schemaPath));
        $resolvedSchema = $this->deepResolveSchema($schema->resolve());

        return $resolvedSchema;
    }

    protected function deepResolveSchema(object $schema, object $rootSchema = null): object
    {
        if (!$rootSchema) {
            $rootSchema = $schema;
        }

        foreach ((array) $schema as $prop => $value) {
            if (is_object($value) && isset($value->{'$ref'})) {
                $schema->$prop = JsonPointer::getDataByPointer($rootSchema, substr((string) $value->{'$ref'}, 1));
            } elseif (is_object($value)) {
                $schema->$prop = $this->deepResolveSchema($value, $rootSchema);
            }
        }

        return $schema;
    }
}
