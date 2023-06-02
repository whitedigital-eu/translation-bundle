<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\DataProvider;

use ApiPlatform\Exception\ResourceClassNotFoundException;
use ReflectionException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\EntityResourceMapper\DataProvider\AbstractDataProvider;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\Translation\ApiResource\TranslationResource;

class TranslationDataProvider extends AbstractDataProvider
{
    /**
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException
     * @throws ReflectionException
     */
    protected function createResource(BaseEntity $entity, array $context): TranslationResource
    {
        return TranslationResource::create($entity, $context);
    }
}
