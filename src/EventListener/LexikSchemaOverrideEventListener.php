<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\MappingException;
use Lexik\Bundle\TranslationBundle\Entity\TransUnit;

#[AsDoctrineListener(Events::loadClassMetadata)]
class LexikSchemaOverrideEventListener
{
    /**
     * @throws MappingException
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
    }
}
