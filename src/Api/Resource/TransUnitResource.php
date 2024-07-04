<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Api\Resource;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\GroupFilter;
use Doctrine\Common\Collections\Order;
use Lexik\Bundle\TranslationBundle\Entity\TransUnit;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use WhiteDigital\EntityResourceMapper\Attribute\Mapping;
use WhiteDigital\EntityResourceMapper\Filters\ResourceBooleanFilter;
use WhiteDigital\EntityResourceMapper\Filters\ResourceOrderFilter;
use WhiteDigital\EntityResourceMapper\Filters\ResourceSearchFilter;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\UTCDateTimeImmutable;
use WhiteDigital\Translation\DataProcessor\TransUnitDataProcessor;
use WhiteDigital\Translation\DataProvider\TransUnitDataProvider;

#[
    ApiResource(
        shortName: 'TransUnit',
        operations: [
            new Delete(
                uriTemplate: '/trans_units/{id}',
            ),
            new Get(
                uriTemplate: '/trans_units/{id}',
            ),
            new GetCollection(
                uriTemplate: '/trans_units',
            ),
            new Patch(
                uriTemplate: '/trans_units/{id}',
            ),
            new Post(
                uriTemplate: '/trans_units',
            ),
            new GetCollection(
                uriTemplate: '/trans_units/list/{locale}',
                uriVariables: [
                    'locale',
                ],
                normalizationContext: ['groups' => [self::LIST, ], ],
                write: false,
                name: 'trans_unit_list_locale',
            ),
        ],
        normalizationContext: ['groups' => [self::READ, ], ],
        denormalizationContext: ['groups' => [self::WRITE, ], ],
        order: [
            'domain' => Order::Ascending->value,
            'key' => Order::Ascending->value,
        ],
        provider: TransUnitDataProvider::class,
        processor: TransUnitDataProcessor::class,
    ),
    ApiFilter(GroupFilter::class, arguments: ['parameterName' => 'groups', 'overrideDefaultGroups' => false, ]),
    ApiFilter(ResourceOrderFilter::class, properties: ['id', 'domain', 'translations.locale', 'key', 'createdAt', 'updatedAt', 'isDeleted', 'translations.content']),
    ApiFilter(ResourceBooleanFilter::class, properties: ['isDeleted', ]),
    ApiFilter(ResourceSearchFilter::class, properties: ['translations.locale', 'key', 'translations.content', 'domain'])
]
#[Mapping(mappedClass: TransUnit::class)]
class TransUnitResource extends BaseResource
{
    public const PREFIX = 'trans_unit:';

    public const READ = self::PREFIX . 'read';
    public const WRITE = self::PREFIX . 'write';
    public const LIST = self::PREFIX . 'list';

    #[ApiProperty(identifier: true)]
    #[Groups([self::READ, ])]
    public mixed $id = null;

    #[Groups([self::READ, ])]
    public ?UTCDateTimeImmutable $createdAt = null;

    #[Groups([self::READ, ])]
    public ?UTCDateTimeImmutable $updatedAt = null;

    #[Groups([self::READ, self::WRITE, ])]
    #[Assert\Regex(pattern: '/^(?i)[a-z0-9][a-z0-9 ._]*(?<=[a-z0-9])$/i')]
    public ?string $key = null;

    #[Groups([self::READ, self::WRITE, ])]
    #[Assert\Type(type: 'alnum', message: 'Only alphanumeric characters allowed')]
    #[Assert\NotBlank]
    public ?string $domain = null;

    /** @var array<string, string> */
    #[Groups([self::READ, self::WRITE, self::LIST, ])]
    #[ApiProperty(example: ['en' => 'translation', 'lv' => 'tulkojums'])]
    public array $translations = [];

    #[Groups([self::READ, ])]
    public ?bool $isDeleted = null;
}
