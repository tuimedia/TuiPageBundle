<?php

namespace Tui\PageBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @UniqueEntity(fields={"state", "slug"}, message="A page already exists with that URL path (the combination of state and slug must be unique)")
 * @ORM\MappedSuperclass
 */
class AbstractPage implements PageInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     * @Groups({"pageList", "pageGet"})
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=128)
     * @Groups({"pageList", "pageCreate", "pageGet"})
     * @Assert\Type(type="string")
     * @Assert\Length(max=128, maxMessage="Slug cannot be longer than {{ limit }} characters")
     * @Assert\Regex(pattern="/^[\w-]+$/", message="URL path (slug) can only contain lower case letters, numbers and dashes.")
     * @Assert\NotBlank
     */
    protected $slug;

    /**
     * @ORM\Column(type="string", length=32)
     * @Groups({"pageList", "pageCreate", "pageGet"})
     * @Assert\Type("string")
     * @Assert\Length(max=32, maxMessage="State must not be longer than {{ limit }} characters")
     */
    protected $state;

    /**
     * @ORM\ManyToOne(targetEntity="Tui\PageBundle\Entity\PageDataInterface", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumn(nullable=false, referencedColumnName="revision")
     * @Groups({"pageList", "pageCreate", "pageGet"})
     * @Assert\Valid
     * @var AbstractPageData
     */
    protected $pageData;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): PageInterface
    {
        $this->slug = $slug;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): PageInterface
    {
        $this->state = $state;

        return $this;
    }

    public function getPageData(): PageDataInterface
    {
        return $this->pageData;
    }

    public function setPageData(PageDataInterface $pageData): PageInterface
    {
        $this->pageData = $pageData;

        return $this;
    }
}
