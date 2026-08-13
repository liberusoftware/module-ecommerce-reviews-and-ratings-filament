<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\DecideModeration;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\RecordMerchantReply;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ModerationRecord;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ReplySubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Data\StaffReview;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ReviewsAndRatingsException;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\ExpressionResource;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsPlugin;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Support\Presentation;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Widgets\RatingCoverageWidget;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\StaffExpression;

/**
 * One expression, read-only, with everything anybody ever decided about it.
 *
 * Nothing on this page edits what was said. The two things it can do — take a
 * decision and write a merchant reply — both append through a domain action,
 * and both are refused by the domain rather than by a check here.
 */
final class ViewExpression extends ViewRecord
{
    protected static string $resource = ExpressionResource::class;

    private ?StaffReview $staffReview = null;

    public function getTitle(): string
    {
        return 'Review '.$this->review()->reference;
    }

    /**
     * The staff projection, read once per request through the domain query.
     *
     * The page never derives a display state or a supersession itself. There is
     * one implementation of that fold and it is not in a panel.
     */
    public function review(): StaffReview
    {
        return $this->staffReview ??= app(StaffExpression::class)(
            ReviewsAndRatingsPlugin::tenant(),
            (string) $this->getRecord()->getAttribute('reference'),
        );
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('What was said')
                ->description('A historical fact. It is not editable from here, or from anywhere.')
                ->schema([
                    TextEntry::make('reference')
                        ->label('Reference')
                        ->state(fn (): string => $this->review()->reference),
                    TextEntry::make('kind')
                        ->label('Kind')
                        ->state(fn (): string => $this->review()->kind === ExpressionKind::MerchantReply ? 'Merchant reply' : 'Shopper review'),
                    TextEntry::make('product_reference')
                        ->label('Product reference')
                        ->placeholder('—')
                        ->state(fn (): ?string => $this->review()->productReference),
                    TextEntry::make('score')
                        ->label('Score')
                        ->placeholder('no score')
                        ->state(fn (): ?string => Presentation::score($this->review()->score, $this->review()->scale)),
                    TextEntry::make('body')
                        ->label('Body')
                        ->placeholder('no words, a score only')
                        ->state(fn (): ?string => $this->review()->body)
                        ->columnSpanFull(),
                    TextEntry::make('locale')
                        ->label('Locale')
                        ->state(fn (): string => $this->review()->locale),
                    TextEntry::make('source')
                        ->label('Source')
                        ->badge()
                        ->state(fn (): string => str_replace('_', ' ', $this->review()->source->value)),
                    TextEntry::make('source_reference')
                        ->label('Source reference')
                        ->placeholder('—')
                        ->state(fn (): ?string => $this->review()->sourceReference),
                    TextEntry::make('incentive')
                        ->label('Incentive disclosed')
                        ->badge()
                        ->color(fn (): string => $this->review()->incentive->isDisclosed() ? 'warning' : 'gray')
                        ->state(fn (): string => str_replace('_', ' ', $this->review()->incentive->value)),
                    TextEntry::make('occurred_at')
                        ->label('Said at')
                        ->dateTime()
                        ->state(fn () => $this->review()->occurredAt),
                    TextEntry::make('recorded_at')
                        ->label('Recorded at')
                        ->dateTime()
                        ->state(fn () => $this->review()->recordedAt),
                ])
                ->columns(3),

            Section::make('Who said it')
                ->description('Held for staff, shown nowhere a shopper can reach, and never searchable or filterable.')
                ->schema([
                    TextEntry::make('author_display_name')
                        ->label('Display name the author chose')
                        ->placeholder('redacted')
                        ->state(fn (): ?string => $this->review()->authorDisplayName),
                    TextEntry::make('author_reference')
                        ->label('Author reference')
                        ->placeholder('redacted')
                        ->state(fn (): ?string => $this->review()->authorReference),
                    TextEntry::make('verification')
                        ->label('Purchase')
                        ->badge()
                        // Unknown renders nothing at all. The absence of a badge
                        // is a claim, and "not a verified purchase" said when
                        // nothing was ever asked is a lie of omission.
                        ->placeholder('not checked')
                        ->state(fn (): ?string => Presentation::verificationLabel($this->review()->verification))
                        ->color(fn (): ?string => Presentation::verificationColor($this->review()->verification)),
                ])
                ->columns(3),

            Section::make('Custody')
                ->description('Whether this is an edit, whether it has been edited since, and whether it has been erased.')
                ->schema([
                    TextEntry::make('display_state')
                        ->label('Display state')
                        ->badge()
                        ->state(fn (): string => ucfirst($this->review()->displayState->value))
                        ->color(fn (): string => Presentation::displayStateColor($this->review()->displayState)),
                    IconEntry::make('edited_after_approval')
                        ->label('Edited after approval')
                        ->boolean()
                        ->trueIcon(Heroicon::OutlinedExclamationTriangle)
                        ->falseIcon(Heroicon::OutlinedMinusSmall)
                        ->trueColor('danger')
                        ->falseColor('gray')
                        ->state(fn (): bool => $this->review()->editedAfterApproval),
                    TextEntry::make('supersedes_reference')
                        ->label('Supersedes')
                        ->placeholder('nothing — this is the original')
                        ->state(fn (): ?string => $this->review()->supersedesReference),
                    IconEntry::make('is_superseded')
                        ->label('Superseded since')
                        ->boolean()
                        ->state(fn (): bool => $this->review()->isSuperseded),
                    IconEntry::make('is_redacted')
                        ->label('Erased')
                        ->boolean()
                        ->state(fn (): bool => $this->review()->isRedacted),
                    TextEntry::make('flag_count')
                        ->label('Reader reports')
                        ->state(fn (): int => $this->review()->flagCount),
                ])
                ->columns(3),

            Section::make('What the machine found')
                ->description('Screening queues an expression for a person. It never decides.')
                ->schema([
                    TextEntry::make('screening_priority')
                        ->label('Priority')
                        ->badge()
                        ->state(fn (): string => ucfirst($this->review()->screeningPriority->value))
                        ->color(fn (): string => Presentation::screeningPriorityColor($this->review()->screeningPriority)),
                    TextEntry::make('screening_signals')
                        ->label('Signals')
                        ->badge()
                        ->placeholder('nothing raised')
                        ->state(fn (): array => array_map(
                            static fn (ModerationReason $reason): string => str_replace('_', ' ', $reason->value),
                            $this->review()->screeningSignals,
                        )),
                    TextEntry::make('votes')
                        ->label('Readers found it')
                        ->state(fn (): string => $this->review()->votes->helpful.' helpful, '.$this->review()->votes->unhelpful.' unhelpful'),
                ])
                ->columns(3),
        ]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('decide')
                ->label('Take a decision')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->modalHeading('Decide whether this is displayed')
                ->modalDescription('Appended, never flipped. The decision names you and stays on the record.')
                ->schema([
                    Select::make('outcome')
                        ->label('Outcome')
                        ->options(self::outcomeOptions())
                        ->required()
                        ->native(false),
                    // A closed enum, and there is no free-text companion field.
                    // A moderation reason sits beside a person's name by
                    // construction, and a free-text box beside an event log is
                    // where personal data gets typed.
                    Select::make('reason')
                        ->label('Reason')
                        ->options(self::reasonOptions())
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $this->guarded(function () use ($data): void {
                        $record = app(DecideModeration::class)(
                            ReviewsAndRatingsPlugin::tenant(),
                            $this->review()->reference,
                            ModerationOutcome::from((string) $data['outcome']),
                            ModerationReason::from((string) $data['reason']),
                            ReviewsAndRatingsPlugin::actor(),
                        );

                        $this->forgetProjection();

                        Notification::make()
                            ->success()
                            ->title('Decision recorded')
                            ->body($this->decisionSummary($record))
                            ->send();
                    });
                })
                ->visible(fn (): bool => ! $this->review()->isSuperseded),

