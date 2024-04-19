<?php

namespace WhiteDigital\Translation\Enum;

enum TranslationImportMode: string
{
    case OVERWRITE_EXISTING = 'OVERWRITE_EXISTING';
    case SKIP_EXISTING = 'SKIP_EXISTING';

    // Required for openapi schema generation
    const VALUES = [self::OVERWRITE_EXISTING->value, self::SKIP_EXISTING->value];
}
