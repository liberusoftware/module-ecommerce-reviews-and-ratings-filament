<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies;

use Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies\Concerns\RefusesUnpublishedAbilities;

/**
 * The moderation history is a relation manager, so its model is authorized too.
 *
 * A relation manager asks the *related* model's policy for `viewAny`, not the
 * owner's. Without this class the audit trail would be a model with no policy
 * on a panel — which is the permissive case, not the safe one.
 */
final class ModerationDecisionPolicy
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
