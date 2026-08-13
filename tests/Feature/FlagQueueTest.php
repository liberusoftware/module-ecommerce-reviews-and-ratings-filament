<?php

declare(strict_types=1);

use Liberu\Ecommerce\ReviewsAndRatings\Enums\FlagReason;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Flags\FlagResource;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Flags\Pages\ListFlags;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures\Scenario;
use Livewire\Livewire;

beforeEach(function (): void {
    Scenario::screener();
});

it('lists what readers have reported', function (): void {
    $reference = Scenario::review();
    Scenario::flag($reference, 'shopper-9', FlagReason::Counterfeit);

    $rows = Livewire::test(ListFlags::class)->assertSuccessful()->instance()->getTableRecords();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['reason'])->toBe('Counterfeit')
        ->and($rows->first()['expression_reference'])->toBe($reference);
});

/**
 * A reporter reference is an author reference, which is the sensitive thing in
 * this module. There is no column for it, and the row the table is built from
 * does not carry it — so nobody can make it searchable later either.
 */
it('never carries who reported what', function (): void {
    $reference = Scenario::review();
    Scenario::flag($reference, 'shopper-9');

    $row = FlagResource::rows()[0];

    expect(array_keys($row))->toBe(['__key', 'expression_reference', 'reason', 'occurred_at'])
        ->and(json_encode($row))->not->toContain('shopper-9');

    Livewire::test(ListFlags::class)->assertDontSee('shopper-9');
});

it('makes nothing on the report queue searchable', function (): void {
    $columns = Livewire::test(ListFlags::class)->instance()->getTable()->getColumns();

    expect($columns)->not->toBeEmpty();

    foreach ($columns as $column) {
        expect($column->isSearchable())->toBeFalse();
    }
});

it('offers no filter at all, because the only thing to filter on is a person', function (): void {
    expect(Livewire::test(ListFlags::class)->instance()->getTable()->getFilters())->toBe([]);
});

it('links a report to the expression it is about, by reference', function (): void {
    $reference = Scenario::review();
    Scenario::flag($reference);

    $url = Livewire::test(ListFlags::class)->instance()->getTable()->getRecordUrl(FlagResource::rows()[0]);

    expect($url)->toContain($reference);
});
