<?php
namespace Tui\PageBundle;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tui\PageBundle\TuiPageBundle;
use Tui\PageBundle\Entity\PageInterface;

class TranslationHandler
{
    private $router;

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }

    public function generateXliff(PageInterface $page, $targetLanguage)
    {
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

        $file->setAttribute('source-language', $this->convertLangCode($pageData->getDefaultLanguage()));
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

        foreach ($content['layout'] as $row) {
            // Add row langdata if it exists
            if (array_key_exists($row['id'], $sourceLangData)) {
                $this->addArrayRecursive($doc, $body, "[{$row['id']}]", $sourceLangData[$row['id']], $targetLangData[$row['id']] ?? []);
            }

            // Add block langdata
            foreach ($row['blocks'] as $blockId) {
                if (!array_key_exists($blockId, $sourceLangData)) {
                    continue;
                }
                $this->addArrayRecursive($doc, $body, "[$blockId]", $sourceLangData[$blockId] ?? [], $targetLangData[$blockId] ?? []);
            }

        }

        return $doc->saveXML();
    }

    private function addArrayRecursive($doc, $element, string $resPrefix, array $sourceValues, array $targetValues) {
        foreach ($sourceValues as $key => $value) {
            if (is_array($value)) {
                $this->addArrayRecursive($doc, $element, vsprintf('%s[%s]', [
                    $resPrefix,
                    $key,
                ]), $value, $targetValues[$key] ?? []);
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

    private function convertLangCode($code): string
    {
        return strtr((string) $code, ['_' => '-']);
    }

    private function addNote($doc, $element, string $text)
    {
        $element->appendChild($note = $doc->createElement('note'));
        $note->appendChild($doc->createTextNode($text));
    }

    public function importXliff(PageInterface $page, string $xliffData)
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
        $file = $doc->xpath('//xliff:file[1]')[0];
        if (!$file) {
            throw new \Exception('No XLIFF file element found');
        }
        $targetLanguage = (string) $file->attributes()['target-language'];
        $original = (string) $file->attributes()['original'];
        if (strpos($original, $page->getSlug()) === false) {
            throw new \Exception('The XLIFF file appears to be for a different page');
        }

        $pageData = $page->getPageData();
        $content = $pageData->getContent();
        $blocks = $content['blocks'];
        $layout = $content['layout'];
        $langData = $content['langData'][$targetLanguage] ?? [];
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $units = $doc->xpath('//xliff:trans-unit');
        if ($units === false) {
            throw new \Exception('No translation units found');
        }

        foreach ($units as $unit) {
            $resource = (string) $unit->attributes()['resname'];
            if (!preg_match('/^(\[[^\][]+])+$/', $resource)) {
                throw new \Exception('Invalid resource name: ' . $resource);
            }
            preg_match('/^\[([^\][]+)]/', $resource, $matches);
            $blockId = $matches[1];

            if (
                $blockId !== 'metadata'
                && !array_key_exists($blockId, $blocks)
                && !array_key_exists($blockId, $layout)
            ) {
                throw new \Exception('Missing or invalid resource: '. $blockId);
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

        // Enable rows for this language
        $content['layout'] = array_map(function ($row) use ($targetLanguage) {
            if (!array_key_exists('languages', $row)) {
                return $row;
            }
            if (!in_array($targetLanguage, $row['languages'])) {
                $row['languages'][] = $targetLanguage;
            }
            return $row;
        }, $content['layout']);

        $content['langData'][$targetLanguage] = $langData;
        $pageData->setContent($content);
        $availableLanguages = array_merge($pageData->getAvailableLanguages(), [$targetLanguage]);
        $pageData->setAvailableLanguages(array_values(array_unique($availableLanguages)));
    }
}
