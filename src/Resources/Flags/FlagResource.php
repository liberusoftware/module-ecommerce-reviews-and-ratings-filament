<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Flags;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\ReviewsAndRatings\Data\FlagRecord;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\ExpressionResource;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Flags\Pages\ListFlags;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsPlugin;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Flag;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\FlagQueue;

/**
 * What readers have reported, newest first.
 *
 * Reporting hides nothing and this queue decides nothing. It routes a report to
 * a person; what that person decides is appended to the expression, where it
 * names them. So the only action here is a link to the expression.
 *
 * **The reporter is not shown, and there is no column for it.** A reporter
 * reference is an author reference — the sensitive thing in this module — and a
 * queue that displayed one would be a page where a moderator learns which
 * shopper reported which neighbour.
 *
 * @extends resource<Flag>
 */
final class FlagResource extends Resource
{
    protected static ?string $model = Flag::class;

    protected static ?string $slug = 'review-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Reported reviews';

    protected static ?string $modelLabel = 'report';

    protected static ?string $pluralModelLabel = 'reports';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', ReviewsAndRatingsPlugin::tenant());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->records(self::rows(...))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Reported at')
                    ->dateTime(),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('expression_reference')
                    ->label('Expression'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open the review')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(static fn (array $record): string => ExpressionResource::getUrl('view', [
                        'record' => $record['expression_reference'],
                    ])),
            ])
            ->recordUrl(static fn (array $record): string => ExpressionResource::getUrl('view', [
                'record' => $record['expression_reference'],
            ]))
            ->searchable(false)
            ->paginated(false)
            ->emptyStateHeading('Nothing reported')
            ->emptyStateDescription('No reader has reported an expression in this business.');
    }

    /**
     * @return list<array<string, mixed>>
     *
     * ponytail: the domain publishes no flag count, so this reads one bounded
     * page. Paginate it in 0.2.0 when a queue this long is a real complaint.
     */
    public static function rows(): array
    {
        return array_map(
            static fn (FlagRecord $flag): array => [
                '__key' => $flag->expressionReference.'|'.$flag->occurredAt->getTimestamp(),
                'expression_reference' => $flag->expressionReference,
                'reason' => ucfirst(str_replace('_', ' ', $flag->reason->value)),
                'occurred_at' => $flag->occurredAt,
            ],
            app(FlagQueue::class)(ReviewsAndRatingsPlugin::tenant(), 50),
        );
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListFlags::route('/'),
        ];
    }

    public static function canGloballySearch(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function canReorder(): bool
    {
        return false;
    }
}
