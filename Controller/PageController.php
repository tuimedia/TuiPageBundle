<?php

namespace Tui\PageBundle\Controller;

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

class PageController extends AbstractController
{
    protected $pageClass;

    function __construct(string $pageClass) {
        $this->pageClass = $pageClass;
    }

    /**
     * List pages
     *
     * @Route("/pages", methods={"GET"}, name="tui_page_index")
     * @SWG\Response(
     *   response=200,
     *   description="Returns a list of pages"
     * )
     * @SWG\Parameter(
     *   in="query",
     *   required=false,
     *   default="live",
     *   name="state",
     *   type="string",
     *   description="Page namespace to use"
     * )
     */
    public function index(Request $request, PageRepository $pageRepository)
    {
        $status = filter_var($request->query->get('state', 'live'), FILTER_SANITIZE_STRING);

        $pages = $pageRepository->findBy([
            'state' => $status,
        ]);

        return $this->json($pages, 200, [], [
            'groups' => ['pageList'],
        ]);
    }

    /**
     * Create a new page
     *
     * @Route("/pages", methods={"POST"}, name="tui_page_create")
     * @SWG\Response(
     *   response=201,
     *   description="Returns the created page"
     * )
     * @SWG\Response(
     *   response=422,
     *   description="Validation error(s) in application/problem+json format"
     * )
     * @SWG\Parameter(
     *   in="body",
     *   required=true,
     *   name="request",
     *   description="Page content",
     *   @SWG\Schema(type="object")
     * )
     */
    public function create(Request $request, SerializerInterface $serializer, PageRepository $pageRepository, Sanitizer $sanitizer, PageSchema $pageSchema)
    {
        // Validate input
        $errors = $pageSchema->validate($request->getContent());
        if ($errors) {
            return $this->json($errors, 422);
        }

        // Filter input
        $filteredContent = $sanitizer->cleanPage($request->getContent());

        $page = $serializer->deserialize($filteredContent, $this->pageClass, 'json', [
            'groups' => ['pageCreate'],
        ]);
        $page->getPageData()->setPageRef(rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '='));

        $errors = $pageRepository->validate($page);
        if (count($errors) > 0) {
            return $this->json($errors, 422);
        }

        $pageRepository->save($page);

        return $this->generatePageResponse($page, $serializer, ['pageGet'], 201);
    }

    /**
     * Get page
     *
     * @Route("/pages/{slug}", methods={"GET"}, name="tui_page_get")
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
     */
    public function retrieve(Request $request, SerializerInterface $serializer, PageRepository $pageRepository, $slug)
    {
        $state = filter_var($request->query->get('state', 'live'), FILTER_SANITIZE_STRING);

        $page = $pageRepository->findOneBy([
            'slug' => $slug,
            'state' => $state,
        ]);

        if (!$page) {
            throw $this->createNotFoundException('No such page in state ' . $state);
        }

        return $this->generatePageResponse($page, $serializer, ['pageGet']);
    }

    /**
     * Returns a list of pagedata objects containing the previous versions of the requested page
     * @Route("/pages/{slug}/history", methods={"GET"}, name="tui_page_history")
     * @SWG\Response(
     *   response=200,
     *   description="Success"
     * )
     * @SWG\Parameter(
     *   in="query",
     *   required=false,
     *   type="string",
     *   default="live",
     *   name="state",
     *   description="Page namespace"
     * )
     */
    public function history(Request $request, SerializerInterface $serializer, PageRepository $pageRepository, PageDataRepository $pageDataRepository, $slug)
    {
        $state = filter_var($request->query->get('state', 'live'), FILTER_SANITIZE_STRING);

        $page = $pageRepository->findOneBy([
            'slug' => $slug,
            'state' => $state,
        ]);

        if (!$page) {
            throw $this->createNotFoundException('No such page in state ' . $state);
        }

        $pageRef = $page->getPageData()->getPageRef();
        $refs = $pageDataRepository->findBy([
            'pageRef' => $pageRef,
        ], [
            'created' => 'DESC',
        ]);

        return $this->json($refs, 200, [], [
            'groups' => ['pageGet'],
        ]);
    }

    /**
     * Edit a page
     * @Route("/pages/{slug}", methods={"PUT"}, name="tui_page_edit")
     * @SWG\Response(
     *   response=200,
     *   description="Returns the updated page"
     * )
     * @SWG\Response(
     *   response=422,
     *   description="Validation error(s) in application/problem+json format"
     * )
     * @SWG\Parameter(
     *   in="body",
     *   required=true,
     *   name="request",
     *   description="Page content",
     *   @SWG\Schema(type="object")
     * )
     */
    public function edit(Request $request, SerializerInterface $serializer, PageRepository $pageRepository, Sanitizer $sanitizer, PageSchema $pageSchema, $slug)
    {
        $state = filter_var($request->query->get('state', 'live'), FILTER_SANITIZE_STRING);
        if (!$state) {
            throw new \InvalidArgumentException('Must specify state in query string');
        }

        $page = $pageRepository->findOneBy([
            'slug' => $slug,
            'state' => $state,
        ]);

        if (!$page) {
            throw $this->createNotFoundException('No such page in state ' . $state);
        }

        // Validate input
        $errors = $pageSchema->validate($request->getContent());
        if ($errors) {
            return $this->json($errors, 422);
        }

        // Filter input
        $filteredContent = $sanitizer->cleanPage($request->getContent());

        $previousRevision = $page->getPageData()->getRevision();
        $pageRef = $page->getPageData()->getPageRef();

        // Apply the request data
        $serializer->deserialize($filteredContent, $this->pageClass, 'json', [
            'groups' => ['pageCreate'],
            'object_to_populate' => $page,
        ]);
        $page->getPageData()
            ->setPageRef($pageRef)
            ->setPreviousRevision($previousRevision);

        // Validation…
        $errors = $pageRepository->validate($page);
        if (count($errors) > 0) {
            return $this->json($errors, 422);
        }

        // Done - save and return
        $pageRepository->save($page);

        return $this->generatePageResponse($page, $serializer, ['pageGet']);
    }

    /**
     * Delete a page from the given namespace
     *
     * @Route("/pages/{slug}", methods={"DELETE"}, name="tui_page_delete")
     * @SWG\Response(
     *   response=204,
     *   description="Returns an empty response"
     * )
     * @SWG\Parameter(
     *   in="query",
     *   required=false,
     *   type="string",
     *   default="live",
     *   name="state",
     *   description="Page namespace"
     * )
     */
    public function delete(Request $request, PageRepository $pageRepository, $slug)
    {
        $state = filter_var($request->query->get('state', 'live'), FILTER_SANITIZE_STRING);
        if (!$state) {
            throw new \InvalidArgumentException('Must specify state in query string');
        }

        $page = $pageRepository->findOneBy([
            'slug' => $slug,
            'state' => $state,
        ]);

        if (!$page) {
            throw $this->createNotFoundException('No such page in state ' . $state);
        }

        $pageRepository->delete($page);

        return new Response(null, 204);
    }

    private function generatePageResponse(PageInterface $page, SerializerInterface $serializer, array $groups = [], int $status = 200): JsonResponse
    {
        $pageJson = $serializer->serialize($page, 'json', [
            'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS,
            'groups' => $groups,
        ]);

        // Hackish fixen
        $pageJson = strtr($pageJson, [
            '"blocks":[]' => '"blocks":{}',
            '"langData":[]' => '"langData":{}',
            '"styles":[]' => '"styles":{}',
        ]);

        return new JsonResponse($pageJson, $status, [], true);
    }
}
