<?php

namespace Tui\PageBundle\Repository;

use Tui\PageBundle\Entity\Element;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @method Element|null find($id, $lockMode = null, $lockVersion = null)
 * @method Element|null findOneBy(array $criteria, array $orderBy = null)
 * @method Element[]    findAll()
 * @method Element[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ElementRepository extends ServiceEntityRepository
{
    protected $validator;

    public function __construct(RegistryInterface $registry, ValidatorInterface $validator)
    {
        $this->validator = $validator;
        parent::__construct($registry, Element::class);
    }

    public function save(Element $element)
    {
        $em = $this->getEntityManager();
        $em->persist($element);
        $em->flush();
    }

    public function delete(Element $element)
    {
        $em = $this->getEntityManager();
        $em->remove($element);
        $em->flush();
    }

    public function validate(Element $element)
    {
        return $this->validator->validate($element);
    }

//    /**
//     * @return Element[] Returns an array of Element objects
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
    public function findOneBySomeField($value): ?Element
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
