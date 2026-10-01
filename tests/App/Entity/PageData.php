<?php

namespace Tui\PageBundle\Tests\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Tui\PageBundle\Entity\AbstractPageData;
use Tui\PageBundle\Repository\PageDataRepository;

#[ORM\Entity(repositoryClass: PageDataRepository::class)]
#[ORM\Table(name: 'page_data')]
class PageData extends AbstractPageData
{
}
