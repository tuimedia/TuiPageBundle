<?php
namespace Tui\PageBundle;

use Opis\JsonSchema\{
    JsonPointer, Validator, ValidationResult, ValidationError, Schema
};
use Opis\JsonSchema\MediaTypes\Text;

class PageSchema
{
    protected $schemas;
    protected $schemaPath = __DIR__.'/Resources/schema/tui-page.schema.json';

    public function __construct($componentSchemas) {
        $this->schemas = $componentSchemas;
    }

    public function validate($data)
    {
        $data = json_decode($data);
        $schema = Schema::fromJsonString((string) file_get_contents($this->schemaPath));

        $validator = new Validator();
        // omg hax, just use the existing plain text type for html
        $mediaType = $validator->getMediaType();
        if ($mediaType instanceof \Opis\JsonSchema\MediaTypeContainer) {
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
                $schema = $this->getSchemaObjectForBlock($resolvedBlock);
                $result = $validator->schemaValidation($resolvedBlock, $schema);
                if ($result->hasErrors()) {
                    return $this->formatSchemaErrors($result->getErrors(), $resolvedBlock, $language);
                }
            }
        }
    }

    public function getSchemaObjectForBlock(\stdClass $block)
    {
        if (!array_key_exists($block->component, $this->schemas)) {
            return $this->formatSchemaErrors([
                sprintf('No schema configured for component "%s"', $block->component),
            ]);
        }

        if (!file_exists($this->schemas[$block->component])) {
            throw new \Exception(vsprintf('%s Component schema not found', [
                $block->component,
            ]));
        }

        return Schema::fromJsonString((string) file_get_contents($this->schemas[$block->component]));
    }

    public function getSchemaForBlock(\stdClass $block)
    {
        $schema = $this->getSchemaObjectForBlock($block);

        return $this->deepResolveSchema($schema->resolve());
    }

    protected function resolveBlockForLanguage($data, $id, $language)
    {
        $resolvedBlock = new \stdClass;
        $defaultLang = $data->pageData->defaultLanguage;

        foreach ($data->pageData->content->blocks->$id as $prop => $value) {
            $resolvedBlock->$prop = $value;
        }

        foreach ($data->pageData->content->langData->$defaultLang->$id as $prop => $value) {
            $resolvedBlock->$prop = $value;
        }

        if ($language === $defaultLang) {
            return $resolvedBlock;
        }

        if (
            !isset($data->pageData->content->langData->$language) ||
            !isset($data->pageData->content->langData->$language->$id)
        ) {
            return $resolvedBlock;
        }

        foreach ($data->pageData->content->langData->$language->$id as $prop => $value) {
            $resolvedBlock->$prop = $value;
        }

        return $resolvedBlock;
    }

    private function formatSchemaErrors($errors, $block = null, $language = null)
    {
        $error = [
            'type' => 'https://tuimedia.com/tui-page/errors/validation',
            'title' => 'Validation failed',
            'detail' => '',
            'errors' => [],
        ];

        if ($block) {
            $error['detail'] = sprintf('Component %s in language %s: ', $block->id, $language);
            $error['component'] = $block;
        }

        $error['errors'] = array_map(function ($error) {
            return $error instanceof ValidationError ? [
                'path' => implode('.', $error->dataPointer()),
                'keyword' => $error->keyword(),
                'keywordArgs' => $error->keywordArgs(),
            ] : $error;
        }, $errors);

        $error['detail'] = implode('. ', array_map(function ($error) {
            return is_array($error) ? sprintf('[%s]: invalid %s.', $error['path'], $error['keyword']) : $error;
        }, $error['errors']));

        return $error;
    }

    public function getResolvedPageSchema()
    {
        $schema = Schema::fromJsonString((string) file_get_contents($this->schemaPath));
        $resolvedSchema = $this->deepResolveSchema($schema->resolve());

        return $resolvedSchema;
    }

    protected function deepResolveSchema(object $schema, $rootSchema = null)
    {
        if (!$rootSchema) {
            $rootSchema = $schema;
        }

        foreach ((array) $schema as $prop => $value) {
            if (is_object($value) && isset($value->{'$ref'})) {
                $schema->$prop = JsonPointer::getDataByPointer($rootSchema, substr($value->{'$ref'}, 1));
            } elseif (is_object($value)) {
                $schema->$prop = $this->deepResolveSchema($value, $rootSchema);
            }
        }

        return $schema;
    }
}
