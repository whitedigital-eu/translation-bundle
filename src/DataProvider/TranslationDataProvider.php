<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\DataProvider;

use ApiPlatform\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Operation;
use ReflectionException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\EntityResourceMapper\DataProvider\AbstractDataProvider;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\Translation\ApiResource\TranslationResource;

use function array_key_exists;
use function in_array;

class TranslationDataProvider extends AbstractDataProvider
{
    /**
     * @throws ReflectionException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (in_array(TranslationResource::LIST, $operation->getNormalizationContext()['groups'] ?? [], true)) {
            return $this->getList($operation);
        }

        if (array_key_exists('id', $uriVariables)) {
            return $this->getItem($operation, $uriVariables['id'], $context);
        }

        return $this->getCollection($operation, $context);
    }

    /**
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException
     * @throws ReflectionException
     */
    protected function createResource(BaseEntity $entity, array $context): TranslationResource
    {
        return TranslationResource::create($entity, $context);
    }

    protected function getList(Operation $operation): TranslationResource
    {
        $translations = $this->entityManager->getRepository($this->getEntityClass($operation))->findBy(['isActive' => true]);
        $result = [];
        foreach ($translations as $translation) {
            $result[$translation->getLocale()][$translation->getDomain()][$translation->getKey()] = $translation->getTranslation();
        }

        $resource = new TranslationResource();
        $resource->translations = $result;

        return $resource;
    }
}
