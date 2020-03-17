<?php

namespace Tui\PageBundle\Repository;

use Tui\PageBundle\Entity\AbstractPage;
use Tui\PageBundle\Entity\PageInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    public function ensureRowIds(PageInterface $page): bool
    {
        $modified = false;
        $content = $page->getPageData()->getContent();
        $content['layout'] = array_map(function ($row) use (&$modified) {
            if (!array_key_exists('id', $row)) {
                $row['id'] = \base64_encode(\random_bytes(9));
                $modified = true;
            }

            return $row;
        }, $content['layout']);

        $page->getPageData()->setContent($content);

        return $modified;
    }
}
