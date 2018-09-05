<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\Repository\ElementRepository;
use Tui\PageBundle\Entity;

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

    /**
     * @Route("/elements", methods={"POST"}, name="tui_page_element_create")
     */
    public function create(Request $request, SerializerInterface $serializer, ElementRepository $elementRepository)
    {
        $element = $serializer->deserialize($request->getContent(), Entity\Element::class, 'json', [
            'groups' => ['elementCreate'],
        ]);

        $elementRepository->save($element);

        return $this->json($element, 200, [], [
            'groups' => ['elementGet'],
        ]);
    }

    /**
     * @Route("/elements/{id}", methods={"PUT"}, name="tui_page_element_edit")
     */
    public function edit(Request $request, SerializerInterface $serializer, ElementRepository $elementRepository, Entity\Element $element)
    {
        $element = $serializer->deserialize($request->getContent(), Entity\Element::class, 'json', [
            'groups' => ['elementCreate'],
            'object_to_populate' => $element,
        ]);

        $elementRepository->save($element);

        return $this->json($element, 200, [], [
            'groups' => ['elementGet'],
        ]);
    }

    /**
     * @Route("/elements/{id}", methods={"DELETE"}, name="tui_page_element_delete")
     */
    public function delete(ElementRepository $elementRepository, Entity\Element $element)
    {
        $elementRepository->delete($element);

        return new Response(null, 204);
    }
}
