<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\DataProcessor;

use ApiPlatform\Exception\ResourceClassNotFoundException;
use ReflectionException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\EntityResourceMapper\DataProcessor\AbstractDataProcessor;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\Translation\ApiResource\TranslationResource;
use WhiteDigital\Translation\Entity\Translation;
use WhiteDigital\Translation\Event\TranslationUpdatedEvent;

class TranslationDataProcessor extends AbstractDataProcessor
{
    protected EventDispatcherInterface $dispatcher;

    #[Required]
    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    public function getEntityClass(): string
    {
        return Translation::class;
    }

    protected function createEntity(BaseResource $resource, array $context, ?BaseEntity $existingEntity = null): Translation
    {
        $entity = Translation::create($resource, $context, $existingEntity);
        $this->dispatcher->dispatch(new TranslationUpdatedEvent(), TranslationUpdatedEvent::EVENT);

        return $entity;
    }

    /**
     * @throws ExceptionInterface
     * @throws ReflectionException
     * @throws ResourceClassNotFoundException
     */
    protected function createResource(BaseEntity $entity, array $context): TranslationResource
    {
        return TranslationResource::create($entity, $context);
    }
}
