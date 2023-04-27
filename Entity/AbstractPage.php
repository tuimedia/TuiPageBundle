<?php

namespace Tui\PageBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['state', 'slug'], message: 'A page already exists with that URL path and state')]
#[ORM\MappedSuperclass]
#[ORM\Table(name: 'tui_page')]
#[ORM\UniqueConstraint(name: 'state_slug_unique', columns: ['state', 'slug'])]
class AbstractPage implements PageInterface, IsIndexableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'guid')]
    #[Groups(['pageList', 'pageGet'])]
    protected ?string $id;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string', length: 128)]
    #[Groups(['pageList', 'pageCreate', 'pageGet'])]
    #[Assert\Type(type: 'string')]
    #[Assert\Length(max: 128, maxMessage: 'Slug cannot be longer than {{ limit }} characters')]
    #[Assert\Regex(pattern: '/^[\w-]+$/', message: 'URL path (slug) can only contain lower case letters, numbers and dashes.')]
    #[Assert\NotBlank]
    protected $slug;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['pageList', 'pageCreate', 'pageGet'])]
    #[Assert\Type('string')]
    #[Assert\Length(max: 32, maxMessage: 'State must not be longer than {{ limit }} characters')]
    protected $state;

    /**
     * @var AbstractPageData|PageDataInterface
     */
    #[ORM\ManyToOne(targetEntity: PageDataInterface::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'revision')]
    #[Groups(['pageList', 'pageCreate', 'pageGet'])]
    #[Assert\Valid]
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

    public function __clone()
    {
        if (!$this->id) {
            return;
        }

        $this->id = null;
        $this->setPageData(clone $this->getPageData());
    }

    public function isIndexable(): bool
    {
        return true;
    }
}
