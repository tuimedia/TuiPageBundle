<?php
namespace Tui\PageBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TransformerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $transformers = [];

        foreach ($container->findTaggedServiceIds('tui_page.transformer') as $id => $tags) {
            $transformers[] = new Reference($id);
        }

        foreach ($container->findTaggedServiceIds('tui_page.transformer_consumer') as $id => $tags) {
            $factoryDef = $container->getDefinition($id);
            $factoryDef->addMethodCall('setTransformers', [$transformers]);
        }
    }
}
