<?php

namespace App\Enums;

/**
 * Changelog entry categories, shared by config/changelog.php (admin form)
 * and the file writer/validator.
 */
enum ChangelogCategory: string
{
    case Added = 'Added';
    case Fixed = 'Fixed';
    case Changed = 'Changed';
    case Removed = 'Removed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $category) => $category->value, self::cases());
    }
}
