<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\ExpressionResource;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\Pages\ListExpressions;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Flags\Pages\ListFlags;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures\Scenario;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Livewire\Livewire;

beforeEach(function (): void {
    Scenario::screener();
});

/**
 * Search terms and filter state both persist into the query string, and author
 * references and display names are the sensitive things in this module. So
 * neither is in a row, in a column, or in a filter — absent rather than hidden.
 */
it('carries no author reference or display name in a queue row', function (): void {
    Scenario::review(author: 'shopper-secret', displayName: 'Ada Lovelace');

    $rows = Livewire::test(ListExpressions::class)->instance()->getTableRecords();
    $row = $rows->first();

    expect(array_keys($row))->toBe([
        '__key', 'reference', 'kind', 'product_reference', 'score', 'scale', 'body',
        'display_state', 'screening_priority', 'screening_signals',
        'edited_after_approval', 'flag_count', 'verification', 'occurred_at',
    ])
        ->and(json_encode($row))->not->toContain('shopper-secret')
        ->and(json_encode($row))->not->toContain('Ada Lovelace');
});

it('does not render an author on the queue page', function (): void {
    Scenario::review(author: 'shopper-secret', displayName: 'Ada Lovelace');

    Livewire::test(ListExpressions::class)
        ->assertDontSee('shopper-secret')
        ->assertDontSee('Ada Lovelace');
});

it('makes no queue column searchable and offers no filter over a person', function (): void {
    $table = Livewire::test(ListExpressions::class)->instance()->getTable();

    expect($table->isSearchable())->toBeFalse();

    foreach ($table->getColumns() as $column) {
        expect($column->isSearchable())->toBeFalse();
    }

    expect(array_keys($table->getFilters()))->toBe(['state']);
});

it('keeps both queues out of global search', function (): void {
    expect(ExpressionResource::canGloballySearch())->toBeFalse()
        ->and(Livewire::test(ListFlags::class)->instance()->getTable()->isSearchable())->toBeFalse();
});

/**
 * An incrementing id in a URL publishes a row count, and this queue's rows are
 * people's speech. Both halves are pinned: the route the panel registers, and
 * the URL the panel generates.
 */
it('registers no panel route bound to a row id', function (): void {
    $panel = Filament::getPanel('staff');

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), $panel->getPath().'/'))
        ->values();

    expect($routes)->not->toBeEmpty();

    $bound = 0;

    foreach ($routes as $route) {
        if (! in_array('record', $route->parameterNames(), true)) {
            continue;
        }

        $bound++;

        // Laravel keeps the binding field off the URI string, so asserting on
        // the URI alone would pass for a route bound by the primary key.
        expect($route->bindingFieldFor('record'))->toBe('reference');
    }

    expect($bound)->toBe(1);
});

it('generates a view URL carrying the reference and not the id', function (): void {
    $reference = Scenario::review();
    $model = Expression::query()->where('reference', $reference)->firstOrFail();

    $url = ExpressionResource::getUrl('view', ['record' => $reference]);

    expect($url)->toEndWith('/'.$reference)
        ->and($url)->not->toEndWith('/'.$model->getKey())
        ->and(ExpressionResource::getRecordRouteKeyName())->toBe('reference');
});

it('resolves an expression back from its reference', function (): void {
    $reference = Scenario::review();

    expect(ExpressionResource::resolveRecordRouteBinding($reference)?->getAttribute('reference'))->toBe($reference);
});
