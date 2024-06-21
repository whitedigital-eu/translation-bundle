<?php declare(strict_types = 1);

namespace WhiteDigital\Translation;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use WhiteDigital\EntityResourceMapper\DependencyInjection\Traits\DefineApiPlatformMappings;
use WhiteDigital\EntityResourceMapper\DependencyInjection\Traits\DefineOrmMappings;
use WhiteDigital\EntityResourceMapper\EntityResourceMapperBundle;

use function array_merge_recursive;

class TranslationBundle extends AbstractBundle
{
    use DefineApiPlatformMappings;
    use DefineOrmMappings;

    private const MAPPINGS = [
        'type' => 'attribute',
        'dir' => __DIR__ . '/Entity',
        'alias' => 'Translation',
        'prefix' => 'WhiteDigital\Translation\Entity',
        'is_bundle' => false,
        'mapping' => true,
    ];

    private const API_RESOURCE_PATH = '%kernel.project_dir%/vendor/whitedigital-eu/translation-bundle/src/Api/Resource';

    public static function getConfig(string $package, ContainerBuilder $builder): array
    {
        return array_merge_recursive(...$builder->getExtensionConfig($package));
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $extensionConfig = self::getConfig('translation', $builder);
        $auditExtensionConfig = self::getConfig('audit', $builder);

        $manager = $extensionConfig['entity_manager'] ?? 'default';

        $this->addDoctrineConfig($container, $manager, 'Translation', self::MAPPINGS);
        $this->addDoctrineConfig($container, $manager, 'LexikTranslationBundle', []);

        if ([] !== $auditExtensionConfig) {
            $mappings = $this->getOrmMappings($builder, $auditExtensionConfig['default_entity_manager'] ?? 'default');
            $this->addDoctrineConfig($container, $auditExtensionConfig['audit_entity_manager'] ?? 'audit', 'Translation', self::MAPPINGS, $mappings);
            $this->addDoctrineConfig($container, $auditExtensionConfig['audit_entity_manager'] ?? 'audit', 'LexikTranslationBundle', []);
        }

        if ($builder->hasExtension('doctrine_migrations')) {
            $container->extension('doctrine_migrations', [
                'migrations_paths' => [
                    'WhiteDigital\\Translation\\Migrations' => '%kernel.project_dir%/vendor/whitedigital-eu/translation-bundle/migrations',
                ],
            ]);
        }

        if ($builder->hasExtension('lexik_translation')) {
            $container->extension('lexik_translation', [
                'fallback_locale' => 'lv',
                'managed_locales' => [
                    'lv',
                ],
            ]);
        }

        $this->configureApiPlatformExtension($container, $extensionConfig);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        foreach (EntityResourceMapperBundle::makeOneDimension(['whitedigital.translation' => $config]) as $key => $value) {
            $builder->setParameter($key, $value);
        }

        $container->import('../config/services.php');
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $root = $definition
            ->rootNode();

        $root
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('entity_manager')->defaultValue('default')->end()
                ->arrayNode('domains')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('locales')
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode('custom_api_resource_path')->defaultNull()->end()
            ->end();
    }

    private function configureApiPlatformExtension(ContainerConfigurator $container, array $extensionConfig): void
    {
        if (!array_key_exists('custom_api_resource_path', $extensionConfig)) {
            $this->addApiPlatformPaths($container, [self::API_RESOURCE_PATH]);
        } elseif (!empty($extensionConfig['custom_api_resource_path'])) {
            $this->addApiPlatformPaths($container, [$extensionConfig['custom_api_resource_path']]);
        }
    }
}
