<?php

declare(strict_types=1);

namespace LibertJeremy\Symfony\ConfigInitializerBundle;

use LibertJeremy\Symfony\ConfigHelpers\Bundle\AbstractBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class ConfigInitializerBundle extends AbstractBundle
{
    public const string CONFIGURATION_NODE_ENABLE_DEBUG = 'enable_debug';
    public const string CONFIGURATION_NODE_ENABLE_PROFILER = 'enable_profiler';
    public const string CONFIGURATION_NODE_ENABLE_ROUTER = 'enable_router';
    public const string CONFIGURATION_NODE_ENABLE_SECURITY = 'enable_security';
    public const string CONFIGURATION_NODE_ENABLE_VALIDATION = 'enable_validation';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition
            ->rootNode()
            ->children()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_DEBUG)->defaultFalse()->end()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_PROFILER)->defaultFalse()->end()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_ROUTER)->defaultFalse()->end()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_SECURITY)->defaultFalse()->end()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_VALIDATION)->defaultFalse()->end()
            ->end();
    }

    #[\Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        $env = $builder->getParameter('kernel.environment');

        if (true === $config[self::CONFIGURATION_NODE_ENABLE_DEBUG]) {
            $this->prependDebug($builder, $env);
        }

        if (true === $config[self::CONFIGURATION_NODE_ENABLE_PROFILER]) {
            $this->prependFrameworkProfiler($builder, $env);
        }

        if (true === $config[self::CONFIGURATION_NODE_ENABLE_ROUTER]) {
            $this->prependFrameworkRouter($builder, $env);
        }

        if (true === $config[self::CONFIGURATION_NODE_ENABLE_SECURITY]) {
            $this->prependSecurity($builder, $env);
        }

        if (true === $config[self::CONFIGURATION_NODE_ENABLE_VALIDATION]) {
            $this->prependFrameworkValidation($builder, $env);
        }
    }

    private function prependDebug(ContainerBuilder $builder, string $env): void
    {
        if ('dev' === $env || 'test' === $env) {
            $builder->prependExtensionConfig('debug', [
                //'dump_destination' => 'tcp://%env(VAR_DUMPER_SERVER)%',
            ]);
        }
    }

    private function prependFrameworkProfiler(ContainerBuilder $builder, string $env): void
    {
        if ('dev' === $env) {
            $builder->prependExtensionConfig('framework', [
                'profiler' => [
                    'collect_serializer_data' => true,
                ],
            ]);
        } elseif ('test' === $env) {
            $builder->prependExtensionConfig('framework', [
                'profiler' => [
                    'collect' => false,
                ],
            ]);
        }
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

    private function prependSecurity(ContainerBuilder $builder, string $env): void
    {
        $builder->prependExtensionConfig('security', [
            'access_decision_manager' => [
                'strategy' => 'unanimous',
            ],
        ]);

        if ('dev' === $env || 'test' === $env) {
            $builder->prependExtensionConfig('security', [
                'password_hashers' => [
                    PasswordAuthenticatedUserInterface::class => [
                        'algorithm' => 'auto',
                        'cost' => 4,
                        'time_cost' => 3,
                        'memory_cost' => 10,
                    ],
                ],
            ]);
        } else {
            $builder->prependExtensionConfig('security', [
                'password_hashers' => [
                    PasswordAuthenticatedUserInterface::class => 'auto',
                ],
            ]);
        }
    }

    private function prependFrameworkValidation(ContainerBuilder $builder, string $env): void
    {
        if ('dev' === $env || 'test' === $env) {
            $builder->prependExtensionConfig('framework', [
                'validation' => [
                    'not_compromised_password' => false,
                ],
            ]);
        }
    }
}
