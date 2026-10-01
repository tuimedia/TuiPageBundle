<?php

namespace Tui\PageBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tui\PageBundle\DependencyInjection\Configuration;
use Tui\PageBundle\DependencyInjection\TuiPageExtension;

class ConfigurationTest extends TestCase
{
    private const MINIMAL = ['components' => ['Text' => ['schema' => '/schemas/Text.schema.json']]];

    public function testMinimalConfigGetsDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [self::MINIMAL]);

        self::assertSame([], $config['search_hosts']);
        self::assertNull($config['search_api_key']);
        self::assertSame('tuipage', $config['search_index']);
        self::assertSame([], $config['valid_languages']);
        self::assertSame('App\Entity\Page', $config['page_class']);
        self::assertSame('App\Entity\PageData', $config['page_data_class']);
        self::assertSame(
            array_fill_keys(['get_response', 'list_response', 'create_request', 'create_response', 'update_request', 'update_response', 'search_response', 'history_response', 'import_response'], []),
            $config['serializer_groups'],
        );
    }

    /**
     * Any warning raised while loading (an unset key read without a default, say) fails the test.
     */
    public function testExtensionLoadsMinimalConfigCleanly(): void
    {
        $container = new ContainerBuilder();
        (new TuiPageExtension())->load([self::MINIMAL], $container);

        self::assertFalse($container->getParameter('tui_page.search_enabled'));
        self::assertNull($container->getParameter('tui_page.search_api_key'));
        self::assertSame([], $container->getParameter('tui_page.valid_languages'));
        self::assertSame([], $container->getParameter('tui_page.serializer_groups.get_response'));
        self::assertSame(['Text' => '/schemas/Text.schema.json'], $container->getParameter('tui_page.schemas'));
    }

    public function testAccessRolesDefaultWhenOmitted(): void
    {
        $container = new ContainerBuilder();
        (new TuiPageExtension())->load([self::MINIMAL], $container);

        foreach (['list', 'retrieve', 'export', 'search'] as $check) {
            self::assertSame([], $container->getParameter('tui_page.access_roles.' . $check), $check);
        }
        foreach (['create', 'edit', 'delete', 'import', 'history'] as $check) {
            self::assertSame(['ROLE_ADMIN'], $container->getParameter('tui_page.access_roles.' . $check), $check);
        }
    }

    public function testAccessRolesFillInKeysLeftOut(): void
    {
        $container = new ContainerBuilder();
        (new TuiPageExtension())->load([self::MINIMAL + ['access_roles' => ['edit' => 'ROLE_EDITOR', 'delete' => []]]], $container);

        self::assertSame(['ROLE_EDITOR'], $container->getParameter('tui_page.access_roles.edit'));
        self::assertSame([], $container->getParameter('tui_page.access_roles.delete'));
        self::assertSame(['ROLE_ADMIN'], $container->getParameter('tui_page.access_roles.history'));
        self::assertSame([], $container->getParameter('tui_page.access_roles.list'));
    }

    public function testSearchIsEnabledByConfiguringAHost(): void
    {
        $container = new ContainerBuilder();
        (new TuiPageExtension())->load([self::MINIMAL + ['search_hosts' => 'http://typesense:8108', 'search_api_key' => 'key']], $container);

        self::assertTrue($container->getParameter('tui_page.search_enabled'));
        self::assertSame(['http://typesense:8108'], $container->getParameter('tui_page.search_hosts'));
    }
}
