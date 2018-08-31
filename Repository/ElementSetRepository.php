<?php

namespace Tui\PageBundle\Repository;

use Tui\PageBundle\Entity\ElementSet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ElementSet|null find($id, $lockMode = null, $lockVersion = null)
 * @method ElementSet|null findOneBy(array $criteria, array $orderBy = null)
 * @method ElementSet[]    findAll()
 * @method ElementSet[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ElementSetRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ElementSet::class);
    }

//    /**
//     * @return ElementSet[] Returns an array of ElementSet objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ElementSet
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
