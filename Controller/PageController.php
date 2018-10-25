<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\Entity;
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
     * @Route("/pages", methods={"GET"}, name="tui_page_index")
     */
    public function index(Request $request, PageRepository $pageRepository)
    {
        $status = $request->query->get('state', 'live');

        $pages = $pageRepository->findBy([
            'state' => $status,
        ]);

        return $this->json($pages, 200, [], [
            'groups' => ['pageList'],
        ]);
    }

    /**
     * @Route("/pages", methods={"POST"}, name="tui_page_create")
     */
    public function create(Request $request, SerializerInterface $serializer, PageRepository $pageRepository, Sanitizer $sanitizer, PageSchema $pageSchema)
    {
        // Validate input
        $errors = $pageSchema->validate($request->getContent());
        if ($errors) {
            return $this->json($errors);
        }

        // Filter input
        $filteredContent = $sanitizer->cleanPage($request->getContent());

        $page = $serializer->deserialize($filteredContent, $this->pageClass, 'json', [
            'groups' => ['pageCreate'],
        ]);

        $errors = $pageRepository->validate($page);
        if (count($errors) > 0) {
            return $this->json($errors, 422);
        }

        $pageRepository->save($page);

        return $this->json($page, 200, [], [
            'groups' => ['pageGet'],
        ]);
    }

    /**
     * @Route("/pages/{slug}", methods={"GET"}, name="tui_page_get")
     */
    public function retrieve(Request $request, SerializerInterface $serializer, PageRepository $pageRepository, $slug)
    {
        $state = $request->query->get('state', 'live');

        $page = $pageRepository->findOneBy([
            'slug' => $slug,
            'state' => $state,
        ]);

        if (!$page) {
            throw $this->createNotFoundException('No such page in state ' . $state);
        }

        return $this->json($page, 200, [], [
            'groups' => ['pageGet'],
        ]);
    }

    /**
     * @Route("/pages/{slug}/history", methods={"GET"}, name="tui_page_history")
     */
    public function history(Request $request, SerializerInterface $serializer, PageRepository $pageRepository, PageDataRepository $pageDataRepository, $slug)
    {
        $state = $request->query->get('state', 'live');

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
     * @Route("/pages/{slug}", methods={"PUT"}, name="tui_page_edit")
     */
    public function edit(Request $request, SerializerInterface $serializer, PageRepository $pageRepository, Sanitizer $sanitizer, PageSchema $pageSchema, $slug)
    {
        $state = $request->query->get('state');
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
            return $this->json($errors);
        }

        // Filter input
        $filteredContent = $sanitizer->cleanPage($request->getContent());

        $previousRevision = $page->getPageData()->getRevision();

        // Create a new revision
        $pageData = clone $page->getPageData();
        $page->setPageData($pageData);

        // Apply the request data
        $serializer->deserialize($filteredContent, $this->pageClass, 'json', [
            'groups' => ['pageCreate'],
            'object_to_populate' => $page,
        ]);
        $page->getPageData()->setPreviousRevision($previousRevision);

        // Validation…
        $errors = $pageRepository->validate($page);
        if (count($errors) > 0) {
            return $this->json($errors, 422);
        }

        // Done - save and return
        $pageRepository->save($page);

        return $this->json($page, 200, [], [
            'groups' => ['pageGet'],
        ]);
    }

    /**
     * @Route("/pages/{slug}", methods={"DELETE"}, name="tui_page_delete")
     */
    public function delete(Request $request, PageRepository $pageRepository, $slug)
    {
        $state = $request->query->get('state');
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
}
