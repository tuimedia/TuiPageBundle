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
        $schema = (string) file_get_contents(__DIR__.'/../Resources/schema/tui-page.schema.json');
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

    /**
     * @Route("/pages/schema/{component}.schema.json", methods={"GET"}, name="tui_page_schema_component")
     */
    public function component(RouterInterface $router, $componentSchemas, string $component)
    {
        if (!isset($componentSchemas[$component])) {
            throw $this->createNotFoundException();
        }

        // Load the component file and replace the $id URL with a pointer to this location
        $schema = (string) file_get_contents($componentSchemas[$component]);
        $correctId = $router->generate('tui_page_schema_component', ['component' => $component], UrlGeneratorInterface::ABSOLUTE_URL);
        $jsonId = json_encode($correctId, JSON_UNESCAPED_SLASHES);

        $schema = preg_replace('/"\$id":\s+"([^\"]+)"/', sprintf('"$id": %s', $jsonId), $schema);

        $response = new Response($schema);
        $response->headers->set('Content-Type', 'application/schema+json');

        return $response;
    }
}
