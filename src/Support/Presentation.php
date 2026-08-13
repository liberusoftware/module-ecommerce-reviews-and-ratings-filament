<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Support;

use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningPriority;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;
use Liberu\Ecommerce\ReviewsAndRatings\Support\SubmissionRules;

/**
 * How the module's closed enums look, in one place.
 *
 * The domain's enums carry no labels or colours on purpose — a domain that
 * knows what colour `escalated` is has a panel inside it. The mapping lives
 * here, and the one that matters is verification.
 */
final class Presentation
{
    /**
     * Tri-state, and it stays tri-state all the way to the badge.
     *
     * `Unknown` renders **nothing at all**: the absence of a badge is itself a
     * claim, and "not a verified purchase" said when the module simply could not
     * ask is a lie of omission. `Unverified` is the only state that renders a
     * negative. Flattening `Unknown` into it is correct in the domain and lost
     * in a view model, which is exactly where it gets lost.
     */
    public static function verificationLabel(VerificationState $state): ?string
    {
        return match ($state) {
            VerificationState::Verified => 'Verified purchase',
            VerificationState::Unverified => 'Not a verified purchase',
            VerificationState::Unknown => null,
        };
    }

    public static function verificationColor(VerificationState $state): ?string
    {
        return match ($state) {
            VerificationState::Verified => 'success',
            VerificationState::Unverified => 'warning',
            VerificationState::Unknown => null,
        };
    }

    public static function displayStateColor(DisplayState $state): string
    {
        return match ($state) {
            DisplayState::Approved => 'success',
            DisplayState::Pending => 'gray',
            DisplayState::Rejected => 'danger',
            DisplayState::Withheld => 'warning',
            DisplayState::Escalated => 'danger',
        };
    }

    public static function screeningPriorityColor(ScreeningPriority $priority): string
    {
        return match ($priority) {
            ScreeningPriority::Urgent => 'danger',
            ScreeningPriority::Elevated => 'warning',
            ScreeningPriority::Routine => 'gray',
        };
    }

    /** `four of five`, or nothing at all — a score without its scale is not a fact. */
    public static function score(?int $score, ?int $scale): ?string
    {
        return $score === null || $scale === null ? null : $score.' / '.$scale;
    }

    /**
     * An aggregate always names the population it summarises.
     *
     * The mean is rounded here and only here, because a panel is a consumer.
     * The numerator and the denominator are shown beside it so the figure can
     * be re-derived, which is what a rounded average cannot support.
     */
    public static function aggregate(int $sum, int $count, int $scale): string
    {
        if ($count === 0) {
            return 'no ratings';
        }

        return round($sum / $count, 2).' of '.$scale.' — '.$sum.' over '.$count;
    }

    /**
     * The limit the whole module reads, so a form cannot invent a fourth.
     *
     * The host has three disagreeing answers for a review body: 1000 in a form
     * request, 65535 in a textarea and a `text` column. The panel reads the
     * domain constant instead.
     */
    public static function maxBodyLength(): int
    {
        return SubmissionRules::MAX_BODY_LENGTH;
    }
}
