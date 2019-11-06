<?php
namespace Tui\PageBundle;

use Tui\PageBundle\TuiPageBundle;
use Tui\PageBundle\Entity\PageInterface;

class TranslationHandler
{
    public function generateXliff(PageInterface $page, $targetLanguage)
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

        $date = new \DateTime();
        $file->setAttribute('date', $date->format('Y-m-d\TH:i:s\Z'));

        $file->setAttribute('source-language', $this->convertLangCode($pageData->getDefaultLanguage()));
        $file->setAttribute('target-language', $this->convertLangCode($targetLanguage));
        $file->setAttribute('datatype', 'plaintext');
        $file->setAttribute('original', vsprintf('%s:%s:%s', [
            $page->getSlug(),
            $page->getState(),
            $pageData->getRevision(),
        ]));

        $file->appendChild($header = $doc->createElement('header'));
        $header->appendChild($tool = $doc->createElement('tool'));
        $tool->setAttribute('tool-id', 'TuiPageBundle');
        $tool->setAttribute('tool-name', 'TuiPageBundle');
        $tool->setAttribute('tool-version', sprintf('%.1f', TuiPageBundle::VERSION));

        $file->appendChild($body = $doc->createElement('body'));

        // Create group for translatable metadata
        $body->appendChild($metadataGroup = $doc->createElement('group'));
        $metadataGroup->setAttribute('resname', 'metadata');
        $metadataGroup->setAttribute('id', $metadataGroup->getNodePath());
        $this->addArrayRecursive($doc, $metadataGroup, $sourceLangData['metadata'] ?? []);

        // Loop through layout rows, create a group for the row and include all row langdata
        foreach ($content['layout'] as $row) {
            $body->appendChild($rowGroup = $doc->createElement('group'));

            // Add row langdata if it exists
            $rowGroup->setAttribute('resname', $row['id']);
            $rowGroup->setAttribute('id', hash('sha1', $rowGroup->getNodePath()));
            if (array_key_exists($row['id'], $sourceLangData)) {
                $this->addArrayRecursive($doc, $rowGroup, $sourceLangData[$row['id']]);
            }

            // Loop through row blocks, create a group for each block with its langdata
            foreach ($row['blocks'] as $blockId) {
                if (!array_key_exists($blockId, $sourceLangData)) {
                    continue;
                }
                $rowGroup->appendChild($blockGroup = $doc->createElement('group'));
                $blockGroup->setAttribute('resname', $blockId);
                $blockGroup->setAttribute('id', hash('sha1', $blockGroup->getNodePath()));
                $this->addArrayRecursive($doc, $blockGroup, $sourceLangData[$blockId] ?? []);
            }

        }

        return $doc->saveXML();
    }

    private function addArrayRecursive($doc, $element, $values) {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $element->appendChild($subGroup = $doc->createElement('group'));
                $subGroup->setAttribute('resname', $key);
                $subGroup->setAttribute('id', $subGroup->getNodePath());
                $this->addArrayRecursive($doc, $subGroup, $value);
                continue;
            }

            $element->appendChild($unit = $doc->createElement('trans-unit'));
            $unit->setAttribute('resname', $key);
            $unit->setAttribute('id', $unit->getNodePath());
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
        }
    }

    private function convertLangCode($code): string
    {
        return strtr((string) $code, ['_' => '-']);
    }
}
