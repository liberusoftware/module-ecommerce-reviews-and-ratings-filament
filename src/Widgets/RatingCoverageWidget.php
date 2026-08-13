<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\ReviewsAndRatings\Data\RatingAggregate;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsPlugin;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Support\Presentation;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\DisplayedRatingAggregate;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\RecordedRatingTotal;

/**
 * What moderation is costing the number on the product page.
 *
 * "4.3 stars" is not a fact. "4.3 from 87 displayed ratings out of 91 recorded"
 * is, and the gap between the two is the whole point of this widget: it is the
 * only place a moderator can see what withholding has done to the published
 * figure. The two aggregates are named differently by the domain on purpose —
 * two aggregates over the same rows with the same name is how a total including
 * withheld content ends up on a product page.
 *
 * Both come from the domain's own queries. There is no counter anywhere and
 * nothing here recomputes either figure.
 */
class RatingCoverageWidget extends StatsOverviewWidget
{
    public ?Model $record = null;

    /** The scale a five-point star sits on. Anything else is a different fact. */
    public int $scale = 5;

    protected ?string $heading = 'Published rating coverage';

    protected ?string $description = 'Every aggregate names the population it summarises.';

    public static function canView(): bool
    {
        return true;
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $product = $this->productReference();

        if ($product === null) {
            return [
                Stat::make('No product', 'not applicable')
                    ->description('A merchant reply has no product of its own to aggregate.'),
            ];
        }

        $tenant = ReviewsAndRatingsPlugin::tenant();

        $displayed = app(DisplayedRatingAggregate::class)($tenant, $product, $this->scale);
        $recorded = app(RecordedRatingTotal::class)($tenant, $product, $this->scale);

        return [
            Stat::make('Displayed', Presentation::aggregate($displayed->sum, $displayed->count, $displayed->scale))
                ->description('Population: '.$displayed->population->value.' — approved, and only approved')
                ->color('success'),
            Stat::make('Recorded', Presentation::aggregate($recorded->sum, $recorded->count, $recorded->scale))
                ->description('Population: '.$recorded->population->value.' — staff only, never a product page')
                ->color('gray'),
            Stat::make('Not displayed', $this->undisplayed($displayed, $recorded).' of '.$recorded->count)
                ->description('Recorded ratings no decision has published')
                ->color($this->undisplayed($displayed, $recorded) > 0 ? 'warning' : 'gray'),
        ];
    }

    private function undisplayed(RatingAggregate $displayed, RatingAggregate $recorded): int
    {
        return max(0, $recorded->count - $displayed->count);
    }

    private function productReference(): ?string
    {
        $product = $this->record?->getAttribute('product_reference');

        return is_string($product) ? $product : null;
    }
}
