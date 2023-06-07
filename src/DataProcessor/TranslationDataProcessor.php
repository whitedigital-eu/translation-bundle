<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\DataProcessor;

use ApiPlatform\Exception\ResourceClassNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use ReflectionException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WhiteDigital\EntityResourceMapper\DataProcessor\AbstractDataProcessor;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\Translation\ApiResource\TranslationResource;
use WhiteDigital\Translation\Entity\Translation;
use WhiteDigital\Translation\Event\TranslationUpdatedEvent;

use function preg_match;

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
        return Translation::create($resource, $context, $existingEntity);
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

    protected function flushAndRefresh(BaseEntity $entity): void
    {
        $this->entityManager->persist($entity);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            preg_match('/DETAIL: (.*)/', $exception->getMessage(), $matches);
            throw new PreconditionFailedHttpException($this->translator->trans('record_already_exists', ['%detail%' => $matches[1]], domain: 'EntityResourceMapper'), $exception);
        }

        $this->dispatcher->dispatch(new TranslationUpdatedEvent(), TranslationUpdatedEvent::EVENT);
        $this->entityManager->refresh($entity);
    }
}
