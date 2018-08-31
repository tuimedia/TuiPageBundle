<?php

namespace Tui\PageBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

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
     * @Groups({"pageList"})
     */
    private $revision;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"pageCreate"})
     */
    private $pageRef;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $created;

    /**
     * @ORM\Column(type="guid", nullable=true)
     */
    private $previousRevision;

    /**
     * @ORM\Column(type="json_array")
     * @Groups({"pageCreate"})
     */
    private $content = [];

    /**
     * @ORM\Column(type="json_array")
     * @Groups({"pageCreate"})
     */
    private $metadata;

    /**
     * @ORM\ManyToOne(targetEntity="Tui\PageBundle\Entity\ElementSet")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @Groups({"pageCreate"})
     */
    private $elementSet;

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

    public function setContent($content): self
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
        $this->elementSet = $elementSet;

        return $this;
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
