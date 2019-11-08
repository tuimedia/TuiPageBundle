<?php

namespace Tui\PageBundle\Controller;

use Tui\PageBundle\TranslationHandler;
use Swagger\Annotations as SWG;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\Entity;
use Tui\PageBundle\Entity\AbstractPage;
use Tui\PageBundle\Entity\PageInterface;
use Tui\PageBundle\Sanitizer;
use Tui\PageBundle\PageSchema;
use Tui\PageBundle\Repository\PageDataRepository;
use Tui\PageBundle\Repository\PageRepository;

class TranslationController extends AbstractController
{
    use TuiPageResponseTrait;

    /**
     * Export page translation file
     *
     * @Route("/translations/{slug}/{lang}", methods={"GET"}, name="tui_page_get_translation")
     * @SWG\Response(
     *   response=200,
     *   description="Success"
     * )
     * @SWG\Response(
     *   response=404,
     *   description="Page not found in the given state"
     * )
     * @SWG\Parameter(
     *   in="query",
     *   required=false,
     *   type="string",
     *   default="live",
     *   name="state",
     *   description="Page namespace"
     * )
     * @SWG\Parameter(
     *   in="path",
     *   required=true,
     *   type="string",
     *   name="slug",
     *   description="Page slug"
     * )
     * @SWG\Parameter(
     *   in="path",
     *   required=true,
     *   type="string",
     *   name="lang",
     *   description="Destination language"
     * )
     */
    public function export(
        Request $request,
        SerializerInterface $serializer,
        PageRepository $pageRepository,
        TranslationHandler $translationHandler,
        string $slug,
        string $lang
    ) {
        $state = filter_var($request->query->get('state', 'live'), FILTER_SANITIZE_STRING);

        $page = $pageRepository->findOneBy([
            'slug' => $slug,
            'state' => $state,
        ]);

        if (!$page) {
            throw $this->createNotFoundException('No such page in state ' . $state);
        }

        $page = $pageRepository->ensureRowIds($page);
        $file = $translationHandler->generateXliff($page, $lang);

        return new Response($file, 201, [
            'Content-Type' => 'application/x-xliff+xml',
            // 'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Import translation into page
     *
     * @Route("/translations/{slug}", methods={"PUT"}, name="tui_page_put_translation")
     * @SWG\Response(
     *   response=200,
     *   description="Success"
     * )
     * @SWG\Response(
     *   response=404,
     *   description="Page not found in the given state"
     * )
     * @SWG\Parameter(
     *   in="query",
     *   required=false,
     *   type="string",
     *   default="live",
     *   name="state",
     *   description="Page namespace"
     * )
     * @SWG\Parameter(
     *   in="path",
     *   required=true,
     *   type="string",
     *   name="slug",
     *   description="Page slug"
     * )
     * @SWG\Parameter(
     *   in="body",
     *   required=true,
     *   name="request",
     *   description="XLIFF file content",
     *   @SWG\Schema(type="string")
     * )
     */
    public function import(
        Request $request,
        SerializerInterface $serializer,
        PageRepository $pageRepository,
        TranslationHandler $translationHandler,
        PageSchema $pageSchema,
        string $slug
    ) {
        $state = filter_var($request->query->get('state', 'live'), FILTER_SANITIZE_STRING);

        $page = $pageRepository->findOneBy([
            'slug' => $slug,
            'state' => $state,
        ]);

        if (!$page) {
            throw $this->createNotFoundException('No such page in state ' . $state);
        }

        $page = $pageRepository->ensureRowIds($page);
        $file = $translationHandler->importXliff($page, $request->getContent());

        $groups = $this->getTuiPageSerializerGroups('import_response', ['pageGet']);
        $pageJson =  $this->generateTuiPageJson($page, $serializer, $groups);

        // Validate input
        $errors = $pageSchema->validate($pageJson);
        if ($errors) {
            return $this->json($errors, 422);
        }

        return new JsonResponse($pageJson, 201, [], true);
    }
}
