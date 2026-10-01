<?php

namespace Tui\PageBundle\Tests\App;

use Composer\InstalledVersions;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tui\PageBundle\Entity\PageDataInterface;
use Tui\PageBundle\TuiPageBundle;

/**
 * A minimal app that uses the bundle the way a real one does: concrete entities extending the
 * abstract ones (with an extra property), attribute routes, a search transformer.
 *
 * Options:
 *  - search: bool, index into the Typesense server at TYPESENSE_URL
 *  - search_index: string, collection prefix (lets each test use its own collections)
 *  - valid_languages: string[]
 *  - default_access_roles: bool, leave access_roles out of the config so the bundle's defaults apply
 *    (otherwise every endpoint is open)
 *  - cache_salt: string, forces a freshly compiled container
 */
class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const COMPONENTS = ['Text', 'ArticleGrid', 'PageHero', 'PageText', 'PageNumberedList', 'PageQuote', 'PageAccordionItem', 'PageBanner', 'PageDownloads', 'PageTable'];

    /** @var array{search?: bool, search_index?: string, valid_languages?: string[], default_access_roles?: bool, cache_salt?: string} */
    private array $options;

    /**
     * @param array{search?: bool, search_index?: string, valid_languages?: string[], default_access_roles?: bool, cache_salt?: string} $options
     */
    public function __construct(string $environment, bool $debug, array $options = [])
    {
        ksort($options);
        $this->options = $options;
        parent::__construct($environment, $debug);
    }

    public static function varDir(): string
    {
        return sys_get_temp_dir() . '/tui-page-bundle-tests-' . substr(hash('xxh128', dirname(__DIR__, 2)), 0, 12);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new SecurityBundle();
        yield new TuiPageBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        // Each combination of options and database gets its own compiled container
        return self::varDir() . '/cache/' . substr(hash('xxh128', serialize([$this->options, self::databaseUrl()])), 0, 12);
    }

    public function getLogDir(): string
    {
        return self::varDir() . '/log';
    }

    public static function databaseUrl(): string
    {
        return $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: 'sqlite:///' . self::varDir() . '/test.db';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', $this->frameworkConfig());
        $container->extension('doctrine', $this->doctrineConfig());
        $container->extension('security', $this->securityConfig());

        $tuiPage = [
            'page_class' => Entity\Page::class,
            'page_data_class' => Entity\PageData::class,
            'components' => array_fill_keys(self::COMPONENTS, ['schema' => '%kernel.project_dir%/schemas/Generic.schema.json']),
        ];
        if (!($this->options['default_access_roles'] ?? false)) {
            $tuiPage['access_roles'] = array_fill_keys(['list', 'delete', 'edit', 'create', 'import', 'export', 'history', 'retrieve', 'search'], []);
        }
        $tuiPage['components']['Text'] = ['schema' => '%kernel.project_dir%/schemas/Text.schema.json'];
        $tuiPage['components']['ResourceList'] = ['schema' => '%kernel.project_dir%/schemas/ResourceList.schema.json'];
        $tuiPage['components']['Quote'] = ['schema' => '%kernel.project_dir%/schemas/Quote.schema.json'];
        if ($this->options['search'] ?? false) {
            $tuiPage['search_hosts'] = [(string) (getenv('TYPESENSE_URL') ?: 'http://127.0.0.1:8108')];
            $tuiPage['search_api_key'] = (string) (getenv('TYPESENSE_API_KEY') ?: 'tui-page-test');
            $tuiPage['search_index'] = $this->options['search_index'] ?? 'tuipagetest';
        }
        if (isset($this->options['valid_languages'])) {
            $tuiPage['valid_languages'] = $this->options['valid_languages'];
        }
        $container->extension('tui_page', $tuiPage);

        $container->services()
            ->set('logger', \Psr\Log\NullLogger::class)
            ->set(SearchTransformer::class)
                ->tag('tui_page.transformer')
            ->set(ShoutingSanitizer::class)
                ->tag('tui_page.sanitizer')
            ->set(RedactingMetadataSanitizer::class)
                ->tag('tui_page.metadata_sanitizer');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@TuiPageBundle/Controller/', 'attribute')->prefix('api');
    }

    /**
     * HTTP basic auth against two in-memory users, `admin` and `editor` (password `pw`). Stateless, so
     * tests authenticate per request with PHP_AUTH_USER/PHP_AUTH_PW and no session is needed.
     */
    private function securityConfig(): array
    {
        return [
            'password_hashers' => [\Symfony\Component\Security\Core\User\InMemoryUser::class => 'plaintext'],
            'providers' => ['users' => ['memory' => ['users' => [
                'admin' => ['password' => 'pw', 'roles' => ['ROLE_ADMIN']],
                'editor' => ['password' => 'pw', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['stateless' => true, 'http_basic' => null]],
        ];
    }

    /**
     * Framework config that's free of deprecations on every supported Symfony version.
     */
    private function frameworkConfig(): array
    {
        $config = [
            'secret' => 'test',
            'test' => true,
            'property_info' => ['enabled' => true],
            'serializer' => ['enabled' => true],
            'validation' => ['enabled' => true],
            'router' => ['utf8' => true],
        ];

        if (self::MAJOR_VERSION < 7) {
            $config['http_method_override'] = false;
            $config['handle_all_throwables'] = true;
            $config['php_errors'] = ['log' => true];
            $config['validation']['email_validation_mode'] = 'html5';
            $config['annotations'] = false;
            $config['uid'] = ['default_uuid_version' => 7, 'time_based_uuid_version' => 7];
        }

        if (self::MAJOR_VERSION === 7 && self::MINOR_VERSION >= 3) {
            $config['property_info']['with_constructor_extractor'] = false;
        }

        return $config;
    }

    /**
     * Doctrine config that's free of deprecations on every supported DoctrineBundle and ORM version.
     */
    private function doctrineConfig(): array
    {
        $orm = [
            'auto_mapping' => true,
            'mappings' => [
                'TestApp' => [
                    'type' => 'attribute',
                    'dir' => '%kernel.project_dir%/Entity',
                    'prefix' => 'Tui\PageBundle\Tests\App\Entity',
                    'is_bundle' => false,
                ],
            ],
            'resolve_target_entities' => [PageDataInterface::class => Entity\PageData::class],
        ];
        $dbal = ['url' => self::databaseUrl()];

        $bundle = (string) InstalledVersions::getVersion('doctrine/doctrine-bundle');
        $ormVersion = (string) InstalledVersions::getVersion('doctrine/orm');

        if (version_compare($bundle, '3.0.0', '<')) {
            $orm['controller_resolver'] = ['auto_mapping' => false];
            $orm['report_fields_where_declared'] = true;
            $orm['validate_xml_mapping'] = true;
            $dbal['use_savepoints'] = true;
            if (version_compare($ormVersion, '3.0.0', '<')) {
                $orm['enable_lazy_ghost_objects'] = true;
            }
        }

        // Native lazy objects replace Symfony's lazy ghosts on PHP 8.4+ with ORM 3.4+
        if (\PHP_VERSION_ID >= 80400 && version_compare($ormVersion, '3.4.0', '>=') && version_compare($bundle, '3.0.0', '<')) {
            $orm['enable_native_lazy_objects'] = true;
        }

        return ['dbal' => $dbal, 'orm' => $orm];
    }
}
