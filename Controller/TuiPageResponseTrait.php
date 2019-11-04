<?php
namespace Tui\PageBundle\Controller;

use Tui\PageBundle\Entity\PageInterface;
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
                'content' => function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []) {
                    dd($innerObject);
                    if (!$outerObject instanceof PageDataInterface) {
                        return $innerObject;
                    }

                    if (count($innerObject['blocks'] ?? []) === 0) {
                        $innerObject['blocks'] = new \stdClass;
                    }
                    if (count($innerObject['langData'] ?? []) === 0) {
                        $innerObject['langData'] = new \stdClass;
                    }

                    return $innerObject;
                },
            ],
        ]);

        // Hackish fixen
        $pageJson = strtr($pageJson, [
            '"styles":[]' => '"styles":{}',
        ]);

        return new JsonResponse($pageJson, $status, [], true);
    }
}
