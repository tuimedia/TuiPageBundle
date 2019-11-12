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

        if ($pageRepository->ensureRowIds($page)) {
            $pageRepository->save($page);
        }
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

        $destination = filter_var($request->query->get('destination', 'original'), FILTER_SANITIZE_STRING);
        $destinationState = filter_var($request->query->get('destinationState', 'live'), FILTER_SANITIZE_STRING);
        $destinationSlug = filter_var($request->query->get('destinationSlug', $page->getSlug()), FILTER_SANITIZE_STRING);
        if (!in_array($destination, ['new', 'original'])) {
            return $this->json([
                'type' => 'https://tuimedia.com/page-bundle/validation',
                'title' => 'Invalid destination parameter',
                'detail' => 'Valid destinations are new (to save to a new page), and original (to create a new one)',
            ], 422);
        }

        if ($destination === 'new' && !($destinationSlug || $destinationState)) {
            return $this->json([
                'type' => 'https://tuimedia.com/page-bundle/validation',
                'title' => 'Missing or invalid destination slug/state',
                'detail' => 'When saving to a new page, you must provide a destinationSlug and/or a destinationState',
            ], 409);
        }

        $pageRepository->ensureRowIds($page);
        if ($destination === 'new') {
            $newPageExists = $pageRepository->findOneBy([
                'slug' => $destinationSlug,
                'state' => $destinationState,
            ]);

            if ($newPageExists) {
                return $this->json([
                    'type' => 'https://tuimedia.com/page-bundle/validation',
                    'title' => 'New destination already exists',
                    'detail' => 'You requested the data be saved to a new page, but the destination state and slug already exist',
                ], 422);
            }

            $page = clone $page;
            // Set a temporary revision so the page will validate
            $page->getPageData()->setRevision('c6706289-1347-441c-9e09-4718e80dc56a');
            if ($destinationSlug) {
                $page->setSlug(preg_replace('/[^\w]+/', '-', $slug));
            }

            if ($destinationState) {
                $page->setState($destinationState);
            }
        }

        $file = $translationHandler->importXliff($page, $request->getContent());
        $groups = $this->getTuiPageSerializerGroups('import_response', ['pageGet']);
        $pageJson = $this->generateTuiPageJson($page, $serializer, $groups);

        // Validate input
        $errors = $pageSchema->validate($pageJson);
        if ($errors) {
            return $this->json($errors, 422);
        }
        // Remove the temporary revision
        $page->getPageData()->setRevision(null);

        $pageRepository->save($page);

        return new JsonResponse($pageJson, 201, [], true);
    }
}