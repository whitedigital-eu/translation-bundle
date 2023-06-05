<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use WhiteDigital\Translation\Entity\Translation;

final readonly class DatabaseTranslationManager implements LoaderInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function load(mixed $resource, string $locale, string $domain = 'messages'): MessageCatalogue
    {
        $messages = $this->em->getRepository(Translation::class)->findBy(['locale' => $locale, 'isActive' => true]);
        $values = [];

        foreach ($messages as $message) {
            $values[$message->getDomain()][$message->getKey()] = $message->getTranslation();
        }

        return new MessageCatalogue($locale, $values);
    }
}
