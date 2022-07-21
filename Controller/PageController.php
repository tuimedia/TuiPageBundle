<?php

namespace Tui\PageBundle\Controller;

use Swagger\Annotations as SWG;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\Sanitizer;
use Tui\PageBundle\PageSchema;
use Tui\PageBundle\Repository\PageDataRepository;
use Tui\PageBundle\Repository\PageRepository;

class PageController extends AbstractController
{
    use TuiPageResponseTrait;

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
        $state = preg_replace('/\W+/', '-', strip_tags($request->query->get('state', 'live')));

        $this->checkTuiPagePermissions('list');

        $pages = $pageRepository->findBy([
            'state' => $state,
        ]);

        $response =  $this->json($pages, 200, [], [
            'groups' => $this->getTuiPageSerializerGroups('list_response', ['pageList']),
        ]);

        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $response->setCache([
            'private' => true,
            'last_modified' => $this->findLatest($pages),
        ]);
        $response->isNotModified($request);

        return $response;
    }

    private function findLatest(array $pages): \DateTimeInterface
    {
        $latest = new \DateTime('1970-01-01 00:00:00');
        foreach ($pages as $page) {
            $pageCreated = $page->getPageData()->getCreated();
            if ($pageCreated > $latest) {
                $latest = $pageCreated;
            }
        }

        return $latest;
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

        $groups = $this->getTuiPageSerializerGroups('create_request', ['pageCreate']);
        $page = $serializer->deserialize($filteredContent, $this->pageClass, 'json', [
            'groups' => $groups,
        ]);
        $page->getPageData()->setPageRef(rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '='));

        $errors = $pageRepository->validate($page);
        if (count($errors) > 0) {
            return $this->json($errors, 422);
        }

        $this->checkTuiPagePermissions('create', $page);
        $pageRepository->save($page);

        $groups = $this->getTuiPageSerializerGroups('create_response', ['pageGet']);
        return $this->generateTuiPageResponse($page, $serializer, $groups, 201);
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

        $this->checkTuiPagePermissions('retrieve', $page);

        if (!$page) {
            throw $this->createNotFoundException('No such page in state ' . $state);
        }

        $groups = $this->getTuiPageSerializerGroups('get_response', ['pageGet']);
        $response = $this->generateTuiPageResponse($page, $serializer, $groups);
        // $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        // $response->setCache([
        //     'private' => true,
        //     'etag' => $page->getPageData()->getRevision(),
        // ]);
        // $response->isNotModified($request);

        return $response;
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
    public function history(Request $request, PageRepository $pageRepository, PageDataRepository $pageDataRepository, $slug)
    {
        $state = filter_var($request->query->get('state', 'live'), FILTER_SANITIZE_STRING);

        $page = $pageRepository->findOneBy([
            'slug' => $slug,
            'state' => $state,
        ]);

        $this->checkTuiPagePermissions('history', $page);

        if (!$page) {
            throw $this->createNotFoundException('No such page in state ' . $state);
        }

        $pageRef = $page->getPageData()->getPageRef();
        $refs = $pageDataRepository->findBy([
            'pageRef' => $pageRef,
        ], [
            'created' => 'DESC',
        ]);

        $groups = $this->getTuiPageSerializerGroups('history_response', ['pageGet']);
        return $this->json($refs, 200, [], [
            'groups' => $groups,
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

        $this->checkTuiPagePermissions('edit', $page);

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
        $groups = $this->getTuiPageSerializerGroups('update_request', ['pageCreate']);
        $serializer->deserialize($filteredContent, $this->pageClass, 'json', [
            'groups' => $groups,
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

        $groups = $this->getTuiPageSerializerGroups('update_response', ['pageGet']);
        return $this->generateTuiPageResponse($page, $serializer, $groups);
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

        $this->checkTuiPagePermissions('delete', $page);

        $pageRepository->delete($page);

        return new Response(null, 204);
    }
}
