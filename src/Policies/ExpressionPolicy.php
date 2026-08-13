<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies;

use Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies\Concerns\RefusesUnpublishedAbilities;

/**
 * Reading an expression is the only ability this package publishes on it.
 *
 * Approving, rejecting, withholding and escalating are not `update` — they
 * append a moderation decision through the domain action and leave the
 * expression exactly as it was said. Editing a review in a panel would be a
 * merchant rewriting a shopper's words, which is the one thing a registry of
 * speech must never do.
 */
final class ExpressionPolicy
{
    use RefusesUnpublishedAbilities;

    public function viewAny(): bool
    {
        return true;
    }

    public function view(): bool
    {
        return true;
    }
}
