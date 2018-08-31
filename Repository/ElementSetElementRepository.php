<?php

namespace Tui\PageBundle\Repository;

use Tui\PageBundle\Entity\ElementSetElement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ElementSetElement|null find($id, $lockMode = null, $lockVersion = null)
 * @method ElementSetElement|null findOneBy(array $criteria, array $orderBy = null)
 * @method ElementSetElement[]    findAll()
 * @method ElementSetElement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ElementSetElementRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ElementSetElement::class);
    }

//    /**
//     * @return ElementSetElement[] Returns an array of ElementSetElement objects
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
    public function findOneBySomeField($value): ?ElementSetElement
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
