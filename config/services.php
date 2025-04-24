<?php declare(strict_types = 1);

use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use WhiteDigital\Translation\Service\DatabaseTranslationManager;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set('translation.loader.db')
        ->class(DatabaseTranslationManager::class)
        ->tag('translation.loader', ['alias' => 'db']);

    $services->set(TranslatableListener::class)
        ->tag('doctrine.event_subscriber', ['event' => 'postLoad'])
        ->tag('doctrine.event_subscriber', ['event' => 'postPersist'])
        ->tag('doctrine.event_subscriber', ['event' => 'preFlush'])
        ->tag('doctrine.event_subscriber', ['event' => 'onFlush'])
        ->tag('doctrine.event_subscriber', ['event' => 'loadClassMetadata'])
        ->call('setDefaultLocale', [param('stof_doctrine_extensions.default_locale')])
        ->call('setTranslationFallback', [param('stof_doctrine_extensions.translation_fallback')]);

    $services->load(namespace: 'WhiteDigital\\Translation\\', resource: __DIR__ . '/../src/*')
        ->exclude(excludes: [__DIR__ . '/../src/{Entity}']);
};
