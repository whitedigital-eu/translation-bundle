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

    private const PATHS = [
        '%kernel.project_dir%/vendor/whitedigital-eu/translation/src/ApiResource',
    ];

    public static function getConfig(string $package, ContainerBuilder $builder): array
    {
        return array_merge_recursive(...$builder->getExtensionConfig($package));
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $audit = self::getConfig('audit', $builder);

        $manager = self::getConfig('translation', $builder)['entity_manager'] ?? 'default';

        $this->addDoctrineConfig($container, $manager, 'Translation', self::MAPPINGS);
        $this->addApiPlatformPaths($container, self::PATHS);

        if ([] !== $audit) {
            $mappings = $this->getOrmMappings($builder, $audit['default_entity_manager'] ?? 'default');
            $this->addDoctrineConfig($container, $audit['audit_entity_manager'] ?? 'audit', 'Translation', self::MAPPINGS, $mappings);
        }
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
            ->end();
    }
}
