<?php

namespace Tui\PageBundle\Controller;

use Swagger\Annotations as SWG;
use ElasticSearcher\Abstracts\AbstractResultParser;
use ElasticSearcher\ElasticSearcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Tui\PageBundle\Repository\PageRepository;
use Tui\PageBundle\Search\PageQuery;
use Tui\PageBundle\Search\TranslatedPageIndexFactory;

class SearchController extends AbstractController
{
    use TuiPageResponseTrait;

    /**
     * Search pages
     * @Route("/search", methods={"GET"}, name="tui_page_search")
     * @SWG\Response(
     *   response=200,
     *   description="Returns a list of pages"
     * )
     * @SWG\Parameter(
     *   in="query",
     *   required=false,
     *   name="q",
     *   type="string",
     *   description="Search terms (can be empty, but that will return no results)"
     * )
     * @SWG\Parameter(
     *   in="query",
     *   required=false,
     *   name="language",
     *   default="en_GB",
     *   type="string",
     *   description="Language to search"
     * )
     * @SWG\Parameter(
     *   in="query",
     *   required=false,
     *   default="live",
     *   name="state",
     *   type="string",
     *   description="Page namespace to search"
     * )
     * @SWG\Parameter(
     *   in="query",
     *   required=false,
     *   default=50,
     *   name="size",
     *   type="integer",
     *   description="Maximum number of results per page"
     * )
     * @SWG\Parameter(
     *   in="query",
     *   required=false,
     *   default=1,
     *   name="page",
     *   type="integer",
     *   description="Page of results to return"
     * )
     */
    public function search(Request $request, PageRepository $pageRepository, ElasticSearcher $searcher, PageQuery $query, TranslatedPageIndexFactory $indexFactory, bool $searchEnabled)
    {
        if (!$searchEnabled) {
            return $this->json([
                'results' => [],
                'total' => 0,
            ]);
        }

        $this->checkTuiPagePermissions('search');

        $terms = substr((string) filter_var($request->query->get('q', ''), FILTER_SANITIZE_STRING), 0, 128);
        $language = substr((string) filter_var($request->query->get('language', 'en_GB'), FILTER_SANITIZE_STRING), 0, 32);
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
                'didYouMean' => null,
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
            'didYouMean' => $this->parseSuggestions($results),
        ];

        return $this->json($response, 200, [], [
            'groups' => $this->getTuiPageSerializerGroups('search_response', ['pageList']),
        ]);
    }

    private function parseSuggestions(AbstractResultParser $results): ?string
    {
        $suggestions = $results->get('suggest.dym');
        if (!count($suggestions) || !count($suggestions[0]['options'])) {
            return null;
        }

        return $suggestions[0]['options'][0]['text'];
    }
}
