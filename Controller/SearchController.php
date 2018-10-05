<?php

namespace Tui\PageBundle\Controller;

use ElasticSearcher\ElasticSearcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\Entity;
use Tui\PageBundle\PageSchema;
use Tui\PageBundle\Repository\PageDataRepository;
use Tui\PageBundle\Repository\PageRepository;
use Tui\PageBundle\Sanitizer;

class SearchController extends AbstractController
{
    // protected $pageClass;
    //
    // function __construct(string $pageClass) {
    //     $this->pageClass = $pageClass;
    // }

    /**
     * @Route("/search", methods={"GET"}, name="tui_page_search")
     */
    public function index(Request $request, PageRepository $pageRepository, ElasticSearcher $searcher)
    {
        $query = $request->query->get('q', '');

        $pages = [];

        return $this->json($pages, 200, [], [
            'groups' => ['pageList'],
        ]);
    }
}
