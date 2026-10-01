<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\InputFilter;
use Tui\PageBundle\PageSchema;
use Tui\PageBundle\Repository\PageRepository;
use Tui\PageBundle\Sanitizer;
use Tui\PageBundle\TranslationHandler;

class TranslationController extends AbstractController
{
    use TuiPageResponseTrait;

    /**
     * Export page translation file.
     */
    #[Route('/translations/{slug}/{lang}', methods: ['GET'], name: 'tui_page_get_translation')]
    public function export(
        Request $request,
        PageRepository $pageRepository,
        TranslationHandler $translationHandler,
        string $slug,
        string $lang
    ): Response {
        $state = InputFilter::string($request->query->get('state', 'live'));
        $lang = InputFilter::string($lang);

        $page = $pageRepository->findOneBy([
            'slug' => $slug,
            'state' => $state,
        ]);

        if (!$page) {
            throw $this->createNotFoundException('No such page in state ' . $state);
        }

        if (!$lang) {
            return $this->json([
                'type' => 'https://tuimedia.com/page-bundle/validation',
                'title' => 'Invalid language parameter',
                'detail' => 'Language parameter is required',
            ], 400);
        }

        $this->checkTuiPagePermissions('export', $page);

        $file = $translationHandler->generateXliff($page, $lang);

        $response = new Response($file, 201, [
            'Content-Type' => 'application/x-xliff+xml',
        ]);
        $dispositionHeader = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            vsprintf('%s.%s.%s.xliff', [
                $page->getSlug(),
                $state,
                $lang,
            ])
        );
        $response->headers->set('Content-Disposition', $dispositionHeader);

        return $response;
    }

    /**
     * Import translation into page.
     */
    #[Route('/translations/{slug}', methods: ['PUT'], name: 'tui_page_put_translation')]
    public function import(
        Request $request,
        SerializerInterface $serializer,
        PageRepository $pageRepository,
        TranslationHandler $translationHandler,
        PageSchema $pageSchema,
        Sanitizer $sanitizer,
        string $slug
    ): Response {
        $state = InputFilter::string($request->query->get('state', 'live'));

        $page = $pageRepository->findOneBy([
            'slug' => $slug,
            'state' => $state,
        ]);

        if (!$page) {
            throw $this->createNotFoundException('No such page in state ' . $state);
        }

        $this->checkTuiPagePermissions('import', $page);

        $destination = InputFilter::string($request->query->get('destination', 'original'));
        // Normalised the way they'll be saved, so the existence check below tests the real destination
        $destinationState = trim((string) preg_replace('/[^\w-]+/', '-', InputFilter::string($request->query->get('destinationState', 'live'))), '-');
        $destinationSlug = trim((string) preg_replace('/[^\w-]+/', '-', InputFilter::string($request->query->get('destinationSlug', $page->getSlug()))), '-');
        if (!in_array($destination, ['new', 'original'])) {
            return $this->json([
                'type' => 'https://tuimedia.com/page-bundle/validation',
                'title' => 'Invalid destination parameter',
                'detail' => 'Valid destinations are new (to save to a new page), and original (to create a new one)',
            ], 422);
        }

        if ($destination === 'new' && !($destinationSlug && $destinationState)) {
            return $this->json([
                'type' => 'https://tuimedia.com/page-bundle/validation',
                'title' => 'Missing or invalid destination slug/state',
                'detail' => 'When saving to a new page, destinationSlug and destinationState can\'t be empty. They default to the original page\'s slug and live',
            ], 409);
        }

        if ($destination === 'new') {
            $newPageExists = $pageRepository->findOneBy([
                'slug' => $destinationSlug,
                'state' => $destinationState,
            ]);

            if ($newPageExists) {
                return $this->json([
                    'type' => 'https://tuimedia.com/page-bundle/validation',
                    'title' => 'New destination already exists',
                    'detail' => 'You requested the data be saved to a new page, but the destination state and slug already exist',
                ], 422);
            }

            $page = clone $page;
            // Set a temporary revision so the page will validate
            $page->getPageData()->setRevision('ffffffff-ffff-ffff-ffff-ffffffffffff');
        }

        if ($destination === 'original') {
            // Create a new revision
            $pageRef = $page->getPageData()->getPageRef();
            $page->setPageData(clone $page->getPageData());
            $page->getPageData()->setPageRef((string) $pageRef);
            // Set a temporary revision so the page will validate
            $page->getPageData()->setRevision('ffffffff-ffff-ffff-ffff-ffffffffffff');
        }

        try {
            $translationHandler->importXliff($page, $request->getContent());
        } catch (\Exception $e) {
            return $this->json([
                'type' => 'https://tuimedia.com/page-bundle/validation',
                'title' => 'Unable to process XLIFF file',
                'detail' => $e->getMessage(),
            ], 422);
        }

        // Only after importing, which checks the file was exported from the original slug
        if ($destination === 'new') {
            $page->setSlug($destinationSlug);
            $page->setState($destinationState);
        }
        $groups = $this->getTuiPageSerializerGroups('import_response', ['pageGet']);

        // Validate input
        $json = $this->generateTuiPageJson($page, $serializer, $groups);
        $errors = $pageSchema->validate($json);
        if ($errors) {
            return $this->json($errors, 422);
        }

        // Filter input. Importing only changes the content, so that's all that's copied back
        $filtered = json_decode($sanitizer->cleanPage($json), true, 512, JSON_THROW_ON_ERROR);
        $page->getPageData()->setContent($filtered['pageData']['content']);
        // Remove the temporary revision
        if ($page->getPageData()->getRevision() === 'ffffffff-ffff-ffff-ffff-ffffffffffff') {
            $page->getPageData()->setRevision(null);
        }

        $pageRepository->save($page);

        return $this->generateTuiPageResponse($page, $serializer, $groups, 201);
    }
}
