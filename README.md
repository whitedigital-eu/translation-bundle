# Translation Bundle

A Symfony bundle that provides translation management capabilities through API Platform, with support for multiple locales and domains.

## Features

- API Platform integration for translation management
- Support for multiple locales and domains
- Database-backed translation storage
- Cache support for improved performance
- Command-line tools for translation management
- Integration with Lexik Translation Bundle
- Support for translatable entities

## Requirements

- PHP 8.2 or higher
- Symfony 6.x
- API Platform 3.x
- Doctrine ORM

## Installation

1. Install the bundle using Composer:

```bash
composer require whitedigital-eu/translation-bundle
```

2. Add the bundle to your `config/bundles.php`:

```php
return [
    // ...
    WhiteDigital\Translation\TranslationBundle::class => ['all' => true],
];
```

## Configuration

Create a configuration file `config/packages/translation.yaml`:

```yaml
translation:
    entity_manager: default  # Optional: defaults to 'default'
    locale: en              # Default locale for translations
    translation_fallback: false  # Whether to use translation fallback
    managed_locales: ['en', 'lv']  # List of managed locales
    cache_pool: cache.app    # Optional: Cache pool to use for translations
```

## Usage

### 1. Translatable Entities

To make an entity translatable, extend the `AbstractTranslatableEntity` class:

```php
use WhiteDigital\Translation\Entity\AbstractTranslatableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

class YourEntity extends AbstractTranslatableEntity
{
    #[Gedmo\Translatable]
    private ?string $name = null;
    
    // ... getters and setters
}
```

### 2. API Endpoints

The bundle provides the following API endpoints:

- `GET /api/trans_units` - List all translation units
- `GET /api/trans_units/{id}` - Get a specific translation unit
- `POST /api/trans_units` - Create a new translation unit
- `PATCH /api/trans_units/{id}` - Update a translation unit
- `DELETE /api/trans_units/{id}` - Delete a translation unit
- `GET /api/trans_units/list/{locale}` - Get translations for a specific locale

### 3. Command Line Tools

The bundle provides several command-line tools. There are two ways to use these commands depending on your setup:

#### When using SiteTree:
Each locale is passed as a separate option with its corresponding file path:
```bash
# Import translations
bin/console wd:trans_unit:import --en=/path/to/en.json --lv=/path/to/lv.json

# Override translations
bin/console wd:trans_unit:override --en=/path/to/en.json --lv=/path/to/lv.json
```

#### Without SiteTree:
Locales and files are passed as comma-separated lists:
```bash
# Import translations
bin/console wd:trans_unit:import --locales=en,lv --files=/path/to/en.json,/path/to/lv.json

# Override translations
bin/console wd:trans_unit:override --locales=en,lv
```

### 4. Translation Format

When creating or updating translations via API, use the following format:

```json
{
    "key": "translation.key",
    "domain": "messages",
    "translations": {
        "en": "English translation",
        "lv": "Latvian translation"
    }
}
```

### 5. Cache Management

The bundle supports caching of translations for improved performance. Configure the cache pool in your configuration:

```yaml
translation:
    cache_pool: cache.app
```

The cache is automatically invalidated when translations are updated.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This bundle is released under the MIT license. See the included [LICENSE](LICENSE) file for more information.
