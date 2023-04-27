<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Tui\PageBundle\Repository\PageRepository;
use Tui\PageBundle\Search\TypesenseClient;

class SearchController extends AbstractController
{
    use TuiPageResponseTrait;

    /**
     * Search pages.
     */
    #[Route('/search', methods: ['GET'], name: 'tui_page_search')]
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
