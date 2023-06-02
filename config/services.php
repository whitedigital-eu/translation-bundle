<?php declare(strict_types = 1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use WhiteDigital\Translation\Service\DatabaseTranslationManager;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set('translation.loader.db')
        ->class(DatabaseTranslationManager::class)
        ->tag('translation.loader', ['alias' => 'db']);

    $services->load(namespace: 'WhiteDigital\\Translation\\', resource: __DIR__ . '/../src/*')
        ->exclude(excludes: [__DIR__ . '/../src/{Entity}']);
};
