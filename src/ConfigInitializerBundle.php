<?php

declare(strict_types=1);

namespace LibertJeremy\Symfony\ConfigInitializerBundle;

use LibertJeremy\Symfony\ConfigHelpers\Bundle\AbstractBundle;
use LibertJeremy\Symfony\ConfigInitializerBundle\Constants\Doctrine;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class ConfigInitializerBundle extends AbstractBundle
{
    public const string CONFIGURATION_NODE_ENABLE_DEBUG = 'enable_debug';
    public const string CONFIGURATION_NODE_ENABLE_DOCTRINE = 'enable_doctrine';
    public const string CONFIGURATION_NODE_ENABLE_PROFILER = 'enable_profiler';
    public const string CONFIGURATION_NODE_ENABLE_ROUTER = 'enable_router';
    public const string CONFIGURATION_NODE_ENABLE_SECURITY = 'enable_security';
    public const string CONFIGURATION_NODE_ENABLE_SESSION = 'enable_session';
    public const string CONFIGURATION_NODE_ENABLE_VALIDATION = 'enable_validation';
    public const string CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTIONS = 'database_connections';
    public const string CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTION_NAME = 'name';
    public const string CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTION_URL = 'url';
    public const string CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTION_SERVER_VERSION = 'server_version';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition
            ->rootNode()
            ->children()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_DEBUG)->defaultFalse()->end()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_DOCTRINE)->defaultFalse()->end()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_PROFILER)->defaultFalse()->end()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_ROUTER)->defaultFalse()->end()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_SECURITY)->defaultFalse()->end()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_SESSION)->defaultFalse()->end()
            ->booleanNode(self::CONFIGURATION_NODE_ENABLE_VALIDATION)->defaultFalse()->end()
            ->arrayNode(self::CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTIONS)
                ->arrayPrototype()
                    ->children()
                        ->scalarNode(self::CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTION_NAME)->end()
                        ->scalarNode(self::CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTION_URL)->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode(self::CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTION_SERVER_VERSION)->end()
                    ->end()
                ->end()
            ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $config = $this->retrieveConfiguration($builder);

        $env = $builder->getParameter('kernel.environment');

        if (true === ($config[self::CONFIGURATION_NODE_ENABLE_DOCTRINE] ?? false)) {
            $this->prependDoctrine($builder, $env, $config[self::CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTIONS] ?? []);
        }
    }

    #[\Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        $env = $builder->getParameter('kernel.environment');

        $this->prependFramework($builder, $env);

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

        if (true === $config[self::CONFIGURATION_NODE_ENABLE_SESSION]) {
            $this->prependFrameworkSession($builder, $env);
        }

        if (true === $config[self::CONFIGURATION_NODE_ENABLE_VALIDATION]) {
            $this->prependFrameworkValidation($builder, $env);
        }
    }

    private function prependFramework(ContainerBuilder $builder, string $env): void
    {
        if ('test' === $env) {
            $builder->prependExtensionConfig('framework', [
                'test' => true,
            ]);
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

    private function prependDoctrine(ContainerBuilder $builder, string $env, array $databaseConnections = []): void
    {
        $doctrineConfiguration = [
            'orm' => [
                'auto_generate_proxy_classes' => $builder->getParameter('kernel.debug'),
                'auto_mapping' => true,
                'default_entity_manager' => Doctrine::DEFAULT_CONNECTION_NAME,
                'enable_lazy_ghost_objects' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'report_fields_where_declared' => true,
                'validate_xml_mapping' => true,
            ],
            'dbal' => [
                'use_savepoints' => true,
            ],
        ];

        $connections = [];
        $connectionNames = [];

        if (!empty($databaseConnections)) {
            foreach ($databaseConnections as $connection) {
                if (empty($name = $connection[self::CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTION_NAME] ?? null)) {
                    $name = Doctrine::DEFAULT_CONNECTION_NAME;
                }

                $connectionNames[] = $name;

                $connections[$name] = [
                    'charset' => 'utf8mb4',
                    'driver' => 'pdo_mysql',
                    'url' => $connection[self::CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTION_URL],
                    'server_version' => $connection[self::CONFIGURATION_NODE_DOCTRINE_DATABASE_CONNECTION_SERVER_VERSION],
                ];
            }

            $doctrineConfiguration = [
                'dbal' => [
                    'default_connection' => $connectionNames[0],
                    'connections' => $connections,
                ],
            ];
        }

        if (
            'test' === $env
            || 'prod' === $env
        ) {
            $connectionSettings = [];

            foreach ($connectionNames as $connectionName) {
                $connectedSettingsForConnectionName = [
                    'logging' => false,
                    'profiling' => false,
                    'profiling_collect_backtrace' => false,
                ];

                if ('test' === $env) {
                    $connectedSettingsForConnectionName['dbname_suffix'] = '_test';
                }

                $connectionSettings[$connectionName] = $connectedSettingsForConnectionName;
            }

            $doctrineConfiguration = array_merge_recursive($doctrineConfiguration, [
                'dbal' => [
                    'connections' => $connectionSettings,
                ],
            ]);
        }

        $builder->prependExtensionConfig('doctrine', $doctrineConfiguration);
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

    private function prependFrameworkSession(ContainerBuilder $builder, string $env): void
    {
        $frameworkConfiguration = [
            'session' => [
                'cookie_secure' => 'auto',
                'cookie_samesite' => 'lax',
                'cookie_lifetime' => 2592000,
                'gc_maxlifetime' => 2592000,
                'name' => substr(($this->retrieveProjectReference($builder)), 0, 6),
                'handler_id' => null,
            ],
        ];

        if ('test' === $env) {
            $frameworkConfiguration = array_merge_recursive($frameworkConfiguration, [
                'session' => [
                    'storage_factory_id' => 'session.storage.factory.mock_file',
                ],
            ]);
        } else {
            $frameworkConfiguration = array_merge_recursive($frameworkConfiguration, [
                'session' => [
                    'storage_factory_id' => 'session.storage.factory.native',
                ],
            ]);
        }

        $builder->prependExtensionConfig('framework', $frameworkConfiguration);
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

    private function retrieveProjectReference(ContainerBuilder $builder): string
    {
        return md5($builder->getParameter('kernel.project_dir').'_'.$builder->getParameter('kernel.environment')).'_'.$builder->getParameter('kernel.secret');
    }
}
