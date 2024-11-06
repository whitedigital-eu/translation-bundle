<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Api\Extension;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Exception\PropertyNotFoundException;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Gedmo\Mapping\Annotation\Translatable;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Throwable;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapper;

/**
 * This class decorates the property metadata factory to add the 'translatable' format/tag to the OpenAPI schema
 * if the corresponding entity property is translatable (has Gedmo\Translatable attribute). The result of this decorator
 * will be cached by the metadata cache (Once per ApiResource class).
 */
#[AsDecorator(decorates: 'api_platform.metadata.property.metadata_factory')]
readonly class TranslatablePropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(
        #[AutowireDecorated] private PropertyMetadataFactoryInterface $decorated,
        private ClassMapper $classMapper,
    ) {
    }

    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        try {
            $apiProperty = $this->decorated->create($resourceClass, $property, $options);
        } catch (PropertyNotFoundException) {
            $apiProperty = new ApiProperty();
        }

        try {
            $entityReflection = new ReflectionClass($this->classMapper->byResource($resourceClass));

            if ($entityReflection->hasProperty($property) && [] !== $entityReflection->getProperty($property)->getAttributes(Translatable::class)) {
                return $apiProperty->withOpenapiContext(['format' => 'translatable']);
            }

            return $apiProperty;
        } catch (Throwable) {
            return $apiProperty;
        }
    }
}
