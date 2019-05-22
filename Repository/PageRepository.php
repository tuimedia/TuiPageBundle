<?php

namespace Tui\PageBundle\Repository;

use Tui\PageBundle\Entity\AbstractPage;
use Tui\PageBundle\Entity\PageInterface;
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

    public function __construct(RegistryInterface $registry, ValidatorInterface $validator, $pageClass)
    {
        $this->validator = $validator;
        parent::__construct($registry, $pageClass);
    }

    public function save(AbstractPage $page)
    {
        $em = $this->getEntityManager();
        $em->persist($page);
        $em->flush();
    }

    public function delete(AbstractPage $page)
    {
        $em = $this->getEntityManager();
        $em->remove($page);
        $em->flush();
    }

    public function validate(AbstractPage $page)
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

    public function clonePage(Page $page, string $slug): Page
    {
        return (clone $page)->setSlug($slug);
    }
}