            Action::make('reply')
                ->label('Reply as the merchant')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->modalHeading('Answer this review')
                ->modalDescription('A reply is an expression too: it is screened, it is moderated, and it is signed with the merchant name this panel is configured with.')
                ->schema([
                    Textarea::make('body')
                        ->label('Reply')
                        ->required()
                        ->maxLength(Presentation::maxBodyLength())
                        ->rows(6),
                ])
                ->action(function (array $data): void {
                    $this->guarded(function () use ($data): void {
                        app(RecordMerchantReply::class)(new ReplySubmission(
                            tenantId: ReviewsAndRatingsPlugin::tenant(),
                            parentReference: $this->review()->reference,
                            authorReference: ReviewsAndRatingsPlugin::actor(),
                            authorDisplayName: ReviewsAndRatingsPlugin::replyName(),
                            body: (string) $data['body'],
                            locale: $this->review()->locale,
                        ));

                        $this->forgetProjection();

                        Notification::make()
                            ->success()
                            ->title('Reply recorded')
                            ->body('It is pending, like anything else that arrives.')
                            ->send();
                    });
                })
                ->visible(fn (): bool => $this->review()->kind === ExpressionKind::ShopperReview
                    && ! $this->review()->isSuperseded
                    && ! $this->review()->isRedacted),
        ];
    }

    /** @return array<class-string> */
    protected function getFooterWidgets(): array
    {
        return [
            RatingCoverageWidget::class,
        ];
    }

    /** @return array<string, string> */
    public static function outcomeOptions(): array
    {
        $options = [];

        foreach (ModerationOutcome::cases() as $outcome) {
            $options[$outcome->value] = ucfirst($outcome->value);
        }

        return $options;
    }

    /** @return array<string, string> */
    public static function reasonOptions(): array
    {
        $options = [];

        foreach (ModerationReason::cases() as $reason) {
            $options[$reason->value] = ucfirst(str_replace('_', ' ', $reason->value));
        }

        return $options;
    }

    /**
     * Every domain refusal reaches the moderator as itself.
     *
     * An unbound screener is a 503 and it must look like one here: a panel that
     * swallowed it would accept a reply the domain never wrote, and the
     * moderator would find out when the reply was not there.
     */
    private function guarded(callable $write): void
    {
        try {
            $write();
        } catch (ReviewsAndRatingsException $e) {
            Notification::make()
                ->danger()
                ->title('Refused ('.$e->status().')')
                ->body($e->getMessage())
                ->send();
        }
    }

    private function decisionSummary(ModerationRecord $record): string
    {
        return ucfirst($record->outcome->value)
            .', for '.str_replace('_', ' ', $record->reason->value)
            .', by '.$record->actorReference;
    }

    private function forgetProjection(): void
    {
        $this->staffReview = null;
        $this->getRecord()->refresh();
    }
}
