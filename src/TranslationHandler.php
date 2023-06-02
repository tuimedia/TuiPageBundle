<?php
namespace Tui\PageBundle;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tui\PageBundle\Entity\PageInterface;

class TranslationHandler
{
    public function __construct(private readonly UrlGeneratorInterface $router, private readonly ?array $validLanguages = null)
    {
    }

    public function generateXliff(PageInterface $page, string $targetLanguage): string
    {
        $this->validateTargetLanguage($targetLanguage);

        $pageData = $page->getPageData();
        $content = $pageData->getContent();
        $sourceLangData = $content['langData'][$pageData->getDefaultLanguage()];
        $targetLangData = $content['langData'][$targetLanguage] ?? [];

        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = true;
        $doc->appendChild($root = $doc->createElement('xliff'));
        $root->setAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');
        $root->setAttribute('version', '1.2');
        $root->appendChild($file = $doc->createElement('file'));
        $file->setAttribute('original', $this->router->generate('tui_page_get', [
            'slug' => $page->getSlug(),
            'state' => $page->getState(),
            'revision' => $pageData->getRevision(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));

        $date = new \DateTime();
        $file->setAttribute('date', $date->format('Y-m-d\TH:i:s\Z'));

        $sourceLanguage = $pageData->getDefaultLanguage();
        if (!$sourceLanguage) {
            throw new \Exception('Unable to create translation file, page has no default language set');
        }
        $file->setAttribute('source-language', $this->convertLangCode($sourceLanguage));
        $file->setAttribute('target-language', $this->convertLangCode($targetLanguage));
        $file->setAttribute('datatype', 'plaintext');

        $file->appendChild($header = $doc->createElement('header'));
        $header->appendChild($tool = $doc->createElement('tool'));
        $tool->setAttribute('tool-id', 'TuiPageBundle');
        $tool->setAttribute('tool-name', 'TuiPageBundle');
        $tool->setAttribute('tool-version', sprintf('%.1f', TuiPageBundle::VERSION));

        $file->appendChild($body = $doc->createElement('body'));

        // Translatable metadata
        $this->addArrayRecursive($doc, $body, '[metadata]', $sourceLangData['metadata'] ?? [], $targetLangData['metadata'] ?? []);

        foreach ($content['layout'] as $rowId) {
            // Add row langdata if it exists
            if (array_key_exists($rowId, $sourceLangData)) {
                $this->addArrayRecursive($doc, $body, "[{$rowId}]", $sourceLangData[$rowId], $targetLangData[$rowId] ?? []);
            }

            $row = $content['blocks'][$rowId] ?? [];
            // Add block langdata
            foreach ($row['blocks'] ?? [] as $blockId) {
                if (!array_key_exists($blockId, $sourceLangData)) {
                    continue;
                }
                $this->addArrayRecursive($doc, $body, "[$blockId]", $sourceLangData[$blockId] ?? [], $targetLangData[$blockId] ?? []);
            }
        }

        return $doc->saveXML() ?: '';
    }

    private function addArrayRecursive(\DOMDocument $doc, \DOMElement $element, string $resPrefix, array $sourceValues, array $targetValues): void
    {
        foreach ($sourceValues as $key => $value) {
            if (is_array($value)) {
                $this->addArrayRecursive($doc, $element, vsprintf('%s[%s]', [
                    $resPrefix,
                    $key,
                ]), $value, $targetValues[$key] ?? []);
                continue;
            }

            // Skip non-string values
            if (!is_string($value) || !rtrim($value)) {
                continue;
            }

            $element->appendChild($unit = $doc->createElement('trans-unit'));
            $unit->setAttribute('resname', vsprintf('%s[%s]', [$resPrefix, $key]));
            $unit->setAttribute('id', hash('sha1', (string) $unit->getNodePath()));
            $unit->appendChild($source = $doc->createElement('source'));
            $unit->appendChild($target = $doc->createElement('target'));
            if (preg_match('/[<>&]/', $value)) {
                $source->appendChild($doc->createCDATASection($value));
                $target->appendChild($doc->createCDATASection($targetValues[$key] ?? ''));
            } else {
                $source->appendChild($doc->createTextNode($value));
                $target->appendChild($doc->createTextNode($targetValues[$key] ?? ''));
                if (preg_match("/\r\n|\n|\r|\t/", $value)) {
                    $source->setAttribute('xml:space', 'preserve');
                    $target->setAttribute('xml:space', 'preserve');
                }
            }
            $this->addNote($doc, $unit, $key);
        }
    }

    private function convertLangCode(string $code): string
    {
        return strtr($code, ['_' => '-']);
    }

    private function addNote(\DOMDocument $doc, \DOMElement $element, string $text): void
    {
        $element->appendChild($note = $doc->createElement('note'));
        $note->appendChild($doc->createTextNode($text));
    }

    public function importXliff(PageInterface $page, string $xliffData): void
    {
        // Load & check
        $previous = libxml_use_internal_errors(true);
        if (false === $doc = \simplexml_load_string($xliffData)) {
            libxml_use_internal_errors($previous);
            $libxmlError = libxml_get_last_error();
            throw new \RuntimeException(sprintf('Could not read XML source: %s', $libxmlError ? $libxmlError->message : '[no error message]'));
        }
        libxml_use_internal_errors($previous);

        // Register namespace(s)
        $doc->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');

        // Get the file tag for target language, revision, etc
        $file = $doc->xpath('//xliff:file[1]');
        if (!$file) {
            throw new \Exception('Invalid translation file - no file element');
        }
        $file = $file[0];
        $attributes = $file->attributes();
        if (!$attributes instanceof \SimpleXMLElement) {
            throw new \Exception('Invalid translation file - file element has no attributes');
        }
        $targetLanguage = (string) $attributes['target-language'];
        $this->validateTargetLanguage($targetLanguage);

        $original = (string) $attributes['original'];
        if (!str_contains($original, (string) $page->getSlug())) {
            throw new \Exception('The XLIFF file looks like it\'s for a different page. Or did the URL change?');
        }

        $pageData = $page->getPageData();
        $content = $pageData->getContent();
        $blocks = $content['blocks'];
        // Copy the default language if there isn't already a translation available
        $langData = $content['langData'][$targetLanguage] ?? $content['langData'][$pageData->getDefaultLanguage()];
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $units = $doc->xpath('//xliff:trans-unit');
        if ($units === false || !is_array($units)) {
            throw new \Exception('No translation units found');
        }

        foreach ($units as $unit) {
            $attributes = $unit->attributes();
            if (!$attributes instanceof \SimpleXMLElement) {
                throw new \Exception('Invalid translation unit - no resname attribute');
            }
            $resource = (string) ($attributes['resname'] ?? '');
            if (!preg_match('/^(\[[^\][]+])+$/', $resource)) {
                throw new \Exception('Invalid resource name: ' . $resource);
            }
            preg_match('/^\[([^\][]+)]/', $resource, $matches);
            $blockId = $matches[1];

            if ($blockId !== 'metadata' && !array_key_exists($blockId, $blocks)) {
                throw new \Exception('This file contains a translation for content that isn\'t on this page. Id: ' . $blockId);
            }

            $target = (string) $unit->target;
            if (!$target) {
                continue;
            }
            $propertyAccessor->setValue($langData, $resource, $target);
        }

        // Enable blocks for this language
        $content['blocks'] = array_map(function ($block) use ($targetLanguage) {
            if (!array_key_exists('languages', $block)) {
                return $block;
            }
            if (!in_array($targetLanguage, $block['languages'])) {
                $block['languages'][] = $targetLanguage;
            }

            return $block;
        }, $content['blocks']);

        $content['langData'][$targetLanguage] = $langData;
        $pageData->setContent($content);
        $availableLanguages = array_merge($pageData->getAvailableLanguages(), [$targetLanguage]);
        $pageData->setAvailableLanguages(array_values(array_unique($availableLanguages)));
    }

    private function validateTargetLanguage(string $language): bool
    {
        if (!is_array($this->validLanguages)) {
            return true;
        }

        if (!in_array($language, $this->validLanguages)) {
            throw new \DomainException(vsprintf('Unsupported target language %s (valid: %s)', [$language, join(', ', $this->validLanguages)]));
        }

        return true;
    }
}
