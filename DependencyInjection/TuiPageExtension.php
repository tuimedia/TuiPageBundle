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
        $transformers = [];
        foreach ($mergedConfig['components'] as $component => $componentConfig) {
            $schemas[$component] = $componentConfig['schema'];
            $mappings[$component] = $componentConfig['mapping'];
        }

        $container->setParameter('tui_page.search_hosts', $mergedConfig['search_hosts']);
        $container->setParameter('tui_page.search_index', $mergedConfig['search_index']);
        $container->setParameter('tui_page.schemas', $schemas);
        $container->setParameter('tui_page.transformers', $transformers);
        $container->setParameter('tui_page.mappings', $mappings);
        $container->setParameter('tui_page.page_class', $mergedConfig['page_class']);
        $container->setParameter('tui_page.page_data_class', $mergedConfig['page_data_class']);
    }
}
