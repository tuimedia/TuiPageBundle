<?php

namespace Tui\PageBundle\Repository;

use Tui\PageBundle\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @method Page|null find($id, $lockMode = null, $lockVersion = null)
 * @method Page|null findOneBy(array $criteria, array $orderBy = null)
 * @method Page[]    findAll()
 * @method Page[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageRepository extends ServiceEntityRepository
{
    protected $validator;
    protected $schemas;

    public function __construct(RegistryInterface $registry, ValidatorInterface $validator)
    {
        $this->validator = $validator;
        parent::__construct($registry, Page::class);
    }

    public function save(Page $page)
    {
        $em = $this->getEntityManager();
        $em->persist($page);
        $em->flush();
    }

    public function delete(Page $page)
    {
        $em = $this->getEntityManager();
        $em->remove($page);
        $em->flush();
    }

    public function validate(Page $page)
    {
        return $this->validator->validate($page);
    }

//    /**
//     * @return Page[] Returns an array of Page objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Page
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
