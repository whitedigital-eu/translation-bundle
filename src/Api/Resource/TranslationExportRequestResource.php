<?php declare(strict_types=1);

namespace WhiteDigital\Translation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use ArrayObject;
use WhiteDigital\Translation\DataProcessor\TranslationExportRequestDataProcessor;

#[
    ApiResource(
        shortName: 'TranslationExportRequest',
        operations: [
            new Post(
                openapi: new Operation(
                    responses: [
                        '201' => new Response(
                            description: 'Exported translations xlsx file',
                            content: new ArrayObject([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [
                                    'schema' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                ],
                            ]),
                        ),
                    ],
                )),
        ],
        processor: TranslationExportRequestDataProcessor::class,
    ),
]
class TranslationExportRequestResource
{
}
