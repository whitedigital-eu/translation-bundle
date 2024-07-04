<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\DataProcessor;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\TranslationBundle\Manager\TransUnitManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use WhiteDigital\EntityResourceMapper\Security\AuthorizationService;
use WhiteDigital\EntityResourceMapper\Traits\Override;
use WhiteDigital\Translation\DataProvider\TransUnitDataProvider;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;

readonly class TransUnitDataProcessor implements ProcessorInterface
{
    use Override;

    public function __construct(
        protected TransUnitManagerInterface $transUnit,
        protected EntityManagerInterface $entityManager,
        protected TransUnitDataProvider $transUnitDataProvider,
        protected AuthorizationService $authorizationService,
        protected TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array|object|null
    {
        if (!$operation instanceof Delete) {
            if ($operation instanceof Patch) {
                return $this->patch($data, $operation, $uriVariables, $context);
            }

            return $this->post($data, $operation, $context);
        }

        return $this->delete($data, $operation, $uriVariables, $context);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws Exception
     */
    private function patch(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array|object|null
    {
        $this->authorizationService->setAuthorizationOverride(fn () => $this->override(AuthorizationService::ITEM_PATCH, $operation->getClass()));
        $this->authorizationService->authorizeSingleObject($data, AuthorizationService::ITEM_PATCH, context: $context);
        $unit = $this->transUnitDataProvider->findByIdOrThrowException($uriVariables['id']);
        $locales = array_map(static fn ($obj) => $obj->getLocale(), $unit->getTranslations()->toArray());
        foreach ($data->translations as $locale => $value) {
            if (in_array($locale, $locales, true)) {
                $this->transUnit->updateTranslation($unit, $locale, $value);
            } else {
                $this->transUnit->addTranslation($unit, $locale, $value);
            }
            $locales = array_values(array_filter($locales, static fn (string $m) => $m !== $locale));
        }

        foreach ($unit->getTranslations() as $translation) {
            /* @noinspection PhpPossiblePolymorphicInvocationInspection */
            $translation->setModifiedManually(true);
            $this->entityManager->persist($translation);
        }

        foreach ($locales as $locale) {
            $translation = $unit->getTranslation($locale);
            if (null !== $translation) {
                $this->transUnit->updateTranslation($unit, $locale, '__' . $unit->getKey());
                $translation->setModifiedManually(false);
                $this->entityManager->persist($translation);
            }
        }

        $this->entityManager->persist($unit);
        $this->entityManager->flush();

        $this->entityManager->refresh($unit);

        return $this->transUnitDataProvider->getItem(entity: $unit);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws Exception
     */
    private function post(mixed $data, Operation $operation, array $context = []): array|object|null
    {
        $this->authorizationService->setAuthorizationOverride(fn () => $this->override(AuthorizationService::COL_POST, $operation->getClass()));
        $this->authorizationService->authorizeSingleObject($data, AuthorizationService::COL_POST, context: $context);
        $unit = $this->transUnit->create($data->key, $data->domain);
        foreach ($data->translations as $locale => $value) {
            $this->transUnit->addTranslation($unit, $locale, $value);
        }

        foreach ($unit->getTranslations() as $translation) {
            /* @noinspection PhpPossiblePolymorphicInvocationInspection */
            $translation->setModifiedManually(true);
            $this->entityManager->persist($translation);
        }

        $this->entityManager->persist($unit);
        $this->entityManager->flush();

        $this->entityManager->refresh($unit);

        return $this->transUnitDataProvider->getItem(entity: $unit);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function delete(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array|object|null
    {
        $this->authorizationService->setAuthorizationOverride(fn () => $this->override(AuthorizationService::ITEM_DELETE, $operation->getClass()));
        $this->authorizationService->authorizeSingleObject($data, AuthorizationService::ITEM_DELETE, context: $context);
        $unit = $this->transUnitDataProvider->findByIdOrThrowException($uriVariables['id']);

        if (true !== $unit->isDeleted) {
            throw new BadRequestHttpException($this->translator->trans('error.transUnitDeleteOnlyIfDeleted', domain: 'whitedigital'));
        }

        foreach ($unit->getTranslations() as $translation) {
            $unit->removeTranslation($translation);
            $this->entityManager->remove($translation);
        }
        $this->entityManager->remove($unit);
        $this->entityManager->flush();

        return null;
    }
}
