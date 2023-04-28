<?php
namespace Tui\PageBundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tui\PageBundle\DependencyInjection\TransformerPass;

class TuiPageBundle extends Bundle
{
    public const VERSION = 1.0;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TransformerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }
}
