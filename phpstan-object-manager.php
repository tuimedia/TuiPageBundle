<?php
// Entity manager for phpstan-doctrine's column and relation rules.
//
// The bundle's entities name their ID generator by service ID ('doctrine.uuid_generator'),
// which only DoctrineBundle can resolve, so this driver swaps it for no generator. Everything
// else about the mapping is read exactly as the ORM reads it.

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;

require __DIR__ . '/vendor/autoload.php';

$driver = new class (new AttributeDriver([__DIR__ . '/src/Entity'])) implements MappingDriver {
    public function __construct(private readonly AttributeDriver $inner)
    {
    }

    public function loadMetadataForClass(string $className, PersistenceClassMetadata $metadata): void
    {
        $this->inner->loadMetadataForClass($className, $metadata);
        if ($metadata instanceof ClassMetadata && $metadata->generatorType === ClassMetadata::GENERATOR_TYPE_CUSTOM) {
            $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
            $metadata->customGeneratorDefinition = null;
        }
    }

    public function getAllClassNames(): array
    {
        return $this->inner->getAllClassNames();
    }

    public function isTransient(string $className): bool
    {
        return $this->inner->isTransient($className);
    }
};

$config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/src/Entity'], true);
$config->setMetadataDriverImpl($driver);
if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
    $config->enableNativeLazyObjects(true);
}

return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
