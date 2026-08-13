<?php

declare(strict_types=1);

use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ScreensContent;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\Pages\ViewExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsPlugin;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Support\Presentation;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures\Scenario;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\StaffExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Support\SubmissionRules;
use Liberu\PackageTestbench\TestUser;
use Livewire\Livewire;

it('writes a reply that is pending, like anything else that arrives', function (): void {
    Scenario::screener();
    $reference = Scenario::review();

    Livewire::test(ViewExpression::class, ['record' => $reference])
        ->callAction('reply', ['body' => 'Sorry about the strap — we have replaced the supplier.'])
        ->assertHasNoActionErrors();

    $reply = Expression::query()->where('kind', ExpressionKind::MerchantReply->value)->firstOrFail();

    $staff = app(StaffExpression::class)(Scenario::TENANT, (string) $reply->getAttribute('reference'));

    expect($staff->kind)->toBe(ExpressionKind::MerchantReply)
        ->and($staff->displayState)->toBe(DisplayState::Pending)
        ->and($staff->parentReference)->toBe($reference);
});

it('signs a reply with the merchant, never with the shopper', function (): void {
    Scenario::screener();
    $reference = Scenario::review(author: 'shopper-1', displayName: 'Ada L.');

    Livewire::test(ViewExpression::class, ['record' => $reference])
        ->callAction('reply', ['body' => 'Thank you for telling us.']);

    $reply = Expression::query()->where('kind', ExpressionKind::MerchantReply->value)->firstOrFail();

    expect($reply->getAttribute('author_display_name'))->toBe('Ada Moderator')
        ->and($reply->getAttribute('author_reference'))->toBe((string) TestUser::query()->firstOrFail()->getKey())
        ->and($reply->getAttribute('author_reference'))->not->toBe('shopper-1');
});

/**
 * An unbound screener is a configuration error, not a deployment: its absence
 * removes a control rather than a claim. The panel must show that as itself —
 * a surface that swallowed the 503 would report a reply it never wrote.
 */
it('refuses to reply at all when screening is unbound, and says so', function (): void {
    Scenario::screener();
    $reference = Scenario::review();

    App::forgetInstance(ScreensContent::class);
    App::offsetUnset(ScreensContent::class);

    Livewire::test(ViewExpression::class, ['record' => $reference])
        ->callAction('reply', ['body' => 'We are sorry to hear that.'])
        ->assertNotified(
            Notification::make()
                ->danger()
                ->title('Refused (503)')
                ->body('No ScreensContent implementation is bound; this module will not accept free text unscreened.'),
        );

    expect(Expression::query()->where('kind', ExpressionKind::MerchantReply->value)->count())->toBe(0);
});

it('reads the one body limit the domain publishes, rather than inventing a fourth', function (): void {
    Scenario::screener();
    $reference = Scenario::review();

    $page = Livewire::test(ViewExpression::class, ['record' => $reference])->instance();
    $components = $page->getAction('reply')?->getSchema(Schema::make($page))?->getComponents() ?? [];

    expect($components)->toHaveCount(1)
        ->and($components[0]->getMaxLength())->toBe(SubmissionRules::MAX_BODY_LENGTH)
        ->and(Presentation::maxBodyLength())->toBe(5000);
});

it('does not offer a reply on a merchant reply', function (): void {
    Scenario::screener();
    $reference = Scenario::review();

    Livewire::test(ViewExpression::class, ['record' => $reference])
        ->callAction('reply', ['body' => 'We will do better.']);

    $reply = Expression::query()->where('kind', ExpressionKind::MerchantReply->value)->firstOrFail();

    Livewire::test(ViewExpression::class, ['record' => (string) $reply->getAttribute('reference')])
        ->assertActionHidden('reply');
});

it('refuses a reply the module has no name to sign with', function (): void {
    Scenario::screener();
    Filament\Facades\Filament::auth()->user()?->setAttribute('name', '');

    expect(fn (): string => ReviewsAndRatingsPlugin::replyName())
        ->toThrow(RuntimeException::class, 'could not resolve a name to sign a merchant reply');
});
