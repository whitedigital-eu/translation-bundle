<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation\UploadableField;
use WhiteDigital\Translation\DataProcessor\TranslationImportRequestDataProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[
    ApiResource(
        shortName: 'TranslationImportRequest',
        operations: [new Post(inputFormats: ['multipart' => ['multipart/form-data']])],
        processor: TranslationImportRequestDataProcessor::class,
    ),
]
class TranslationImportRequestResource
{
    #[Assert\NotBlank]
    public ?bool $overwriteExisting = null;

    #[UploadableField(mapping: 'def')]
    public ?File $file = null;
}
