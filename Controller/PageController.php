<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Tui\PageBundle\Repository\PageRepository;

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
}
