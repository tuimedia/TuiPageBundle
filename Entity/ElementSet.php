<?php

namespace Tui\PageBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="Tui\PageBundle\Repository\ElementSetRepository")
 * @ORM\Table(name="tuipagebundle_element_set")
 */
class ElementSet
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     */
    private $id;

    /**
     * @ORM\OneToMany(targetEntity="Tui\PageBundle\Entity\ElementSetElement", mappedBy="elementSet", cascade={"persist"})
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @ORM\OrderBy({"displayOrder" = "ASC"})
     * @Groups({"pageCreate", "pageGet"})
     * @Assert\Valid
     */
    private $elementSetElements;

    public function __construct(?ArrayCollection $elementSetElements = null)
    {
        $this->elementSetElements = $elementSetElements ?: new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return Collection|ElementSetElement[]
     */
    public function getElementSetElements(): Collection
    {
        return $this->elementSetElements;
    }

    public function addElementSetElement(ElementSetElement $elementSetElement): self
    {
        if (!$this->elementSetElements->contains($elementSetElement)) {
            $this->elementSetElements[] = $elementSetElement;
            $elementSetElement->setElementSet($this);
        }

        return $this;
    }

    public function removeElementSetElement(ElementSetElement $elementSetElement): self
    {
        if ($this->elementSetElements->contains($elementSetElement)) {
            $this->elementSetElements->removeElement($elementSetElement);
            // set the owning side to null (unless already changed)
            if ($elementSetElement->getElementSet() === $this) {
                $elementSetElement->setElementSet(null);
            }
        }

        return $this;
    }
}
