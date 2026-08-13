<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Flags\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Flags\FlagResource;

/** The report queue. Read, and leave for a decision on the expression itself. */
final class ListFlags extends ListRecords
{
    protected static string $resource = FlagResource::class;

    public function getTitle(): string
    {
        return 'Reported reviews';
    }

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
