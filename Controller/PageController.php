<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class PageController extends AbstractController
{
    /**
     * @Route("/pages", name="tui_page_index")
     */
    public function index()
    {
        return $this->json([
            'message' => 'Pages',
        ]);
    }
}
