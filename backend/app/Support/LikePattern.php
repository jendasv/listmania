<?php

declare(strict_types=1);

namespace App\Support;

final class LikePattern
{
    /**
     * "Contains" pattern for a LIKE / ILIKE query. The term is bound as a
     * parameter (no SQL injection), but % and _ inside it would still act as
     * wildcards — searching "50%" or "a_b" must match those literal characters.
     * Backslash is the default LIKE escape character in PostgreSQL and MySQL.
     */
    public static function contains(string $term): string
    {
        return '%'.addcslashes($term, '\\%_').'%';
    }
}
