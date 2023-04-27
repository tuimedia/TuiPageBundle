<?php
namespace Tui\PageBundle;

use voku\helper\AntiXSS;

class Sanitizer
{
    private $antixss;
    private $pageSchema;

    public function __construct(AntiXSS $antixss, PageSchema $pageSchema)
    {
        $this->antixss = $antixss;
        $this->pageSchema = $pageSchema;
    }

    /**
     * Recursively clean a TuiPage JSON document according to both the TuiPage JSON Schema
     * and the custom schemas created for each content component. We rely on the schema
     * validation to assert content types, so there's no explicit casting, but string values
     * are filtered according to their content type (defaulting to text/plain, but allowing
     * some HTML when the content type is set to text/html).
     */
    public function cleanPage(string $rawContent): string
    {
        $data = json_decode($rawContent);

        // Get resolved tui-page schema (references collapsed)
        $resolvedSchema = $this->pageSchema->getResolvedPageSchema();

        $data = $this->cleanBySchemaRecursive($data, $resolvedSchema);

        // Clean blocks & associated langData according to custom schemas
        foreach ($data->pageData->content->blocks as $blockId => $block) {
            $blockSchema = $this->pageSchema->getSchemaForBlock($block);

            // Main block definition
            $data->pageData->content->blocks->$blockId = $this->cleanBySchemaRecursive($block, $blockSchema);

            // Sanitize any language overrides
            foreach ($data->pageData->content->langData as $languageCode => $langData) {
                if (isset($langData->$blockId)) {
                    $langData->$blockId = $this->cleanBySchemaRecursive($langData->$blockId, $blockSchema);
                }
            }
        }

        // Clean metadata
        $data->pageData->metadata = $this->stringCleanRecursive($data->pageData->metadata);
        foreach ($data->pageData->content->langData as $languageCode => $langData) {
            if (isset($langData->metadata)) {
                $langData->metadata = $this->stringCleanRecursive($langData->metadata);
            }
        }

        $json = json_encode($data);
        if (!$json) {
            throw new \RuntimeException('Unable to encode JSON output');
        }

        return $json;
    }

    public function cleanQuery(string $terms): string
    {
        // Count double-quotes and remove them all if they aren't paired
        if (substr_count($terms, '"') % 2) {
            $terms = str_replace('"', '', $terms);
        }

        // Sanitize query string
        $terms = (string) preg_replace('/[^\w \'\"\-\:\(\)&]/', '', $terms);
        $terms = (string) preg_replace('/[\:\(\)&-]+/', ' ', $terms);

        return $this->stringClean($terms);
    }

    /**
     * Simple recursive string cleaner - recurses through arrays & objects, applies filtering to strings only
     * Only good for, say, metadata.
     */
    private function stringCleanRecursive($data)
    {
        $dataIsArray = is_array($data);
        $dataIsObject = is_object($data);
        foreach ($data as $prop => $value) {
            if ($dataIsArray && is_string($value)) {
                $data[$prop] = $this->stringClean($value);
                continue;
            }

            if ($dataIsObject && is_string($value)) {
                $data->$prop = $this->stringClean($value);
                continue;
            }

            if (is_array($value) || is_object($value)) {
                if ($dataIsArray) {
                    $data[$prop] = $this->stringCleanRecursive($value);
                    continue;
                }
                $data->$prop = $this->stringCleanRecursive($value);
            }
        }

        return $data;
    }

    private function stringClean($value)
    {
        return html_entity_decode(
            (string) htmlspecialchars(strip_tags($value), ENT_NOQUOTES),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    private function cleanBySchemaRecursive($data, $schema)
    {
        foreach ($data as $prop => $value) {
            $propSchema = $schema->properties->{$prop} ?? null;

            // Look for a matching patternProperty schema
            if (!$propSchema && isset($schema->patternProperties)) {
                foreach ($schema->patternProperties as $pattern => $schema) {
                    $pattern = sprintf('!%s!', str_replace('!', '\!', $pattern));
                    if (preg_match($pattern, $prop)) {
                        $propSchema = $schema;
                        break;
                    }
                }
            }

            // Don't touch undescribed props (e.g. custom component props)
            if (!$propSchema) {
                continue;
            }

            $data->$prop = $this->cleanValue($value, $propSchema);
        }

        return $data;
    }

    // TODO: this assumes type is a single value, but arrays of valid types are also allowed in JSON Schema
    // There is a special case we do handle: where type is an array of (any type or null)
    private function cleanValue($value, $propSchema)
    {
        // If type isn't set but there's a `properties` prop, assume object, else assume string
        // Should really set a type though - might be smarter to throw an exception
        $type = $propSchema->type ?? (isset($propSchema->properties) || isset($propSchema->patternProperties) ? 'object' : 'string');

        // If the type is an array of (any type OR null), figure out which it is and handle it, else explode
        if (is_array($type) && count($type) === 2 && in_array('null', $type)) {
            $otherType = $type[0] === 'null' ? $type[1] : $type[0];
            $type = is_null($value) ? 'null' : $otherType;
        } elseif (is_array($type)) {
            throw new \RuntimeException('Schema defines multiple types, but the sanitizer does not support this yet.');
        }

        if ($type === 'null') {
            return null;
        }

        if ($type === 'number') {
            return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }

        if ($type === 'integer') {
            return intval($value);
        }

        if ($type === 'boolean') {
            return (bool) $value;
        }

        if ($type === 'string') {
            // Look for a content type - run HTML fields through antiXSS and everything else through filter_var
            if (isset($propSchema->contentMediaType) && $propSchema->contentMediaType === 'text/html') {
                return $this->antixss->xss_clean($value);
            }

            return html_entity_decode(
                (string) htmlspecialchars(strip_tags($value), ENT_NOQUOTES),
                ENT_QUOTES,
                'UTF-8'
            );
        }

        if ($type === 'object') {
            return $this->cleanBySchemaRecursive($value, $propSchema);
        }

        if ($type === 'array') {
            // TODO: array schemas can be complex and this assumes all items are the same
            $subSchema = $propSchema->items ?? false;
            if (!$subSchema) {
                return $value;
            }

            return array_map(function ($subValue) use ($subSchema) {
                return $this->cleanValue($subValue, $subSchema);
            }, $value);
        }
    }
}
