<?php

namespace Tui\PageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
}
