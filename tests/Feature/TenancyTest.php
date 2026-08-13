<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\RecordExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionSubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\ExpressionResource;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\Pages\ListExpressions;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsPlugin;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures\Scenario;
use Livewire\Livewire;

beforeEach(function (): void {
    Scenario::screener();
});

it('takes its tenant from the plugin the panel was given', function (): void {
    expect(ReviewsAndRatingsPlugin::tenant())->toBe(Scenario::TENANT);
});

it('shows one business nothing of another', function (): void {
    $mine = Scenario::review();

    $theirs = app(RecordExpression::class)(new ExpressionSubmission(
        tenantId: 'tenant-b',
        productReference: Scenario::PRODUCT,
        authorReference: 'shopper-1',
        authorDisplayName: 'Someone Else',
        score: 5,
        scale: 5,
        body: 'A different business entirely.',
    ))->reference;

    $keys = array_keys(Livewire::test(ListExpressions::class)->instance()->getTableRecords()->all());

    expect($keys)->toBe([$mine])->not->toContain($theirs);
});

it('will not bind another business\' expression by reference', function (): void {
    $theirs = app(RecordExpression::class)(new ExpressionSubmission(
        tenantId: 'tenant-b',
        productReference: Scenario::PRODUCT,
        authorReference: 'shopper-1',
        authorDisplayName: 'Someone Else',
        score: 5,
        scale: 5,
        body: 'A different business entirely.',
    ))->reference;

    expect(ExpressionResource::resolveRecordRouteBinding($theirs))->toBeNull();
});

/**
 * `where('tenant_id', null)` compiles to `is null`, so a panel that could not
 * resolve a tenant would list exactly the orphan rows nobody owns. It refuses
 * instead.
 */
it('refuses to read anything at all when it cannot name a tenant', function (): void {
    Filament::setCurrentPanel(
        Panel::make()
            ->id('unscoped')
            ->path('unscoped')
            ->plugin(ReviewsAndRatingsPlugin::make()),
    );

    expect(fn (): string => ReviewsAndRatingsPlugin::tenant())
        ->toThrow(RuntimeException::class, 'could not resolve a tenant for this panel');
});

it('refuses when the plugin is not attached to the panel at all', function (): void {
    Filament::setCurrentPanel(Panel::make()->id('bare')->path('bare'));

    expect(fn (): ReviewsAndRatingsPlugin => ReviewsAndRatingsPlugin::current())
        ->toThrow(RuntimeException::class, 'not attached to the current Filament panel');
});

it('records a decision against nobody rather than against no one in particular', function (): void {
    auth()->logout();

    expect(fn (): string => ReviewsAndRatingsPlugin::actor())
        ->toThrow(RuntimeException::class, 'cannot record a decision with no authenticated actor');
});
