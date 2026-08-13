<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies;

use Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies\Concerns\RefusesUnpublishedAbilities;

/**
 * A reader's report is read and acted on, never edited or dismissed in place.
 *
 * Clearing a flag would be a moderation decision without a moderation record.
 * The queue exists to route a report to a person; what that person decides is
 * appended to the expression, where it names them.
 */
final class FlagPolicy
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
