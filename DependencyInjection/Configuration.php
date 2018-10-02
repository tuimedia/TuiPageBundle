<?php
namespace Tui\PageBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('tui_page');

        $rootNode
            ->fixXmlConfig('component')
            ->children()
                ->scalarNode('page_class')
                    ->info('The class name of your Page entity')
                    ->defaultValue('App\Entity\Page')
                    ->isRequired()
                ->end()
                ->scalarNode('page_data_class')
                    ->info('The class name of your PageData entity')
                    ->defaultValue('App\Entity\PageData')
                    ->isRequired()
                ->end()
                ->arrayNode('components')
                    ->info('Frontend components')
                    ->isRequired()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('schema')
                                ->info('Path to JSON schema for this component')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}