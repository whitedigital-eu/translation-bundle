<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\ApiResource;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\GroupFilter;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use WhiteDigital\EntityResourceMapper\Attribute\Mapping;
use WhiteDigital\EntityResourceMapper\Filters\ResourceBooleanFilter;
use WhiteDigital\EntityResourceMapper\Filters\ResourceOrderFilter;
use WhiteDigital\EntityResourceMapper\Filters\ResourceSearchFilter;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\UTCDateTimeImmutable;
use WhiteDigital\Translation\DataProcessor\TranslationDataProcessor;
use WhiteDigital\Translation\DataProvider\TranslationDataProvider;
use WhiteDigital\Translation\Entity\Translation;

#[
    ApiResource(
        shortName: 'Translation',
        operations: [
            new Get(
                requirements: ['id' => '\d+', ],
                normalizationContext: ['groups' => [self::READ, ], ],
            ),
            new Get(
                uriTemplate: '/translations/list/{locale}',
                uriVariables: [
                    'locale' => new Link(fromClass: self::class, identifiers: ['locale']),
                ],
                normalizationContext: ['groups' => [self::LIST, ], ],
                write: false,
            ),
            new GetCollection(
                normalizationContext: ['groups' => [self::READ, ], ],
            ),
            new Patch(
                requirements: ['id' => '\d+', ],
                denormalizationContext: ['groups' => [self::WRITE, ], ],
            ),
            new Post(
                denormalizationContext: ['groups' => [self::WRITE, ], ],
            ),
        ],
        normalizationContext: ['groups' => [self::READ, ], ],
        denormalizationContext: ['groups' => [self::WRITE, ], ],
        order: ['id' => 'ASC', ],
        provider: TranslationDataProvider::class,
        processor: TranslationDataProcessor::class,
    ),
    ApiFilter(GroupFilter::class, arguments: ['parameterName' => 'groups', 'overrideDefaultGroups' => false, ]),
    ApiFilter(ResourceBooleanFilter::class, properties: ['isActive', ]),
    ApiFilter(ResourceOrderFilter::class, properties: ['domain', 'locale', 'key', 'translation', ]),
    ApiFilter(ResourceSearchFilter::class, properties: ['domain', 'locale', 'key', 'translation', ]),
]
#[Mapping(Translation::class)]
class TranslationResource extends BaseResource
{
    public const PREFIX = 'translation:';

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
    #[Assert\Type(type: 'alnum', message: 'Only alphanumeric characters allowed')]
    #[Assert\NotBlank]
    public ?string $domain = null;

    #[Groups([self::READ, self::WRITE, ])]
    #[Assert\Type(type: 'alpha', message: 'Only letters allowed')]
    #[Assert\NotBlank]
    public ?string $locale = null;

    #[Groups([self::READ, self::WRITE, ])]
    #[Assert\Regex(pattern: '/^(?i)[a-z0-9][a-z0-9 .]*(?<=[a-z0-9])$/i')]
    #[Assert\NotBlank]
    public ?string $key = null;

    #[Groups([self::READ, self::WRITE, ])]
    #[Assert\NotBlank]
    public ?string $translation = null;

    #[Groups([self::READ, self::WRITE, ])]
    public bool $isActive = true;

    #[Groups([self::LIST, ])]
    public array $translations = [];
}
