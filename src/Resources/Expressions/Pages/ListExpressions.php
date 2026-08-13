<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\ExpressionResource;

/**
 * The queue a moderator opens.
 *
 * It defaults to `pending` and filters by state rather than sorting by it. The
 * domain does not offer "no decision first" as an ordering on purpose: that is
 * an order over a nullable subquery, and Postgres, MySQL and SQLite do not
 * agree about where nulls sort. Urgency is the ordering, and the domain
 * supplies it.
 */
final class ListExpressions extends ListRecords
{
    protected static string $resource = ExpressionResource::class;

    public function getTitle(): string
    {
        return 'Moderation queue';
    }

    /** Nothing is created from a panel. Speech arrives from a shopper. */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * `ListRecords` wires a click-through action typed against an Eloquent
     * record. These rows are staff read models, so the row link is configured
     * on the table instead and this one is removed.
     */
    protected function makeTable(): Table
    {
        return parent::makeTable()->recordAction(null);
    }
}
