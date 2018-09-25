<?php

namespace Tui\PageBundle\Repository;

use Tui\PageBundle\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Opis\JsonSchema\{
    Validator, ValidationResult, ValidationError, Schema
};

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

    public function __construct(RegistryInterface $registry, ValidatorInterface $validator, $componentSchemas)
    {
        $this->validator = $validator;
        $this->schemas = $componentSchemas;
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

    public function schemaValidate($data)
    {
        $data = json_decode($data);
        $schema = Schema::fromJsonString(file_get_contents(__DIR__.'/../Resources/schema/tui-page.schema.json'));

        $validator = new Validator();

        /** @var ValidationResult $result */
        $result = $validator->schemaValidation($data, $schema);

        if ($result->hasErrors()) {
            return $this->formatSchemaErrors($result->getErrors());
        }

        // Validate components against their schemas
        foreach ($data->pageData->content->blocks as $block) {
            if (!array_key_exists($block->component, $this->schemas)) {
                throw new \Exception(sprintf('No schema configured for component "%s"', $block->component));
            }

            foreach ($block->languages as $language) {
                // Build the block by overlaying default language data and this language data
                $resolvedBlock = $this->resolveBlockForLanguage($data, $block->id, $language);

                // Check resulting object against the component schema
                $schema = Schema::fromJsonString(file_get_contents($this->schemas[$resolvedBlock->component]));
                $result = $validator->schemaValidation($resolvedBlock, $schema);
                if ($result->hasErrors()) {
                    return $this->formatSchemaErrors($result->getErrors(), $resolvedBlock, $language);
                }
            }
        }
    }

    private function resolveBlockForLanguage($data, $id, $language)
    {
        $resolvedBlock = new \stdClass;
        $defaultLang = $data->pageData->defaultLanguage;

        foreach ($data->pageData->content->blocks->$id as $prop => $value) {
            $resolvedBlock->$prop = $value;
        }

        foreach ($data->pageData->content->langData->$defaultLang->$id as $prop => $value) {
            $resolvedBlock->$prop = $value;
        }

        if ($language === $defaultLang) {
            return $resolvedBlock;
        }

        foreach ($data->pageData->content->langData->$language->$id as $prop => $value) {
            $resolvedBlock->$prop = $value;
        }

        return $resolvedBlock;
    }

    private function formatSchemaErrors($errors, $block = null, $language = null)
    {
        $error = [
            'type' => 'https://tuimedia.com/tui-page/errors/validation',
            'title' => 'Validation failed',
            'detail' => '',
            'errors' => [],
        ];

        if ($block) {
            $error['detail'] = sprintf('Component %s in language %s: ', $block->id, $language);
            $error['component'] = $block;
        }

        $error['errors'] = array_map(function ($error) {
            return [
                'path' => implode('.', $error->dataPointer()),
                'keyword' => $error->keyword(),
                'keywordArgs' => $error->keywordArgs(),
            ];
        }, $errors);

        $error['detail'] = implode('. ', array_map(function ($error) {
            return sprintf('[%s]: invalid %s.', $error['path'], $error['keyword']);
        }, $error['errors']));

        return $error;
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
