<?php

namespace Tui\PageBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="Tui\PageBundle\Repository\ElementRepository")
 * @ORM\Table(name="tuipagebundle_element")
 */
class Element
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     * @Groups({"pageGet", "pageCreate", "elementList", "elementGet", "elementCreate"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"pageGet", "pageCreate", "elementList", "elementCreate", "elementGet"})
     */
    private $title;

    /**
     * @ORM\Column(type="string", length=128, unique=true)
     * @Groups({"pageGet", "pageCreate", "elementList", "elementCreate", "elementGet"})
     */
    private $slug;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"pageGet", "pageCreate", "elementList", "elementCreate", "elementGet"})
     */
    private $hidden = false;

    /**
     * @ORM\Column(type="string", length=128)
     * @Groups({"pageGet", "pageCreate", "elementList", "elementCreate", "elementGet"})
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
