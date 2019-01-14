<?php
namespace Tui\PageBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('tui_page');

        $treeBuilder
            ->getRootNode()
            ->fixXmlConfig('component')
            ->fixXmlConfig('search_host')
            ->children()
                ->arrayNode('search_hosts')
                    ->info('ElasticSearch host(s) (if unset, search will be disabled)')
                    ->beforeNormalization()
                        ->castToArray()
                    ->end()
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode('search_index')
                    ->info('Search index to use')
                    ->defaultValue('tuipage')
                ->end()
                ->scalarNode('page_class')
                    ->info('The class name of your Page entity')
                    ->defaultValue('App\Entity\Page')
                ->end()
                ->scalarNode('page_data_class')
                    ->info('The class name of your PageData entity')
                    ->defaultValue('App\Entity\PageData')
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
                            ->arrayNode('mapping')
                                ->info('Optional search mapping')
                                ->variablePrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}