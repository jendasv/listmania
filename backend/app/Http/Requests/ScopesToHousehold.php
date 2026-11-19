<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Exists;

/**
 * A plain exists:table,id rule accepts ids from every household, which lets a
 * user attach (and then read back) another household's custom records. These
 * rules only accept records that are global or belong to the current household.
 */
trait ScopesToHousehold
{
    protected function currentHouseholdId(): ?int
    {
        return $this->user()?->household()?->id;
    }

    protected function categoryVisibleToHousehold(): Exists
    {
        $householdId = $this->currentHouseholdId();

        return (new Exists('categories', 'id'))->where(
            fn (Builder $q) => $q->where('is_global', true)->orWhere('household_id', $householdId)
        );
    }

    protected function globalProductVisibleToHousehold(): Exists
    {
        $householdId = $this->currentHouseholdId();

        return (new Exists('global_products', 'id'))->where(
            fn (Builder $q) => $q->whereNull('household_id')->orWhere('household_id', $householdId)
        );
    }
}
