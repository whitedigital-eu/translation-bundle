<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\MappingException;
use Lexik\Bundle\TranslationBundle\Entity\TransUnit;

#[AsDoctrineListener(Events::loadClassMetadata)]
final readonly class LexikSchemaOverrideEventListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws MappingException
     * @throws Exception
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();

        if (TransUnit::class !== $classMetadata->getName()) {
            return;
        }

        if (!$classMetadata->hasField('isDeleted')) {
            $classMetadata->mapField([
                'fieldName' => 'isDeleted',
                'type' => 'boolean',
                'nullable' => true,
                'options' => ['default' => false],
                'default' => false,
                'columnName' => 'is_deleted',
                'declared' => \WhiteDigital\Translation\Entity\TransUnit::class,
            ]);
        }

        $this->ensureIndex();
    }

    /**
     * @throws Exception
     */
    private function ensureIndex(): void
    {
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            $localeIdxSql = 'DO $$ BEGIN
                        IF NOT EXISTS (SELECT 1 FROM pg_class WHERE relname = \'idx_translations_locale\') THEN
                            CREATE INDEX idx_translations_locale ON lexik_trans_unit_translations (locale);
                        END IF;
                    END $$;';
            $connection->executeQuery($localeIdxSql);

            $isDeletedIdxSql = 'DO $$ BEGIN
                        IF NOT EXISTS (SELECT 1 FROM pg_class WHERE relname = \'idx_trans_unit_is_deleted\') THEN
                            CREATE INDEX idx_trans_unit_is_deleted ON lexik_trans_unit (is_deleted);
                        END IF;
                    END $$;';
            $connection->executeQuery($isDeletedIdxSql);
        }
    }
}
