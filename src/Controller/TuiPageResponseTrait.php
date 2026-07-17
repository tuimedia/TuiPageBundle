<?php
namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\Entity\PageDataInterface;
use Tui\PageBundle\Entity\PageInterface;

trait TuiPageResponseTrait
{
    public function generateTuiPageResponse(PageInterface $page, SerializerInterface $serializer, array $groups = [], int $status = 200): JsonResponse
    {
        $pageJson = $this->generateTuiPageJson($page, $serializer, $groups);

        return new JsonResponse($pageJson, $status, [], true);
    }

    public function generateTuiPageJson(PageInterface $page, SerializerInterface $serializer, array $groups = []): string
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

        return $pageJson;
    }

    public function flagEmptyObjects(array $innerObject, mixed $outerObject): array
    {
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

    public function getTuiPageSerializerGroups(string $action, array $default): array
    {
        if (!$this instanceof AbstractController) {
            throw new \Exception('This method requires the class to extend Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController');
        }
        $parameter = 'tui_page.serializer_groups.' . $action;
        $groups = array_values(array_unique(array_merge($default, (array) $this->getParameter($parameter))));

        return $groups;
    }

    protected function checkTuiPagePermissions(string $check, ?PageInterface $page = null): void
    {
        $roles = (array) $this->getParameter('tui_page.access_roles.' . $check);
        if (count($roles)) {
            // Since Symfony 4.4 passing an array to denyAccessUnlessGranted is deprecated
            // - call multiple times instead
            foreach ($roles as $role) {
                if ($this->isGranted($role, $page)) {
                    return;
                }
            }

            /** var \Symfony\Component\HttpKernel\Exception\NotFoundHttpException */
            $exception = $this->createAccessDeniedException('Access Denied.');
            $exception->setAttributes($roles);
            $exception->setSubject($page);

            throw $exception;
        }
    }
}
