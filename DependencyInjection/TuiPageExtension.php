<?php
namespace Tui\PageBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class TuiPageExtension extends ConfigurableExtension
{
    public function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $schemas = [];
        $mappings = [];
        $transformers = [];
        foreach ($mergedConfig['components'] as $component => $componentConfig) {
            $schemas[$component] = $componentConfig['schema'];
            $mappings[$component] = $componentConfig['mapping'];
        }

        $container->setParameter('tui_page.search_enabled', !!count($mergedConfig['search_hosts']));
        $container->setParameter('tui_page.search_hosts', $mergedConfig['search_hosts']);
        $container->setParameter('tui_page.search_index', $mergedConfig['search_index']);
        $container->setParameter('tui_page.schemas', $schemas);
        $container->setParameter('tui_page.transformers', $transformers);
        $container->setParameter('tui_page.mappings', $mappings);
        $container->setParameter('tui_page.page_class', $mergedConfig['page_class']);
        $container->setParameter('tui_page.page_data_class', $mergedConfig['page_data_class']);
        $container->setParameter('tui_page.serializer_groups.get_response', $mergedConfig['serializer_groups']['get_response']);
        $container->setParameter('tui_page.serializer_groups.history_response', $mergedConfig['serializer_groups']['history_response']);
        $container->setParameter('tui_page.serializer_groups.import_response', $mergedConfig['serializer_groups']['import_response']);
        $container->setParameter('tui_page.serializer_groups.list_response', $mergedConfig['serializer_groups']['list_response']);
        $container->setParameter('tui_page.serializer_groups.search_response', $mergedConfig['serializer_groups']['search_response']);
        $container->setParameter('tui_page.serializer_groups.create_request', $mergedConfig['serializer_groups']['create_request']);
        $container->setParameter('tui_page.serializer_groups.create_response', $mergedConfig['serializer_groups']['create_response']);
        $container->setParameter('tui_page.serializer_groups.update_request', $mergedConfig['serializer_groups']['update_request']);
        $container->setParameter('tui_page.serializer_groups.update_response', $mergedConfig['serializer_groups']['update_response']);
    }
}
