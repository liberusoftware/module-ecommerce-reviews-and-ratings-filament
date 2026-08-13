<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\CastVote;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\DecideModeration;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\FlagExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\RecordExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\ReviseExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ConfirmsPurchase;
use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ScreensContent;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionSubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Data\PurchaseConfirmation;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ScreeningOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\FlagReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VoteDirection;

/**
 * Fixtures written the way a shopper writes them: through the domain actions.
 *
 * Nothing here inserts a row directly. A panel test that seeded Eloquent would
 * be asserting against states the domain cannot produce.
 */
final class Scenario
{
    public const string TENANT = TestPanelProvider::TENANT;

    public const string PRODUCT = 'sku-camera-9000';

    /** Bind the screening seam. Unbound is a configuration error, not a deployment. */
    public static function screener(?ScreeningOutcome $outcome = null): void
    {
        App::bind(ScreensContent::class, fn (): ScreensContent => new StubScreener($outcome ?? ScreeningOutcome::clean()));
    }

    public static function verifier(?bool $purchased): void
    {
        App::bind(ConfirmsPurchase::class, fn (): ConfirmsPurchase => new class($purchased) implements ConfirmsPurchase
        {
            public function __construct(private readonly ?bool $purchased) {}

            public function confirm(string $authorReference, string $productReference): ?PurchaseConfirmation
            {
                return $this->purchased === null
                    ? null
                    : new PurchaseConfirmation($this->purchased, CarbonImmutable::parse('2026-01-01T00:00:00Z'));
            }
        });
    }

    public static function review(
        string $author = 'shopper-1',
        string $displayName = 'Ada L.',
        ?int $score = 4,
        ?string $body = 'The lens is sharp and the strap is not.',
        string $product = self::PRODUCT,
        ?CarbonImmutable $occurredAt = null,
    ): string {
        return app(RecordExpression::class)(new ExpressionSubmission(
            tenantId: self::TENANT,
            productReference: $product,
            authorReference: $author,
            authorDisplayName: $displayName,
            score: $score,
            scale: $score === null ? null : 5,
            body: $body,
            occurredAt: $occurredAt,
        ))->reference;
    }

    public static function revise(string $reference, ?int $score = 2, ?string $body = 'Second thoughts: the strap broke.'): string
    {
        return app(ReviseExpression::class)(self::TENANT, $reference, new ExpressionRevision(
            score: $score,
            scale: $score === null ? null : 5,
            body: $body,
        ))->reference;
    }

    public static function decide(
        string $reference,
        ModerationOutcome $outcome = ModerationOutcome::Approved,
        ModerationReason $reason = ModerationReason::Compliant,
        string $actor = 'staff-7',
    ): void {
        app(DecideModeration::class)(self::TENANT, $reference, $outcome, $reason, $actor);
    }

    public static function vote(string $reference, string $voter = 'shopper-2', VoteDirection $direction = VoteDirection::Helpful): void
    {
        app(CastVote::class)(self::TENANT, $reference, $voter, $direction);
    }

    public static function flag(string $reference, string $reporter = 'shopper-3', FlagReason $reason = FlagReason::Spam): void
    {
        app(FlagExpression::class)(self::TENANT, $reference, $reporter, $reason);
    }
}
