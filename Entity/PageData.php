<?php

namespace Tui\PageBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="Tui\PageBundle\Repository\PageDataRepository")
 * @ORM\Table(name="tuipagebundle_page_data")
 */
class PageData
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     * @Groups({"pageList", "pageGet"})
     */
    private $revision;

    /**
     * @ORM\Column(type="guid", nullable=true)
     * @Groups({"pageList", "pageGet"})
     */
    private $previousRevision;

    /**
     * @ORM\Column(type="string", length=128)
     * @Groups({"pageCreate", "pageGet"})
     * @Assert\Type(type="string")
     * @Assert\Length(max=128)
     */
    private $pageRef;

    /**
     * @ORM\Column(type="string", length=32)
     * @Groups({"pageCreate", "pageGet"})
     * @Assert\Type(type="string")
     * @Assert\Length(max=32)
     */
    private $defaultLanguage = 'en';

    /**
     * @ORM\Column(type="array")
     * @Groups({"pageCreate", "pageGet"})
     * @Assert\Type(type="array")
     */
    private $availableLanguages = ['en'];

    /**
     * @ORM\Column(type="datetime_immutable")
     * @Groups({"pageList", "pageGet"})
     */
    private $created;

    /**
     * @ORM\Column(type="json_array")
     * @Assert\Type(type="array")
     * @Groups({"pageCreate", "pageGet"})
     */
    private $content = [];

    /**
     * @ORM\Column(type="json_array")
     * @Assert\Type(type="array")
     * @Groups({"pageList", "pageCreate", "pageGet"})
     */
    private $metadata;

    /**
     * @ORM\ManyToOne(targetEntity="Tui\PageBundle\Entity\ElementSet", cascade={"persist"})
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @Assert\Valid
     */
    private $elementSet;

    /**
     * @Groups({"pageList", "pageCreate", "pageGet"})
     * @var Element[] Array of Elements
     */
    private $elements;

    /**
     * @ORM\OneToMany(targetEntity="Tui\PageBundle\Entity\Page", mappedBy="pageData")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private $pages;

    public function __construct()
    {
        $this->pages = new ArrayCollection();
        $this->created = date_create_immutable();
    }

    public function getPageRef(): ?string
    {
        return $this->pageRef;
    }

    public function setPageRef(string $pageRef): self
    {
        $this->pageRef = $pageRef;

        return $this;
    }

    public function getDefaultLanguage(): ?string
    {
        return $this->defaultLanguage;
    }

    public function setDefaultLanguage(string $defaultLanguage): self
    {
        $this->defaultLanguage = $defaultLanguage;

        return $this;
    }

    public function getAvailableLanguages()
    {
        return $this->availableLanguages;
    }

    public function setAvailableLanguages(array $availableLanguages): self
    {
        $this->availableLanguages = $availableLanguages;

        return $this;
    }

    public function getCreated(): ?\DateTimeImmutable
    {
        return $this->created;
    }

    public function setCreated(\DateTimeImmutable $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function getRevision(): ?string
    {
        return $this->revision;
    }

    public function setRevision(string $revision): self
    {
        $this->revision = $revision;

        return $this;
    }

    public function getPreviousRevision(): ?string
    {
        return $this->previousRevision;
    }

    public function setPreviousRevision(?string $previousRevision): self
    {
        $this->previousRevision = $previousRevision;

        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setContent(array $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function setMetadata($metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getElementSet(): ?ElementSet
    {
        return $this->elementSet;
    }

    public function setElementSet(?ElementSet $elementSet): self
    {
        // Make elementsets immutable by duplicating them on set
        $this->elementSet = $elementSet;

        return $this;
    }

    public function setElements(?array $elements): self
    {
        if (!count($elements)) {
            $this->setElementSet(null);
            return $this;
        }

        $elementSet = new ElementSet();
        foreach ($elements as $element) {
            $elementSetElement = new ElementSetElement();
            $elementSetElement->setElement($element);
            $elementSet->addElementSetElement($elementSetElement);
        }

        $this->setElementSet($elementSet);
        $this->getElements(); // Refresh internal list

        return $this;
    }
    
    public function getElements(): array
    {
        if (!$this->elementSet) {
            return [];
        }

        $this->elements = array_map(function ($elementSetElement) {
            return $elementSetElement->getElement();
        }, $this->elementSet->getElementSetElements()->toArray());

        return $this->elements;
    }

    /**
     * @return Collection|Page[]
     */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    public function addPage(Page $page): self
    {
        if (!$this->pages->contains($page)) {
            $this->pages[] = $page;
            $page->setPageData($this);
        }

        return $this;
    }

    public function removePage(Page $page): self
    {
        if ($this->pages->contains($page)) {
            $this->pages->removeElement($page);
            // set the owning side to null (unless already changed)
            if ($page->getPageData() === $this) {
                $page->setPageData(null);
            }
        }

        return $this;
    }
}
