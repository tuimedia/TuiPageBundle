<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Tui\PageBundle\Repository\ElementRepository;

class ElementController extends AbstractController
{
    /**
     * @Route("/elements", methods={"GET"}, name="tui_page_element_index")
     */
    public function index(Request $request, ElementRepository $elementRepository)
    {
        $elements = $elementRepository->findAll();

        return $this->json($elements, 200, [], [
            'groups' => ['elementList'],
        ]);
    }
}
