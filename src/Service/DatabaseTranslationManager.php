<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Service;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use WhiteDigital\Translation\Entity\Translation;

/**
 * @deprecated
 */
final readonly class DatabaseTranslationManager implements LoaderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws Exception
     */
    public function load(mixed $resource, string $locale, string $domain = 'messages'): MessageCatalogue
    {
        $schemaManager = $this->em->getConnection()->createSchemaManager();
        $values = [];

        if ($schemaManager->tablesExist(['translation'])) {
            $messages = $this->em->getRepository(Translation::class)->findBy(['locale' => $locale]);

            foreach ($messages as $message) {
                $values[$message->getDomain()][$message->getKey()] = $message->getTranslation();
            }
        }

        return new MessageCatalogue($locale, $values);
    }
}
