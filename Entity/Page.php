<?php

namespace Tui\PageBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Tui\PageBundle\Repository\PageRepository")
 * @ORM\Table(name="page",
 *   uniqueConstraints={@ORM\UniqueConstraint(name="state_slug_unique",columns={"state","slug"})}
 * )
 */
class Page
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=128)
     */
    private $slug;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $state;

    /**
     * @ORM\ManyToOne(targetEntity="Tui\PageBundle\Entity\PageData", inversedBy="pages")
     * @ORM\JoinColumn(nullable=false, referencedColumnName="revision")
     */
    private $pageData;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getPageData(): ?PageData
    {
        return $this->pageData;
    }

    public function setPageData(?PageData $pageData): self
    {
        $this->pageData = $pageData;

        return $this;
    }
}
