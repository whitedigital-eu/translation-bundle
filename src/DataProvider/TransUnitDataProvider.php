<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\DataProvider;

use ApiPlatform\Doctrine\Orm\Extension\FilterExtension;
use ApiPlatform\Doctrine\Orm\Extension\OrderExtension;
use ApiPlatform\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Lexik\Bundle\TranslationBundle\Entity\TransUnit;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use WhiteDigital\Translation\Api\Resource\TransUnitResource;
use WhiteDigital\Translation\Service\CollectionCount;

use function array_merge_recursive;
use function array_shift;
use function explode;
use function in_array;
use function is_array;
use function ksort;
use function preg_match;
use function sprintf;
use function strtolower;

use const SORT_FLAG_CASE;
use const SORT_NATURAL;

readonly class TransUnitDataProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private CollectionCount $count,
        #[TaggedIterator('api_platform.doctrine.orm.query_extension.collection')]
        protected iterable $collectionExtensions = [],
    ) {
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (in_array(TransUnitResource::LIST, $operation->getNormalizationContext()['groups'] ?? [], true)) {
            return $this->list($uriVariables);
        }

        if ($operation instanceof GetCollection) {
            return $this->getCollection($operation, $context);
        }

        return $this->getItem($uriVariables['id']);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function getItem(mixed $id = null, ?object $entity = null): object
    {
        $entity ??= $this->findByIdOrThrowException($id);

        $resource = new TransUnitResource();
        $resource->id = $entity->getId();
        $resource->key = $entity->getKey();
        $resource->domain = $entity->getDomain();
        $resource->translations = [];

        try {
            $resource->isDeleted = $entity->isDeleted;
        } catch (Throwable) {
            $resource->isDeleted = (bool) $this->entityManager->getConnection()->executeStatement('SELECT is_deleted FROM lexik_trans_unit WHERE id = :id', ['id' => $id]);
        }

        foreach ($entity->getTranslations() as $translation) {
            $resource->translations[$translation->getLocale()] = $translation->getContent();
        }

        return $resource;
    }

    /**
     * @throws ReflectionException
     */
    public function findByIdOrThrowException(mixed $id): TransUnit
    {
        $entity = $this->entityManager->getRepository($entityClass = TransUnit::class)->find($id);
        $this->throwErrorIfNotExists($entity, strtolower((new ReflectionClass($entityClass))->getShortName()), $id);

        return $entity;
    }

    protected function throwErrorIfNotExists(mixed $result, string $rootAlias, mixed $id): void
    {
        if (null === $result) {
            throw new NotFoundHttpException($this->translator->trans('named_resource_not_found', ['%resource%' => $rootAlias, '%id%' => $id], domain: 'EntityResourceMapper'));
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function getCollection(Operation $operation, array $context = []): array|object
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('e')->from(TransUnit::class, 'e');

        $collection = $this->applyFilterExtensionsToCollection($queryBuilder, new QueryNameGenerator(), $operation, $context);
        $result = [];
        $this->count->setCount((int) $collection->getTotalItems());
        foreach ($collection->getIterator()->getArrayCopy() as $item) {
            $result[] = $this->getItem(entity: $item);
        }

        return $result;
    }

    protected function applyFilterExtensionsToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, Operation $operation, array $context = []): array|object
    {
        foreach ($this->collectionExtensions as $extension) {
            if ($extension instanceof FilterExtension
                || $extension instanceof QueryResultCollectionExtensionInterface) {
                $extension->applyToCollection($queryBuilder, $queryNameGenerator, $operation->getClass(), $operation, $context);
            }

            if ($extension instanceof OrderExtension) {
                $orderByDqlPart = $queryBuilder->getDQLPart('orderBy');
                if ([] !== $orderByDqlPart) {
                    continue;
                }

                foreach ($operation->getOrder() as $field => $direction) {
                    $queryBuilder->addOrderBy(sprintf('%s.%s', $queryBuilder->getRootAliases()[0], $field), $direction);
                }
            }

            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($operation->getClass(), $operation, $context)) {
                return $extension->getResult($queryBuilder, $operation->getClass(), $operation, $context);
            }
        }

        return $queryBuilder->getQuery()->getResult();
    }

    private function list(array $uriVariables = []): object|array|null
    {
        $transUnits = $this->entityManager->getRepository(TransUnit::class)->findAll();
        $result = [];
        foreach ($transUnits as $transUnit) {
            if (!$transUnit->isDeleted) {
                $found = false;
                foreach ($transUnit->getTranslations() as $translation) {
                    $result[$translation->getLocale()][$transUnit->getDomain()][$transUnit->getKey()] = $translation->getContent();
                    if ($uriVariables['locale'] === $translation->getLocale()) {
                        $found = true;
                    }
                }
                if (!$found) {
                    $result[$uriVariables['locale']][$transUnit->getDomain()][$transUnit->getKey()] = $transUnit->getKey();
                }
            }
        }

        $result = $result[$uriVariables['locale']];
        foreach ($result as $domain => $keys) {
            $matchingKeys = $nonMatchingKeys = [];

            foreach ($keys as $key => $value) {
                if (preg_match('/^([a-zA-Z0-9_-]+\.)+[a-zA-Z0-9_-]+$/', $key)) {
                    $matchingKeys[$key] = $value;
                } else {
                    $nonMatchingKeys[$key] = $value;
                }
            }

            $result[$domain] = array_merge_recursive($nonMatchingKeys, $this->convert($matchingKeys));
        }

        ksort($result);

        $resource = new TransUnitResource();
        $resource->id = 0;
        $resource->translations = $result;

        return $resource;
    }

    private function convert(array $input): array
    {
        $result = [];

        foreach ($input as $dotted => $translation) {
            $keys = explode('.', (string) $dotted);
            $current = &$result[array_shift($keys)];

            foreach ($keys as $key) {
                if (isset($current[$key]) && $translation === $current[$key]) {
                    $current[$key] = [];
                }

                $current = &$current[$key];
            }

            if (null === $current) {
                $current = $translation;
            }
        }

        return $result;
    }
}
