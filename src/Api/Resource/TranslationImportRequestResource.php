<?php declare(strict_types=1);

namespace WhiteDigital\Translation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ArrayObject;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation\Uploadable;
use Vich\UploaderBundle\Mapping\Annotation\UploadableField;
use WhiteDigital\Translation\DataProcessor\TranslationImportRequestDataProcessor;
use WhiteDigital\Translation\Enum\TranslationImportMode;

#[Uploadable]
#[
    ApiResource(
        shortName: 'TranslationImportRequest',
        operations: [
            new Post(
                inputFormats: ['multipart' => ['multipart/form-data']],
                openapi: new Operation(
                    requestBody: new RequestBody(
                        content: new ArrayObject(
                            [
                                'multipart/form-data' => [
                                    'schema' => [
                                        'type'       => 'object',
                                        'properties' => [
                                            'importMode' => [
                                                'type' => 'string',
                                                'enum' => [
                                                    TranslationImportMode::VALUES,
                                                    TranslationImportMode::SKIP_EXISTING->value,
                                                ],
                                            ],
                                            'file' => [
                                                'type'   => 'string',
                                                'format' => 'binary',
                                            ],
                                        ],
                                    ],
                                ],
                            ]
                        )
                    ),
                )
            ),
        ],
        processor: TranslationImportRequestDataProcessor::class,
    ),
]
class TranslationImportRequestResource
{
    #[Assert\NotBlank]
    public ?TranslationImportMode $importMode = null;

    #[UploadableField(mapping: 'translations_import_files')]
    #[Assert\NotBlank]
    #[Assert\File(
        mimeTypes: ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        mimeTypesMessage: 'Please upload a valid XLSX file',
    )]
    public ?File $file = null;
}
