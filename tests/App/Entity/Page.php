<?php

namespace Tui\PageBundle\Tests\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Tui\PageBundle\Entity\AbstractPage;
use Tui\PageBundle\Repository\PageRepository;

/**
 * Extends the bundle's page with an extra property, as real apps do (tags, channels and so on).
 */
#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\Table(name: 'page')]
class Page extends AbstractPage
{
    #[ORM\Column(type: 'json')]
    #[Groups(['pageGet', 'pageCreate', 'pageList'])]
    private array $tagData = [];

    public function getTagData(): array
    {
        return $this->tagData;
    }

    public function setTagData(array $tagData): self
    {
        $this->tagData = $tagData;

        return $this;
    }
}
