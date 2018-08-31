<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\Repository\PageRepository;
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
    public function create(Request $request, SerializerInterface $serializer, PageRepository $pageRepository)
    {
        $page = $serializer->deserialize($request->getContent(), Entity\Page::class, 'json', [
            'groups' => ['pageCreate'],
        ]);

        return $this->json($page, 200, [], [
            'groups' => ['pageList'],
        ]);
    }
}
