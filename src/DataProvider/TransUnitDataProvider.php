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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use WhiteDigital\SiteTree\Entity\SiteTree;
use WhiteDigital\Translation\Api\Resource\TransUnitResource;
use WhiteDigital\Translation\Entity\TransUnitIsDeleted;
use WhiteDigital\Translation\Service\CollectionCount;

use function array_key_exists;
use function array_map;
use function array_merge_recursive;
use function array_shift;
use function class_exists;
use function explode;
use function filter_var;
use function in_array;
use function is_array;
use function ksort;
use function preg_match;
use function sprintf;
use function strtolower;

use const FILTER_VALIDATE_BOOLEAN;
use const SORT_FLAG_CASE;
use const SORT_NATURAL;

class TransUnitDataProvider implements ProviderInterface
{
    private ?array $locales = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly CollectionCount $count,
        private readonly ParameterBagInterface $bag,
        private readonly ?CacheInterface $whitedigitalTranslationCache = null,
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
        $resource->isDeleted = 0 !== $this->entityManager->getRepository(TransUnitIsDeleted::class)->count(['transUnit' => $entity, 'isDeleted' => true]);

        $found = [];
        foreach ($entity->getTranslations() as $translation) {
            $resource->translations[$translation->getLocale()] = $translation->getContent();
            $found[$translation->getLocale()] = 1;
        }

        foreach ($this->getLocales() as $locale) {
            if (!array_key_exists($locale, $found)) {
                $resource->translations[$locale] = '__' . $entity->getKey();
            }
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

        if (isset($context['filters']) && array_key_exists('isDeleted', $context['filters'])) {
            $check = filter_var($context['filters']['isDeleted'], FILTER_VALIDATE_BOOLEAN);
            $checkQueryBuilder = $this->entityManager->getRepository(TransUnitIsDeleted::class)->createQueryBuilder('td');
            $checkFound = $checkQueryBuilder
                ->select('tu.id')
                ->innerJoin('td.transUnit', 'tu')
                ->where('td.isDeleted = :isDeleted')
                ->setParameter('isDeleted', $check)
                ->getQuery()
                ->useQueryCache(true)
                ->getSingleColumnResult();

            if ([] !== $checkFound) {
                $queryBuilder->andWhere($queryBuilder->expr()->in('e.id', $checkFound));
            } else {
                $queryBuilder->andWhere('1 = 0');
            }
        }

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

    private function getLocales(): array
    {
        if (null === $this->locales) {
            if (class_exists(SiteTree::class)) {
                $trees = $this->entityManager->getRepository(SiteTree::class)->findBy(['parent' => null, 'isActive' => true]);
                $this->locales = array_map(static fn (SiteTree $tree) => $tree->getSlug(), $trees);
            } else {
                $this->locales = [];
            }
        }

        return $this->locales;
    }

    private function list(array $uriVariables = []): object|array|null
    {
        if (null !== $this->whitedigitalTranslationCache && null !== $this->bag->get('whitedigital.translation.cache_pool')) {
            return $this->whitedigitalTranslationCache->get('whitedigital.translation.list.' . $uriVariables['locale'], function (ItemInterface $item) use ($uriVariables) {
                $item->expiresAfter(3600);

                return $this->listData($uriVariables['locale']);
            });
        }

        return $this->listData($uriVariables['locale']);
    }

    private function listData(string $locale): object|array|null
    {
        $transUnits = $this->entityManager->getRepository(TransUnit::class)->createQueryBuilder('tu')
            ->select('tu', 't')
            ->innerJoin('tu.translations', 't')
            ->getQuery()
            ->useQueryCache(true)
            ->getArrayResult();

        $result = [];
        foreach ($transUnits as $transUnit) {
            if (0 !== $this->entityManager->getRepository(TransUnitIsDeleted::class)->count(['transUnit' => $this->entityManager->getReference(TransUnit::class, $transUnit['id']), 'isDeleted' => true])) {
                continue;
            }

            $found = false;
            foreach ($transUnit['translations'] as $translation) {
                $result[$translation['locale']][$transUnit['domain']][$transUnit['key']] = $translation['content'];
                if ($locale === $translation['locale']) {
                    $found = true;
                }
            }
            if (!$found) {
                $result[$locale][$transUnit['domain']][$transUnit['key']] = '__' . $transUnit['key'];
            }
        }

        $result = $result[$locale];

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

        function recursive_ksort(&$array, int $flags = SORT_NATURAL | SORT_FLAG_CASE): bool
        {
            foreach ($array as &$v) {
                if (is_array($v)) {
                    recursive_ksort($v, $flags);
                }
            }
            unset($v);

            return ksort($array, $flags);
        }

        recursive_ksort($result);

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
