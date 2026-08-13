<?php

declare(strict_types=1);

use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Support\Presentation;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures\Scenario;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Widgets\RatingCoverageWidget;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Livewire\Livewire;

beforeEach(function (): void {
    Scenario::screener();
});

/**
 * "4.3 stars" is not a fact. The widget's whole job is showing what moderation
 * has done to the published figure, which means showing two aggregates that
 * name their populations apart.
 */
it('shows the displayed and the recorded populations apart', function (): void {
    $approved = Scenario::review(author: 'shopper-1', score: 4);
    Scenario::decide($approved);

    $withheld = Scenario::review(author: 'shopper-2', score: 1);
    Scenario::decide($withheld, ModerationOutcome::Withheld, ModerationReason::Spam);

    Scenario::review(author: 'shopper-3', score: 5);

    Livewire::test(RatingCoverageWidget::class, ['record' => record($approved)])
        ->assertSuccessful()
        ->assertSee('Displayed')
        ->assertSee('Recorded')
        ->assertSee('Population: displayed')
        ->assertSee('Population: recorded')
        // 4 over 1 displayed, 10 over 3 recorded — the numerator and the
        // denominator both, so the figure can be re-derived.
        ->assertSee('4 of 5 — 4 over 1')
        ->assertSee('3.33 of 5 — 10 over 3')
        ->assertSee('2 of 3');
});

it('says so plainly when nothing has been approved yet', function (): void {
    $reference = Scenario::review();

    Livewire::test(RatingCoverageWidget::class, ['record' => record($reference)])
        ->assertSee('no ratings');
});

it('has no product to aggregate on a merchant reply', function (): void {
    $reference = Scenario::review();

    Livewire::test(RatingCoverageWidget::class, ['record' => record($reference)->setAttribute('product_reference', null)])
        ->assertSee('not applicable');
});

it('never publishes a bare rounded average', function (): void {
    expect(Presentation::aggregate(374, 87, 5))->toBe('4.3 of 5 — 374 over 87')
        ->and(Presentation::aggregate(0, 0, 5))->toBe('no ratings');
});

function record(string $reference): Expression
{
    return Expression::query()->where('reference', $reference)->firstOrFail();
}
