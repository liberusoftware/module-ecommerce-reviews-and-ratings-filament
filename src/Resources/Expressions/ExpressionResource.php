<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Liberu\Ecommerce\ReviewsAndRatings\Data\StaffReview;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningPriority;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\Pages\ListExpressions;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\Pages\ViewExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\RelationManagers\ModerationHistoryRelationManager;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsPlugin;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Support\Presentation;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\ModerationQueue;

/**
 * The moderation queue. This resource is the module.
 *
 * The host's whole moderation surface is a toggle on an edit form, and ADR 0008
 * exists to preserve it. What a moderator actually needs is here instead: what
 * the machine flagged, how urgently, how many readers reported it, and — the
 * one that has no equivalent in the host — whether this is an edit of something
 * that was already approved.
 *
 * Every row comes from {@see ModerationQueue}. Nothing in this panel recomputes
 * a display state or a supersession from Eloquent: the state is a fold over the
 * decisions, and a second implementation of that fold is how a host ends up
 * with two averages that disagree.
 *
 * **No author reference and no display name appear in this table at all.** Not
 * hidden — absent. Search terms and filter state both persist into the query
 * string, and a column that is not there cannot be made searchable by the next
 * person to touch this file.
 *
 * @extends resource<Expression>
 */
final class ExpressionResource extends Resource
{
    protected static ?string $model = Expression::class;

    /**
     * Bind by the opaque reference, never the row id.
     *
     * This is only the inbound half. URL *generation* still emits the key the
     * model declares, so the view page is registered as `/{record:reference}`
     * in {@see self::getPages()} — both halves, or a moderation queue publishes
     * a row count in its address bar.
     */
    protected static ?string $recordRouteKeyName = 'reference';

    protected static ?string $slug = 'review-moderation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Moderation queue';

    protected static ?string $modelLabel = 'review';

    protected static ?string $pluralModelLabel = 'reviews';

    /**
     * Scoped to the panel's tenant, and refusing rather than defaulting.
     *
     * `ReviewsAndRatingsPlugin::tenant()` throws when it cannot answer, because
     * `where('tenant_id', null)` compiles to `is null` and would bind exactly
     * the orphan rows nobody owns.
     */
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
                    ->label('Said at')
                    ->dateTime()
                    ->sortable(false),
                TextColumn::make('kind')
                    ->label('Kind')
                    ->badge()
                    ->formatStateUsing(static fn (ExpressionKind $state): string => $state === ExpressionKind::MerchantReply ? 'Merchant reply' : 'Review'),
                TextColumn::make('product_reference')
                    ->label('Product reference')
                    ->placeholder('—')
                    ->description(static fn (array $record): ?string => Presentation::score($record['score'], $record['scale'])),
                TextColumn::make('body')
                    ->label('What was said')
                    ->placeholder('no words, a score only')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('display_state')
                    ->label('Display state')
                    ->badge()
                    ->formatStateUsing(static fn (DisplayState $state): string => ucfirst($state->value))
                    ->color(static fn (DisplayState $state): string => Presentation::displayStateColor($state)),
                TextColumn::make('screening_priority')
                    ->label('Screening')
                    ->badge()
                    ->formatStateUsing(static fn (ScreeningPriority $state): string => ucfirst($state->value))
                    ->color(static fn (ScreeningPriority $state): string => Presentation::screeningPriorityColor($state))
                    ->description(static fn (array $record): ?string => $record['screening_signals'] === '' ? null : $record['screening_signals']),
                IconColumn::make('edited_after_approval')
                    ->label('Edited after approval')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedExclamationTriangle)
                    ->falseIcon(Heroicon::OutlinedMinusSmall)
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->tooltip(static fn (array $record): ?string => $record['edited_after_approval']
                        ? 'This supersedes an expression that had already been approved.'
                        : null),
                TextColumn::make('flag_count')
                    ->label('Reports')
                    ->badge()
                    ->color(static fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('verification')
                    ->label('Purchase')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(static fn (VerificationState $state): ?string => Presentation::verificationLabel($state))
                    ->color(static fn (VerificationState $state): ?string => Presentation::verificationColor($state)),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Display state')
                    ->options(self::stateOptions())
                    ->default(DisplayState::Pending->value)
                    ->selectablePlaceholder(true)
                    ->placeholder('Every state'),
            ])
            ->recordActions([
                // A link rather than a ViewAction: rows here are staff read
                // models, not Eloquent records, and Filament's record
                // authorization on a resource page takes a Model. The page it
                // opens authorizes the record itself.
                Action::make('open')
                    ->label('Review')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(static fn (array $record): string => self::getUrl('view', [
                        'record' => $record['reference'],
                    ])),
            ])
            ->recordUrl(static fn (array $record): string => self::getUrl('view', [
                'record' => $record['reference'],
            ]))
            ->searchable(false)
            ->emptyStateHeading('Nothing waiting')
            ->emptyStateDescription('No expression is in this state right now.')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50]);
    }

    /** @return array<string, string> */
    public static function stateOptions(): array
    {
        $options = [];

        foreach (DisplayState::cases() as $state) {
            $options[$state->value] = ucfirst($state->value);
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return Paginator<int, array<string, mixed>>
     */
    public static function rows(?array $filters, int|string $page, int|string $recordsPerPage): Paginator
    {
        $perPage = max(1, (int) $recordsPerPage);
        $currentPage = max(1, (int) $page);

        $state = $filters['state']['value'] ?? null;

        /** @var list<StaffReview> $reviews */
        $reviews = app(ModerationQueue::class)(
            ReviewsAndRatingsPlugin::tenant(),
            is_string($state) && $state !== '' ? DisplayState::from($state) : null,
            // One more than the page, so "is there a next page" is answered
            // without a count query the domain does not publish.
            $perPage + 1,
            ($currentPage - 1) * $perPage,
        );

        return new Paginator(
            array_map(self::row(...), $reviews),
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * One staff read model, flattened for a table row.
     *
     * The author reference and the display name are not copied across. This is
     * the projection, and there is no field here to forget to blank.
     *
     * @return array<string, mixed>
     */
    private static function row(StaffReview $review): array
    {
        return [
            '__key' => $review->reference,
            'reference' => $review->reference,
            'kind' => $review->kind,
            'product_reference' => $review->productReference,
            'score' => $review->score,
            'scale' => $review->scale,
            'body' => $review->body,
            'display_state' => $review->displayState,
            'screening_priority' => $review->screeningPriority,
            'screening_signals' => implode(', ', array_map(
                static fn ($signal): string => str_replace('_', ' ', $signal->value),
                $review->screeningSignals,
            )),
            'edited_after_approval' => $review->editedAfterApproval,
            'flag_count' => $review->flagCount,
            'verification' => $review->verification,
            'occurred_at' => $review->occurredAt,
        ];
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            ModerationHistoryRelationManager::class,
        ];
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListExpressions::route('/'),
            'view' => ViewExpression::route('/{record:reference}'),
        ];
    }

    /**
     * Global search would put a review body — and whatever a shopper typed into
     * it — into a query string on every panel page. It is off.
     */
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
