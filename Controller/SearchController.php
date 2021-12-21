<?php

namespace Tui\PageBundle\Controller;

use Swagger\Annotations as SWG;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Tui\PageBundle\Repository\PageRepository;
use Tui\PageBundle\Search\TypesenseClient;

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
    public function search(Request $request, PageRepository $pageRepository, TypesenseClient $searcher, bool $searchEnabled)
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
        $index = $searcher->getCollectionNameForLanguage($language);

        $size = $request->query->getInt('size', 50) ?: 1;
        $size = ($size > 100 || $size < 1) ? 50 : $size;

        $query = [
            'q' => $terms,
            'query_by' => 'searchableText',
            'filter_by' => vsprintf('state:%s', [$state]),
            'page' => $request->query->getInt('page', 1),
            'per_page' => $request->query->getInt('size', 50),
            'prefix' => false,
        ];

        try {
            $results = $searcher->search($index, $query);
        } catch (\Exception $e) {
            return $this->json([
                'results' => [],
                'total' => 0,
            ]);
        }

        $pages = [];
        if (($results['found'] ?? 0) > 0) {
            $ids = array_map(function ($result) {
                return $result['document']['id'];
            }, $results['hits'] ?? []);

            $pagesById = [];
            // Sort results by relevance
            foreach ($pageRepository->findById($ids) as $page) {
                $pagesById[$page->getId()] = $page;
            }
            foreach ($ids as $id) {
                if (!array_key_exists($id, $pagesById)) {
                    continue;
                }
                $pages[] = $pagesById[$id];
            }
        }

        $response = [
            'results' => $pages,
            'total' => $results['found'],
        ];

        return $this->json($response, 200, [], [
            'groups' => $this->getTuiPageSerializerGroups('search_response', ['pageList']),
        ]);
    }
}
