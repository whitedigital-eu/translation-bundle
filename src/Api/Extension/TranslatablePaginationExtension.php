<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Api\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Translatable\Query\TreeWalker\TranslationWalker;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator('api_platform.doctrine.orm.query_extension.pagination')]
readonly class TranslatablePaginationExtension implements QueryResultCollectionExtensionInterface
{
    public function __construct(private QueryResultCollectionExtensionInterface $originalPaginationExtension)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->originalPaginationExtension->applyToCollection($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
    }

    public function supportsResult(string $resourceClass, ?Operation $operation = null, array $context = []): bool
    {
        return $this->originalPaginationExtension->supportsResult($resourceClass, $operation, $context);
    }

    public function getResult(QueryBuilder $queryBuilder, ?string $resourceClass = null, ?Operation $operation = null, array $context = []): iterable
    {
        /** @var Paginator $paginator */
        $paginator = $this->originalPaginationExtension->getResult($queryBuilder, $resourceClass, $operation, $context);
        $paginator->getQuery()->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TranslationWalker::class);

        return $paginator;
    }
}
