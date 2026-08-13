<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies\Concerns;

/**
 * Every ability this package does not publish, refused by name.
 *
 * A model with no policy is exposed, not safe: Laravel's unanswered gate case
 * is permissive, and Filament's `get_authorization_response()` returns **allow**
 * when a policy exists but lacks the method being asked about. So a partial
 * policy is the same hazard as no policy, and harder to see.
 *
 * `associate`, `dissociate`, `attach` and `detach` are here because they are
 * live on a `hasMany` relation manager and default open. They are named
 * deliberately — this trait means to answer them, and the answer is no.
 *
 * Nothing in this module is created, edited or deleted through Eloquent. An
 * expression is append-only and a moderation decision is a record, so every
 * write goes through a domain action which appends a row. There is no ability
 * here that could be published later without that stopping being true.
 */
trait RefusesUnpublishedAbilities
{
    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    public function deleteAny(): bool
    {
        return false;
    }

    public function forceDelete(): bool
    {
        return false;
    }

    public function forceDeleteAny(): bool
    {
        return false;
    }

    public function restore(): bool
    {
        return false;
    }

    public function restoreAny(): bool
    {
        return false;
    }

    public function replicate(): bool
    {
        return false;
    }

    public function reorder(): bool
    {
        return false;
    }

    public function attach(): bool
    {
        return false;
    }

    public function attachAny(): bool
    {
        return false;
    }

    public function detach(): bool
    {
        return false;
    }

    public function detachAny(): bool
    {
        return false;
    }

    public function associate(): bool
    {
        return false;
    }

    public function associateAny(): bool
    {
        return false;
    }

    public function dissociate(): bool
    {
        return false;
    }

    public function dissociateAny(): bool
    {
        return false;
    }
}
