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
        foreach ($mergedConfig['components'] as $component => $componentConfig) {
            $schemas[$component] = $componentConfig['schema'];
        }

        $container->setParameter('tui_page.schemas', $schemas);
    }
}
