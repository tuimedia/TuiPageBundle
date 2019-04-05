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
use Tui\PageBundle\Search\PageQuery;
use Tui\PageBundle\Search\TranslatedPageIndexFactory;

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
    public function search(Request $request, PageRepository $pageRepository, ElasticSearcher $searcher, PageQuery $query, TranslatedPageIndexFactory $indexFactory)
    {
        $terms = substr((string) filter_var($request->query->get('q', ''), FILTER_SANITIZE_STRING), 0, 128);
        $language = substr((string) filter_var($request->query->get('language', ''), FILTER_SANITIZE_STRING), 0, 32);
        $state = substr((string) filter_var($request->query->get('state', 'live'), FILTER_SANITIZE_STRING), 0, 32);
        $index = $indexFactory->createTranslatedPageIndex($language);
        $searcher->indicesManager()->register($index);
        $size = $request->query->getInt('size', 50) ?: 1;
        $size = ($size > 100 || $size < 1) ? 50 : $size;

        $query->addData([
            'index' => $index->getName(),
            'state' => $state,
            'q' => $terms,
            'page' => $request->query->getInt('page', 1),
            'size' => $request->query->getInt('size', 50),
        ]);

        try {
            $results = $query->run();
        } catch (\Exception $e) {
            return $this->json([
                'results' => [],
                'total' => 0,
            ]);
        }
        $pages = [];

        if ($results->getTotal() > 0) {
            $ids = array_column($results->getResults(), '_id');
            $pages = $pageRepository->findById($ids);
        }

        $response = [
            'results' => $pages,
            'total' => $results->getTotal(),
        ];

        return $this->json($response, 200, [], [
            'groups' => ['pageList'],
        ]);
    }
}
