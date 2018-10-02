<?php

namespace Tui\PageBundle\Repository;

use Tui\PageBundle\Entity\PageData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method PageData|null find($id, $lockMode = null, $lockVersion = null)
 * @method PageData|null findOneBy(array $criteria, array $orderBy = null)
 * @method PageData[]    findAll()
 * @method PageData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageDataRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry, $pageDataClass)
    {
        parent::__construct($registry, $pageDataClass);
    }
}
