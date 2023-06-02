<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Serializer\Annotation\Groups;
use WhiteDigital\EntityResourceMapper\Attribute\Mapping;
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
]
#[Mapping(Translation::class)]
class TranslationResource extends BaseResource
{
    public const PREFIX = 'translation:';

    public const READ = self::PREFIX . 'read';
    public const WRITE = self::PREFIX . 'write';

    #[ApiProperty(identifier: true)]
    #[Groups([self::READ, ])]
    public mixed $id = null;

    #[Groups([self::READ, ])]
    public ?UTCDateTimeImmutable $createdAt = null;

    #[Groups([self::READ, ])]
    public ?UTCDateTimeImmutable $updatedAt = null;

    #[Groups([self::READ, self::WRITE, ])]
    public ?string $domain = null;

    #[Groups([self::READ, self::WRITE, ])]
    public ?string $locale = null;

    #[Groups([self::READ, self::WRITE, ])]
    public ?string $key = null;

    #[Groups([self::READ, self::WRITE, ])]
    public ?string $translation = null;
}
