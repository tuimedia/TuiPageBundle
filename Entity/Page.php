<?php

namespace Tui\PageBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="Tui\PageBundle\Repository\PageRepository")
 * @ORM\Table(name="tuipagebundle_page",
 *   uniqueConstraints={@ORM\UniqueConstraint(name="state_slug_unique",columns={"state","slug"})}
 * )
 * @UniqueEntity(fields={"state", "slug"}, message="A page already exists with that URL path (the combination of state and slug must be unique)")
 */
class Page
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     * @Groups({"pageList", "pageGet"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=128)
     * @Groups({"pageList", "pageCreate", "pageGet"})
     * @Assert\Type(type="string")
     * @Assert\Length(max=128, maxMessage="Slug cannot be longer than {{ limit }} characters")
     * @Assert\Regex(pattern="/^[\w-]+$/", message="URL path (slug) can only contain lower case letters, numbers and dashes.")
     * @Assert\NotBlank
     */
    private $slug;

    /**
     * @ORM\Column(type="string", length=32)
     * @Groups({"pageList", "pageCreate", "pageGet"})
     * @Assert\Type("string")
     * @Assert\Length(max=32, maxMessage="State must not be longer than {{ limit }} characters")
     */
    private $state;

    /**
     * @ORM\ManyToOne(targetEntity="Tui\PageBundle\Entity\PageData", inversedBy="pages", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumn(nullable=false, referencedColumnName="revision")
     * @Groups({"pageList", "pageCreate", "pageGet"})
     * @Assert\Valid
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
