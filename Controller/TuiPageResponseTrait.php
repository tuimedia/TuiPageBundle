<?php
namespace Tui\PageBundle\Controller;

use Tui\PageBundle\Entity\PageInterface;
use Tui\PageBundle\Entity\PageDataInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

trait TuiPageResponseTrait
{
    public function generateTuiPageResponse(PageInterface $page, SerializerInterface $serializer, array $groups = [], int $status = 200): JsonResponse
    {
        $pageJson = $serializer->serialize($page, 'json', [
            'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS,
            'groups' => $groups,
            'callbacks' => [
                'content' => [$this, 'flagEmptyObjects'],
            ],
        ]);

        // Replace markers with empty objects
        $pageJson = strtr($pageJson, [
            '"TUI_PAGE_EMPTY_OBJECT"' => '{}',
        ]);

        return new JsonResponse($pageJson, $status, [], true);
    }

    public function flagEmptyObjects($innerObject, $outerObject) {
        if (!$outerObject instanceof PageDataInterface) {
            return $innerObject;
        }

        foreach (($innerObject['blocks'] ?? []) as $idx => $block) {
            if (is_array($block['styles'] ?? false) && !count($block['styles'])) {
                $innerObject['blocks'][$idx]['styles'] = 'TUI_PAGE_EMPTY_OBJECT';
            }
        }

        foreach (['blocks', 'langData'] as $key) {
            if (count($innerObject[$key] ?? []) === 0) {
                $innerObject[$key] = 'TUI_PAGE_EMPTY_OBJECT';
            }
        }

        return $innerObject;
    }
}
