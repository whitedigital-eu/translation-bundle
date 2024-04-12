<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use WhiteDigital\Translation\DataProcessor\TranslationExportRequestDataProcessor;

#[
    ApiResource(
        shortName: 'TranslationExportRequest',
        operations: [new Post()],
        processor: TranslationExportRequestDataProcessor::class,
    ),
]
class TranslationExportRequestResource
{
}
