<?php

namespace Tui\PageBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="Tui\PageBundle\Repository\ElementRepository")
 * @ORM\Table(name="tuipagebundle_element")
 * @UniqueEntity("slug")
 */
class Element
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     * @Groups({"pageGet", "pageCreate", "pageList", "elementList", "elementGet", "elementCreate"})
     * @Assert\Uuid
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"pageGet", "pageCreate", "pageList", "elementList", "elementCreate", "elementGet"})
     * @Assert\Type(type="string")
     * @Assert\Length(max=255, maxMessage="Title cannot be longer than {{ limit }} characters")
     * @Assert\NotBlank
     */
    private $title;

    /**
     * @ORM\Column(type="string", length=128, unique=true)
     * @Groups({"pageGet", "pageCreate", "pageList", "elementList", "elementCreate", "elementGet"})
     * @Assert\Type(type="string")
     * @Assert\Length(max=128, maxMessage="Slug cannot be longer than {{ limit }} characters")
     * @Assert\Regex(pattern="/^[\w-]+$/", message="URL path (slug) can only contain lower case letters, numbers and dashes.")
     * @Assert\NotBlank
     */
    private $slug;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"pageGet", "pageCreate", "pageList", "elementList", "elementCreate", "elementGet"})
     * @Assert\Type(type="bool")
     * @Assert\NotNull
     */
    private $hidden = false;

    /**
     * @ORM\Column(type="string", length=128)
     * @Groups({"pageGet", "pageCreate", "pageList", "elementList", "elementCreate", "elementGet"})
     * @Assert\Type(type="string")
     * @Assert\Length(max=128, maxMessage="Element type cannot be longer than {{ limit }} characters")
     */
    protected $type = 'element';

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
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

    public function getHidden(): ?bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): self
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
