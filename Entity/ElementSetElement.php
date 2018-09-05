<?php

namespace Tui\PageBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="Tui\PageBundle\Repository\ElementSetElementRepository")
 * @ORM\Table(name="tuipagebundle_element_set_element")
 */
class ElementSetElement
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Tui\PageBundle\Entity\ElementSet", inversedBy="elementSetElements")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $elementSet;

    /**
     * @ORM\ManyToOne(targetEntity="Tui\PageBundle\Entity\Element", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     * @Groups({"pageCreate", "pageGet"})
     * @Assert\Valid
     */
    private $element;

    /**
     * @ORM\Column(type="integer")
     */
    private $displayOrder = 0;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getElementSet(): ?ElementSet
    {
        return $this->elementSet;
    }

    public function setElementSet(?ElementSet $elementSet): self
    {
        $this->elementSet = $elementSet;

        return $this;
    }

    public function getElement(): ?Element
    {
        return $this->element;
    }

    public function setElement(?Element $element): self
    {
        $this->element = $element;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }
}
