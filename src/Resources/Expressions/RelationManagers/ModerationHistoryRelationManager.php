<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ModerationRecord;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsPlugin;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\StaffExpression;

/**
 * Every decision anybody has made about this expression, in order.
 *
 * The host stores moderation as a boolean that `approve()` and `reject()` flip
 * in place, so a review approved, retracted and re-approved is indistinguishable
 * from one approved once and nobody can say who did any of it. This is the same
 * information the host threw away.
 *
 * It is a relation manager rather than another infolist section for one reason:
 * `canAssociate`, `canDissociate` and `canDissociateAny` are live on a `hasMany`
 * and default open. Every one of them is refused below, by name, and asserted.
 * The rows themselves still come from the domain read model.
 */
final class ModerationHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'decisions';

    protected static ?string $title = 'Moderation history';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->rows())
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Decided at')
                    ->dateTime(),
                TextColumn::make('outcome')
                    ->label('Outcome')
                    ->badge(),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('actor_reference')
                    ->label('Decided by'),
            ])
            ->searchable(false)
            ->paginated(false)
            ->emptyStateHeading('No decision yet')
            ->emptyStateDescription('Nothing is displayed by arriving. This expression is waiting for somebody.');
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        $review = app(StaffExpression::class)(
            ReviewsAndRatingsPlugin::tenant(),
            (string) $this->getOwnerRecord()->getAttribute('reference'),
        );

        return array_values(array_map(
            static fn (ModerationRecord $record, int $index): array => [
                '__key' => (string) $index,
                'occurred_at' => $record->occurredAt,
                'outcome' => ucfirst($record->outcome->value),
                'reason' => ucfirst(str_replace('_', ' ', $record->reason->value)),
                'actor_reference' => $record->actorReference,
            ],
            $review->moderationHistory,
            array_keys($review->moderationHistory),
        ));
    }

    /**
     * The base relation-manager table wires a click-through action typed
     * against an Eloquent record. These rows are the domain's read model, and
     * there is nothing to click through to.
     */
    protected function makeTable(): Table
    {
        return parent::makeTable()->recordAction(null)->recordUrl(null);
    }

    /** An audit trail nobody can add to, edit, or take a row out of. */
    public function isReadOnly(): bool
    {
        return true;
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }

    protected function canDeleteAny(): bool
    {
        return false;
    }

    protected function canForceDelete(Model $record): bool
    {
        return false;
    }

    protected function canForceDeleteAny(): bool
    {
        return false;
    }

    protected function canRestore(Model $record): bool
    {
        return false;
    }

    protected function canRestoreAny(): bool
    {
        return false;
    }

    protected function canReplicate(Model $record): bool
    {
        return false;
    }

    protected function canReorder(): bool
    {
        return false;
    }

    protected function canAttach(): bool
    {
        return false;
    }

    protected function canDetach(Model $record): bool
    {
        return false;
    }

    protected function canDetachAny(): bool
    {
        return false;
    }

    protected function canAssociate(): bool
    {
        return false;
    }

    protected function canDissociate(Model $record): bool
    {
        return false;
    }

    protected function canDissociateAny(): bool
    {
        return false;
    }
}
