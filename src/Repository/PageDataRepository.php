<?php

namespace Tui\PageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tui\PageBundle\Entity\PageData;

/**
 * @method PageData|null find($id, $lockMode = null, $lockVersion = null)
 * @method PageData|null findOneBy(array $criteria, array $orderBy = null)
 * @method PageData[]    findAll()
 * @method PageData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @template T of object
 *
 * @template-extends ServiceEntityRepository<T>
 */
class PageDataRepository extends ServiceEntityRepository
{
    /**
     * @param string $pageDataClass The class name of the entity this repository manages
     *
     * @psalm-param class-string<T> $pageDataClass
     */
    public function __construct(ManagerRegistry $registry, private string $pageDataClass)
    {
        parent::__construct($registry, $pageDataClass);
    }

    public function getAllLanguages(): array
    {
        $result = $this->getEntityManager()->createQuery(vsprintf('SELECT DISTINCT pd.availableLanguages FROM %s pd', [
            $this->pageDataClass,
        ]))->getResult();

        if (!is_array($result)) {
            return [];
        }

        $langs = array_values(array_unique(array_reduce($result, fn ($langs, $pageData) => [...$langs, ...$pageData['availableLanguages']], [])));

        return $langs;
    }
}
