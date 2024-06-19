# translation-bundle
**Overriding default api resource (and therefore api endpoints)**

By default, Translation resource is based on `TranslationResource`  
If you wish not to use this resource and not expose the api endpoints it provides, just set a custom api resource path
with a configuration value. If you set it as `null`, api platform will not register api resource located within this
package.

```yaml
translation:
    custom_api_resource_path: '%kernel.project_dir%/src/MyCustomPath'
#    custom_api_resource_path: null
```

```php
use Symfony\Config\TranslationConfig;
return static function (TranslationConfig $config): void {
    $config->customApiResourcePath('%kernel.project_dir%/src/MyCustomPath')
    // or  ->customApiResourcePath(null);
};
```
After overriding default api resource, do not forget to update ClassMapperConfigurator configuration that is used for
resource <-> entity mapping in `whitedigital-eu/entity-resource-mapper-bundle`

```php
use App\ApiResource\Admin\TranslationResource;
use WhiteDigital\Translation\Entity\Translation;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapper;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapperConfiguratorInterface;
final class ClassMapperConfigurator implements ClassMapperConfiguratorInterface
{
    public function __invoke(ClassMapper $classMapper): void
    {
        $classMapper->registerMapping(TranslationResource::class, Translation::class);
    }
}
```
---
