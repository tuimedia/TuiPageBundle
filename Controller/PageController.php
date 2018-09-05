<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\Repository\PageRepository;
use Tui\PageBundle\Repository\ElementRepository;
use Tui\PageBundle\Entity;

class PageController extends AbstractController
{
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
    public function create(Request $request, SerializerInterface $serializer, PageRepository $pageRepository, ElementRepository $elementRepository)
    {
        $page = $serializer->deserialize($request->getContent(), Entity\Page::class, 'json', [
            'groups' => ['pageCreate'],
        ]);

        $elementIds = array_map(function ($element) {
            return $element->getId();
        }, $page->getPageData()->getElements());

        $elements = $elementRepository->findById($elementIds);
        $page->getPageData()->setElements($elements);

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
     * @Route("/pages/{slug}", methods={"PUT"}, name="tui_page_edit")
     */
    public function edit(Request $request, SerializerInterface $serializer, PageRepository $pageRepository, ElementRepository $elementRepository, $slug)
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

        // Get previous elements
        $previousElementIds = array_map(function ($element) {
            return $element->getId();
        }, $page->getPageData()->getElements());
        sort($previousElementIds);
        $previousElementSet = $page->getPageData()->getElementSet();
        $previousRevision = $page->getPageData()->getRevision();

        // Create a new revision
        $pageData = clone $page->getPageData();
        $page->setPageData($pageData);

        // Apply the request data
        $serializer->deserialize($request->getContent(), Entity\Page::class, 'json', [
            'groups' => ['pageCreate'],
            'object_to_populate' => $page,
        ]);
        $page->getPageData()->setPreviousRevision($previousRevision);

        // Compare elements and create a new set if there are differences
        $elementIds = array_map(function ($element) {
            return $element->getId();
        }, $page->getPageData()->getElements());
        sort($elementIds);

        if ($previousElementIds !== $elementIds) {
            $elements = count($elementIds) ? $elementRepository->findById($elementIds) : [];
            $page->getPageData()->setElements($elements);
        } else {
            $page->getPageData()->setElementSet($previousElementSet);
        }

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
}
