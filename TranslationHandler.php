<?php
namespace Tui\PageBundle;

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

    public function generateXliff(PageInterface $page)
    {
        $pageData = $page->getPageData();
        $content = $pageData->getContent();
        $sourceLangData = $content['langData'][$pageData->getDefaultLanguage()];

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
        $file->setAttribute('datatype', 'plaintext');


        $file->appendChild($header = $doc->createElement('header'));
        $header->appendChild($tool = $doc->createElement('tool'));
        $tool->setAttribute('tool-id', 'TuiPageBundle');
        $tool->setAttribute('tool-name', 'TuiPageBundle');
        $tool->setAttribute('tool-version', sprintf('%.1f', TuiPageBundle::VERSION));

        $file->appendChild($body = $doc->createElement('body'));

        // Create group for translatable metadata
        $body->appendChild($metadataGroup = $doc->createElement('group'));
        $metadataGroup->setAttribute('resname', 'metadata');
        $metadataGroup->setAttribute('id', hash('sha1', (string) $metadataGroup->getNodePath()));
        $this->addNote($doc, $metadataGroup, 'metadata');
        $this->addArrayRecursive($doc, $metadataGroup, $sourceLangData['metadata'] ?? []);

        // Loop through layout rows, create a group for the row and include all row langdata
        foreach ($content['layout'] as $row) {
            $body->appendChild($rowGroup = $doc->createElement('group'));

            // Add row langdata if it exists
            $rowGroup->setAttribute('resname', $row['id']);
            $rowGroup->setAttribute('id', hash('sha1', (string) $rowGroup->getNodePath()));
            if (array_key_exists($row['id'], $sourceLangData)) {
                $this->addArrayRecursive($doc, $rowGroup, $sourceLangData[$row['id']]);
            }

            // Loop through row blocks, create a group for each block with its langdata
            foreach ($row['blocks'] as $blockId) {
                if (!array_key_exists($blockId, $sourceLangData)) {
                    continue;
                }
                $rowGroup->appendChild($blockGroup = $doc->createElement('group'));
                $this->addNote($doc, $blockGroup, $content['blocks'][$blockId]['component']);
                $blockGroup->setAttribute('resname', $blockId);
                $blockGroup->setAttribute('id', hash('sha1', (string) $blockGroup->getNodePath()));
                $this->addArrayRecursive($doc, $blockGroup, $sourceLangData[$blockId] ?? []);
            }

        }

        return $doc->saveXML();
    }

    private function addArrayRecursive($doc, $element, $values) {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $element->appendChild($subGroup = $doc->createElement('group'));
                $this->addNote($doc, $subGroup, $key);
                $subGroup->setAttribute('resname', $key);
                $subGroup->setAttribute('id', hash('sha1', (string) $subGroup->getNodePath()));
                $this->addArrayRecursive($doc, $subGroup, $value);
                continue;
            }

            $element->appendChild($unit = $doc->createElement('trans-unit'));
            $unit->setAttribute('resname', $key);
            $unit->setAttribute('id', hash('sha1', (string) $unit->getNodePath()));
            $unit->appendChild($source = $doc->createElement('source'));
            $unit->appendChild($target = $doc->createElement('target'));
            if (preg_match('/[<>&]/', $value)) {
                $source->appendChild($doc->createCDATASection($value));
                $target->appendChild($doc->createCDATASection(''));
            } else {
                $source->appendChild($doc->createTextNode($value));
                $target->appendChild($doc->createTextNode(''));
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
}
