<?php

declare(strict_types=1);

use Liberu\Ecommerce\ReviewsAndRatings\Actions\EraseAuthor;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\Pages\ViewExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\RelationManagers\ModerationHistoryRelationManager;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures\Scenario;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Livewire\Livewire;

beforeEach(function (): void {
    Scenario::screener();
});

it('opens one expression by its reference', function (): void {
    $reference = Scenario::review();

    Livewire::test(ViewExpression::class, ['record' => $reference])
        ->assertSuccessful()
        ->assertSee($reference)
        ->assertSee('The lens is sharp and the strap is not.');
});

it('reads the projection through the domain query rather than deriving it', function (): void {
    $reference = Scenario::review();
    Scenario::decide($reference, ModerationOutcome::Withheld, ModerationReason::OffTopic, 'staff-3');

    $review = Livewire::test(ViewExpression::class, ['record' => $reference])->instance()->review();

    expect($review->reference)->toBe($reference)
        ->and($review->displayState->value)->toBe('withheld')
        ->and($review->moderationHistory)->toHaveCount(1)
        ->and($review->moderationHistory[0]->actorReference)->toBe('staff-3');
});

it('shows the whole moderation history, not the latest of it', function (): void {
    $reference = Scenario::review();
    Scenario::decide($reference, ModerationOutcome::Approved, ModerationReason::Compliant, 'staff-1');
    Scenario::decide($reference, ModerationOutcome::Withheld, ModerationReason::Counterfeit, 'staff-2');
    Scenario::decide($reference, ModerationOutcome::Approved, ModerationReason::Compliant, 'staff-3');

    $rows = Livewire::test(ModerationHistoryRelationManager::class, [
        'ownerRecord' => Expression::query()->where('reference', $reference)->firstOrFail(),
        'pageClass' => ViewExpression::class,
    ])->instance()->getTableRecords();

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('actor_reference')->all())->toBe(['staff-1', 'staff-2', 'staff-3'])
        ->and($rows->pluck('outcome')->all())->toBe(['Approved', 'Withheld', 'Approved']);
});

it('tells a moderator that an approved expression was edited afterwards', function (): void {
    $original = Scenario::review();
    Scenario::decide($original);
    $edit = Scenario::revise($original);

    $review = Livewire::test(ViewExpression::class, ['record' => $edit])->instance()->review();

    expect($review->editedAfterApproval)->toBeTrue()
        ->and($review->supersedesReference)->toBe($original);
});

/**
 * The tri-state has to survive the whole way to the badge. Three separate
 * assertions, because flattening `unknown` into `unverified` is correct in the
 * domain and lost in a view model — and a view model is exactly what this is.
 */
it('renders a badge for a verified purchase', function (): void {
    Scenario::verifier(true);
    $reference = Scenario::review();

    Livewire::test(ViewExpression::class, ['record' => $reference])
        ->assertSuccessful()
        ->assertSee('Verified purchase');
});

it('renders a negative only when the module actually checked', function (): void {
    Scenario::verifier(false);
    $reference = Scenario::review();

    $page = Livewire::test(ViewExpression::class, ['record' => $reference]);

    expect($page->instance()->review()->verification)->toBe(VerificationState::Unverified);

    $page->assertSee('Not a verified purchase');
});

it('renders nothing at all when it could not ask', function (): void {
    $reference = Scenario::review();

    $page = Livewire::test(ViewExpression::class, ['record' => $reference]);

    expect($page->instance()->review()->verification)->toBe(VerificationState::Unknown);

    $page->assertDontSee('Not a verified purchase')
        ->assertDontSee('Verified purchase');
});

it('keeps an erased expression readable as a shape with no content', function (): void {
    $reference = Scenario::review();
    Scenario::decide($reference);
    app(EraseAuthor::class)(Scenario::TENANT, 'shopper-1');

    $review = Livewire::test(ViewExpression::class, ['record' => $reference])
        ->assertSuccessful()
        ->instance()
        ->review();

    expect($review->isRedacted)->toBeTrue()
        ->and($review->body)->toBeNull()
        ->and($review->authorReference)->toBeNull()
        ->and($review->authorDisplayName)->toBeNull()
        ->and($review->score)->toBe(4)
        ->and($review->moderationHistory)->toHaveCount(1);
});
