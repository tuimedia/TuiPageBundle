<?php
namespace Tui\PageBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TransformerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $factoryDef = $container->getDefinition('Tui\PageBundle\Search\TranslatedPageFactory');
        $transformers = [];

        foreach ($container->findTaggedServiceIds('tui_page.transformer') as $id => $tags) {
            $transformers[] = new Reference($id);
        }

        $factoryDef->replaceArgument(0, $transformers);
    }
}
