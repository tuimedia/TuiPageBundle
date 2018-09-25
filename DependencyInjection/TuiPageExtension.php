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
            if (!file_exists($componentConfig['schema'])) {
                throw new \Exception(vsprintf('%s Component schema not found: %s', [
                    $component,
                    $componentConfig['schema'],
                ]));
            }
            $schemas[$component] = $componentConfig['schema'];
        }

        $container->setParameter('tui_page.schemas', $schemas);
    }
}
