<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\DataProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\TranslationBundle\Entity\TransUnit;
use Lexik\Bundle\TranslationBundle\Manager\TransUnitManagerInterface;
use ReflectionException;
use WhiteDigital\Translation\DataProvider\TransUnitDataProvider;

use function array_map;
use function in_array;

readonly class TransUnitDataProcessor implements ProcessorInterface
{
    public function __construct(
        private TransUnitManagerInterface $transUnit,
        private EntityManagerInterface $entityManager,
        private TransUnitDataProvider $transUnitDataProvider,
    ) {
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array|object|null
    {
        $isDeleted = $data->isDeleted;
        if ($operation instanceof Patch) {
            $unit = $this->transUnitDataProvider->findByIdOrThrowException($uriVariables['id']);
            $locales = array_map(static fn ($obj) => $obj->getLocale(), $unit->getTranslations()->toArray());
            foreach ($data->translations as $locale => $value) {
                if (in_array($locale, $locales, true)) {
                    $this->transUnit->updateTranslation($unit, $locale, $value);
                } else {
                    $this->transUnit->addTranslation($unit, $locale, $value);
                }
            }
            foreach ($unit->getTranslations() as $translation) {
                $translation->setModifiedManually(true);
            }
        } else {
            $unit = $this->transUnit->create($data->key, $data->domain);
            foreach ($data->translations as $locale => $value) {
                $this->transUnit->addTranslation($unit, $locale, $value);
            }
        }

        $this->entityManager->persist($unit);
        $this->entityManager->flush();

        $this->entityManager->getConnection()->executeStatement('UPDATE lexik_trans_unit SET is_deleted = :isDeleted WHERE id = :id', ['isDeleted' => (int) $isDeleted, 'id' => $unit->getId()]);

        $unit = $this->entityManager->find(TransUnit::class, $unit->getId());
        $this->entityManager->refresh($unit);

        return $this->transUnitDataProvider->getItem(entity: $unit);
    }
}
