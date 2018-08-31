<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ElementController extends AbstractController
{
    /**
     * @Route("/elements", name="tui_page_element_index")
     */
    public function index()
    {
        return $this->json([
            'message' => 'Elements',
        ]);
    }
}
