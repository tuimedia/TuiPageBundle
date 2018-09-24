<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SchemaController extends AbstractController
{
    /**
     * @Route("/pages/schema/tui-page.json", methods={"GET"}, name="tui_page_schema_page")
     */
    public function page(RouterInterface $router)
    {
        $schema = file_get_contents(__DIR__.'/../Resources/schema/tui-page.schema.json');
        $correctId = $router->generate('tui_page_schema_page', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $schema = str_replace(
            '"$id": "http://api.example.com/tui-page.schema.json#"',
            sprintf('"$id": "%s"', $correctId),
            $schema
        );

        $response = new Response($schema);
        $response->headers->set('Content-Type', 'application/schema+json');

        return $response;
    }
}
