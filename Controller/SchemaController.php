<?php

namespace Tui\PageBundle\Controller;

use Swagger\Annotations as SWG;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SchemaController extends AbstractController
{
    /**
     * Get the TuiPage JSON Schema
     *
     * @Route("/pages/schema/tui-page.json", methods={"GET"}, name="tui_page_schema_page")
     * @SWG\Response(
     *   response=200,
     *   description="Success"
     * )
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
     * Get the JSON schema for the given content component
     *
     * Content component schemas describe the distinct fields for that component. Bear in mind that all components have common fields that are not included in this schema file, but are also validated (e.g. `id`, `component`, `languages` and `styles`). See the TuiPage schema for details.
     *
     * @Route("/pages/schema/{component}.schema.json", methods={"GET"}, name="tui_page_schema_component")
     * @SWG\Parameter(
     *   in="path",
     *   required=true,
     *   name="component",
     *   type="string",
     *   description="Component name, e.g. `PageText`"
     * )
     * @SWG\Response(
     *   response=200,
     *   description="Success"
     * )
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
