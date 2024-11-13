<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TranslationCacheCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $pool = $container->getParameter('whitedigital.translation.cache_pool');
        if (null !== $pool) {
            $container->setDefinition('whitedigital.translation.cache', $container->getDefinition($pool));
        }
    }
}
