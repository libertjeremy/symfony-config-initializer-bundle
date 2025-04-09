<?php

declare(strict_types=1);

namespace LibertJeremy\Symfony\ConfigInitializerBundle;

use LibertJeremy\Symfony\ConfigHelpers\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class ConfigInitializerBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $this->prependFrameworkRouter($builder, $builder->getParameter('kernel.environment'));
    }

    private function prependFrameworkRouter(ContainerBuilder $builder, string $env): void
    {
        if ('prod' === $env) {
            $builder->prependExtensionConfig('framework', [
                'router' => [
                    'strict_requirements' => null,
                ],
            ]);
        } elseif ('dev' === $env) {
            $builder->prependExtensionConfig('framework', [
                'router' => [
                    'strict_requirements' => true,
                ],
            ]);
        }
    }
}
