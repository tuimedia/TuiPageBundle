<?php

namespace Tui\PageBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\MappedSuperclass
 */
abstract class AbstractPageData implements PageDataInterface
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

    public function __construct()
    {
        $this->created = date_create_immutable();
    }

    public function getPageRef(): ?string
    {
        return $this->pageRef;
    }

    public function setPageRef(string $pageRef): PageDataInterface
    {
        $this->pageRef = $pageRef;

        return $this;
    }

    public function getDefaultLanguage(): ?string
    {
        return $this->defaultLanguage;
    }

    public function setDefaultLanguage(string $defaultLanguage): PageDataInterface
    {
        $this->defaultLanguage = $defaultLanguage;

        return $this;
    }

    public function getAvailableLanguages(): array
    {
        return $this->availableLanguages;
    }

    public function setAvailableLanguages(array $availableLanguages): PageDataInterface
    {
        $this->availableLanguages = $availableLanguages;

        return $this;
    }

    public function getCreated(): ?\DateTimeImmutable
    {
        return $this->created;
    }

    public function setCreated(\DateTimeImmutable $created): PageDataInterface
    {
        $this->created = $created;

        return $this;
    }

    public function getRevision(): ?string
    {
        return $this->revision;
    }

    public function setRevision(string $revision): PageDataInterface
    {
        $this->revision = $revision;

        return $this;
    }

    public function getPreviousRevision(): ?string
    {
        return $this->previousRevision;
    }

    public function setPreviousRevision(?string $previousRevision): PageDataInterface
    {
        $this->previousRevision = $previousRevision;

        return $this;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function setContent(array $content): PageDataInterface
    {
        $this->content = $content;

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata($metadata): PageDataInterface
    {
        $this->metadata = $metadata;

        return $this;
    }
}
