<?php declare(strict_types = 1);

namespace WhiteDigital\Translation;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use WhiteDigital\EntityResourceMapper\DependencyInjection\Traits\DefineApiPlatformMappings;
use WhiteDigital\EntityResourceMapper\DependencyInjection\Traits\DefineOrmMappings;
use WhiteDigital\EntityResourceMapper\EntityResourceMapperBundle;
use WhiteDigital\Translation\DependencyInjection\CompilerPass\TranslationCacheCompilerPass;

use function array_key_exists;
use function array_merge_recursive;
use function array_unique;
use function is_array;

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

    private const GEDMO_MAPPINGS = [
        'type' => 'attribute',
        'dir' => '%kernel.project_dir%/vendor/gedmo/doctrine-extensions/src/Translatable/Entity',
        'alias' => 'Gedmo',
        'prefix' => 'Gedmo\Translatable\Entity',
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

        /* @deprecated */
        $this->addDoctrineConfig($container, $manager, 'Translation', self::MAPPINGS);
        $this->addDoctrineConfig($container, $manager, 'Gedmo', self::GEDMO_MAPPINGS);
        $this->addDoctrineConfig($container, $manager, 'LexikTranslationBundle', []);

        if ([] !== $auditExtensionConfig) {
            $mappings = $this->getOrmMappings($builder, $auditExtensionConfig['default_entity_manager'] ?? 'default');
            /* @deprecated */
            $this->addDoctrineConfig($container, $auditExtensionConfig['audit_entity_manager'] ?? 'audit', 'Translation', self::MAPPINGS, $mappings);
            $this->addDoctrineConfig($container, $auditExtensionConfig['audit_entity_manager'] ?? 'audit', 'Gedmo', self::GEDMO_MAPPINGS, $mappings);
            $this->addDoctrineConfig($container, $auditExtensionConfig['audit_entity_manager'] ?? 'audit', 'LexikTranslationBundle', []);
        }

        if ($builder->hasExtension('doctrine_migrations')) {
            $container->extension('doctrine_migrations', [
                'migrations_paths' => [
                    'WhiteDigital\\Translation\\Migrations' => '%kernel.project_dir%/vendor/whitedigital-eu/translation-bundle/migrations',
                ],
            ]);
        }

        $locale = self::getLocale($builder);
        if ($builder->hasExtension('lexik_translation')) {
            $container->extension('lexik_translation', [
                'fallback_locale' => $extensionConfig['locale'] ?? $locale,
                'managed_locales' => [
                    $extensionConfig['locale'] ?? $locale,
                ],
            ]);
        }

        if ($builder->hasExtension('framework')) {
            $container->extension('framework', [
                'set_locale_from_accept_language' => true,
            ]);
        }

        $stof = [
            'orm' => [
                $manager => [
                    'translatable' => true,
                ],
            ],
            'translation_fallback' => $extensionConfig['translation_fallback'] ?? false,
        ];

        $stof['default_locale'] = $locale;

        $container->extension('stof_doctrine_extensions', $stof);
        $this->configureApiPlatformExtension($container, $extensionConfig);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        foreach (EntityResourceMapperBundle::makeOneDimension(['whitedigital.translation' => $config]) as $key => $value) {
            $builder->setParameter($key, $value);
        }

        $managedLocales = $builder->getParameter('whitedigital.translation.managed_locales');
        if (!is_array($managedLocales)) {
            $managedLocales = [];
        }

        /* @var array $managedLocales */
        $managedLocales[] = $builder->getParameter('whitedigital.translation.locale');
        $builder->setParameter('whitedigital.translation.managed_locales', array_unique($managedLocales));

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
                ->scalarNode('custom_api_resource_path')->defaultNull()->end()
                ->scalarNode('locale')->defaultValue('lv')->info('default_locale')->end()
                ->scalarNode('cache_pool')->defaultNull()->end()
                ->booleanNode('translation_fallback')->defaultFalse()->end()
                ->arrayNode('managed_locales')
                    ->scalarPrototype()->end()
                ->end()
            ->end();
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TranslationCacheCompilerPass());
    }

    public static function getLocale(ContainerBuilder $builder): ?string
    {
        $framework = self::getConfig('framework', $builder);
        $locale = $framework['default_locale'];
        if (str_contains($locale, '%') && !str_contains($locale, '%env')) {
            if ($builder->hasParameter($key = strtr($locale, ['%' => '']))) {
                $locale = $builder->getParameter($key);
            }
        }

        if (str_contains($locale, '%env')) {
            $locale = $_ENV[strtr($locale, ['%env(' => '', ')%' => ''])] ?? null;
        }

        return $locale ?? 'lv';
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
