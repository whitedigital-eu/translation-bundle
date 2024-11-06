<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\EventSubscriber;

use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\LocaleAwareInterface;

readonly class DoctrineExtensionSubscriber implements EventSubscriberInterface, LocaleAwareInterface
{
    public function __construct(
        private TranslatableListener $translatableListener,
        private ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * Set the translatable locale on each request based on the current request locale
     * Note: The priority is set to 5 to ensure that this event is triggered after the symfony's LocaleListener and
     * before the ApiPlatform's ReadListener!
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
            ConsoleCommandEvent::class => 'onConsoleCommand',
        ];
    }

    /**
     * Set the translatable locale on each request based on the current request locale.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $this->translatableListener->setTranslatableLocale($event->getRequest()->getLocale());
    }

    public function setLocale(string $locale): void
    {
        $this->translatableListener->setTranslatableLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->translatableListener->getListenerLocale();
    }

    public function onConsoleCommand(): void
    {
        $this->translatableListener->setTranslatableLocale($this->parameterBag->get('stof_doctrine_extensions.default_locale'));
    }
}
