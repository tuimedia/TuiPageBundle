<?php

namespace Tui\PageBundle\Repository;

use Tui\PageBundle\Entity\AbstractPage;
use Tui\PageBundle\Entity\PageInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Iterator;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Traversable;

/**
 * @method PageInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method PageInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method PageInterface[]    findAll()
 * @method PageInterface[]    findById(array|string $ids)
 * @method PageInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageRepository extends ServiceEntityRepository
{
    /** @var ValidatorInterface */
    protected $validator;

    public function __construct(ManagerRegistry $registry, ValidatorInterface $validator, string $pageClass)
    {
        $this->validator = $validator;
        parent::__construct($registry, $pageClass);
    }

    public function save(PageInterface $page): void
    {
        $em = $this->getEntityManager();
        $em->persist($page);
        $em->flush();
    }

    public function delete(PageInterface $page): void
    {
        $em = $this->getEntityManager();
        $em->remove($page);
        $em->flush();
    }

    public function validate(PageInterface $page): ?ConstraintViolationListInterface
    {
        return $this->validator->validate($page);
    }

    public function getBySlugAndState(string $slug, string $state = 'live'): ?PageInterface
    {
        return $this->findOneBy([
            'slug' => filter_var($slug, FILTER_SANITIZE_STRING),
            'state' => filter_var($state, FILTER_SANITIZE_STRING),
        ]);
    }

    public function clonePage(PageInterface $page, string $slug): PageInterface
    {
        return (clone $page)->setSlug($slug);
    }

    public function getQueryForIndexing(): Query
    {
        return $this->createQueryBuilder('p')
            ->select('p, pd')
            ->join('p.pageData', 'pd')
            ->getQuery();
    }

    public function getPagesForIndexing(int $limit = 500, int $offset = 0): Traversable
    {
        $query = $this->getQueryForIndexing()
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return new Paginator($query);
    }
}
