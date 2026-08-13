<?php

declare(strict_types=1);

use Filament\Tables\Columns\Column;
use Illuminate\Support\Collection;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ScreeningOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningPriority;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\ExpressionResource;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\Pages\ListExpressions;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures\Scenario;
use Livewire\Livewire;

beforeEach(function (): void {
    Scenario::screener();
});

it('renders the queue', function (): void {
    Scenario::review();

    Livewire::test(ListExpressions::class)
        ->assertSuccessful()
        ->assertSee('Moderation queue');
});

it('defaults to what nobody has decided about yet', function (): void {
    $pending = Scenario::review(author: 'shopper-1');
    $approved = Scenario::review(author: 'shopper-2', product: 'sku-other');
    Scenario::decide($approved);

    $keys = array_keys(rows());

    expect($keys)->toContain($pending)
        ->and($keys)->not->toContain($approved);
});

it('filters by display state rather than sorting by it', function (): void {
    $approved = Scenario::review(author: 'shopper-2');
    Scenario::decide($approved);

    expect(array_keys(rows(DisplayState::Approved->value)))->toBe([$approved]);
});

it('shows every state when the filter is cleared', function (): void {
    $pending = Scenario::review(author: 'shopper-1');
    $approved = Scenario::review(author: 'shopper-2', product: 'sku-other');
    Scenario::decide($approved);

    $keys = array_keys(rows(''));

    expect($keys)->toContain($pending)->toContain($approved);
});

it('flags an expression edited after it was approved', function (): void {
    $original = Scenario::review();
    Scenario::decide($original);
    $edit = Scenario::revise($original);

    $rows = rows();

    expect($rows[$edit]['edited_after_approval'])->toBeTrue();
});

it('does not flag an edit of something nobody had approved', function (): void {
    $original = Scenario::review();
    $edit = Scenario::revise($original);

    expect(rows()[$edit]['edited_after_approval'])->toBeFalse();
});

it('puts what screening found most urgent at the top', function (): void {
    Scenario::screener();
    $routine = Scenario::review(author: 'shopper-1');

    Scenario::screener(ScreeningOutcome::escalate([ModerationReason::Profanity], ScreeningPriority::Urgent));
    $urgent = Scenario::review(author: 'shopper-2', product: 'sku-other');

    // Screening escalation appends its own decision, so the urgent row is in
    // the escalated queue, not the pending one — a machine queues, it does not
    // decide, and the queue position is still recorded as a decision.
    expect(array_keys(rows(DisplayState::Escalated->value)))->toBe([$urgent])
        ->and(array_keys(rows()))->toBe([$routine]);
});

it('carries the screening signals a moderator has to see', function (): void {
    Scenario::screener(ScreeningOutcome::escalate([ModerationReason::PersonalInformation]));
    $reference = Scenario::review();

    expect(rows(DisplayState::Escalated->value)[$reference]['screening_signals'])
        ->toBe('personal information');
});

it('counts the reader reports on a row', function (): void {
    $reference = Scenario::review();
    Scenario::flag($reference, 'shopper-9');
    Scenario::flag($reference, 'shopper-8');

    expect(rows()[$reference]['flag_count'])->toBe(2);
});

it('offers exactly the display states the domain publishes', function (): void {
    expect(array_keys(ExpressionResource::stateOptions()))
        ->toBe(array_map(fn (DisplayState $state): string => $state->value, DisplayState::cases()));
});

it('reads a decision taken elsewhere rather than a flag of its own', function (): void {
    $reference = Scenario::review();
    Scenario::decide($reference, ModerationOutcome::Withheld, ModerationReason::UndisclosedIncentive);

    expect(rows(DisplayState::Withheld->value)[$reference]['display_state'])
        ->toBe(DisplayState::Withheld);
});

/**
 * @return array<string, array<string, mixed>>
 */
function rows(?string $state = null): array
{
    $filters = $state === null ? [] : ['state' => ['value' => $state]];

    $component = Livewire::test(ListExpressions::class, $state === null ? [] : ['tableFilters' => $filters]);

    if ($state !== null) {
        $component->set('tableFilters.state.value', $state);
    }

    /** @var Collection<string, array<string, mixed>> $records */
    $records = $component->instance()->getTableRecords();

    return $records->all();
}

it('makes no column searchable', function (): void {
    $columns = Livewire::test(ListExpressions::class)->instance()->getTable()->getColumns();

    expect($columns)->not->toBeEmpty();

    foreach ($columns as $column) {
        expect($column)->toBeInstanceOf(Column::class)
            ->and($column->isSearchable())->toBeFalse();
    }
});
