<?php

namespace Tui\PageBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
#[ORM\Table(name: 'tui_page_data')]
abstract class AbstractPageData implements PageDataInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'guid')]
    #[Groups(['pageList', 'pageGet'])]
    private ?string $revision = null;

    #[ORM\Column(type: 'guid', nullable: true)]
    #[Groups(['pageList', 'pageGet'])]
    private ?string $previousRevision = null;

    #[ORM\Column(type: 'string', length: 128)]
    #[Assert\Type(type: 'string')]
    #[Assert\Length(max: 128)]
    private ?string $pageRef = null;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['pageCreate', 'pageGet', 'pageList'])]
    #[Assert\Type(type: 'string')]
    #[Assert\Length(max: 32)]
    private string $defaultLanguage = 'en_GB';

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'array')]
    #[Groups(['pageCreate', 'pageGet', 'pageList'])]
    #[Assert\Type(type: 'array')]
    private array $availableLanguages = ['en_GB'];

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['pageList', 'pageGet'])]
    private \DateTimeImmutable $created;

    #[ORM\Column(type: 'json')]
    #[Assert\Type(type: 'array')]
    #[Groups(['pageCreate', 'pageGet'])]
    private array $content = [];

    #[ORM\Column(type: 'json')]
    #[Assert\Type(type: 'array')]
    #[Groups(['pageList', 'pageCreate', 'pageGet'])]
    private array $metadata = [];

    public function __construct()
    {
        $time = new \DateTimeImmutable();
        $this->created = $time;
    }

    public function getPageRef(): ?string
    {
        return $this->pageRef;
    }

    public function setPageRef(?string $pageRef): PageDataInterface
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

    public function getCreated(): \DateTimeImmutable
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

    public function setRevision(?string $revision): PageDataInterface
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

    #[Groups(['pageList', 'pageGet'])]
    public function getTranslatedMetadata(): array
    {
        $global = $this->metadata;
        $allMetadata = [];
        foreach ($this->availableLanguages as $lang) {
            $allMetadata[$lang] = array_replace_recursive(
                $global,
                isset($this->content['langData'][$this->defaultLanguage]) && isset($this->content['langData'][$this->defaultLanguage]['metadata'])
                ? $this->content['langData'][$this->defaultLanguage]['metadata']
                : [],
                isset($this->content['langData'][$lang]) && isset($this->content['langData'][$lang]['metadata'])
                ? $this->content['langData'][$lang]['metadata']
                : []
            );
        }

        return $allMetadata;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): PageDataInterface
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function __clone()
    {
        if (!$this->revision) {
            return;
        }

        // Set previous revision so we know which page this was forked from
        $this->previousRevision = $this->revision;
        $this->revision = null;

        // Create new page reference (to reset history)
        $this->pageRef = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }
}
