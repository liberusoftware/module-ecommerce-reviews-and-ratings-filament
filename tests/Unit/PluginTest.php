<?php

declare(strict_types=1);

use Filament\Panel;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningPriority;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\ExpressionResource;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Flags\FlagResource;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsPlugin;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Support\Presentation;
use Liberu\PackageTestbench\PackageRoot;
use Liberu\PackageTestbench\TestUser;

it('contributes its surface to the panel it is attached to, and to no other', function (): void {
    $attached = Panel::make()->id('attached')->path('attached')->plugin(ReviewsAndRatingsPlugin::make());
    $bare = Panel::make()->id('untouched')->path('untouched');

    // Values, not the array itself: Filament has changed whether the resource
    // registry is keyed by class across patch releases, and the assertion is
    // about what the panel got, not how the registry is shaped.
    expect(array_values($attached->getResources()))->toBe([
        ExpressionResource::class,
        FlagResource::class,
    ])
        ->and($bare->getResources())->toBe([])
        ->and(ReviewsAndRatingsPlugin::make()->getId())->toBe('ecommerce-reviews-and-ratings');
});

it('declares every class its manifest names for a panel', function (): void {
    $manifest = PackageRoot::manifest(PackageRoot::locate((string) getcwd()));

    expect($manifest['category'])->toBe('presentation')
        ->and($manifest['presentation']['filament'])->not->toBeEmpty();

    foreach ($manifest['presentation']['filament'] as $classes) {
        expect($classes)->not->toBeEmpty();

        foreach ($classes as $class) {
            expect(class_exists($class))->toBeTrue("[{$class}] does not exist.");
        }
    }
});

it('ships no laravel provider, so composer installing it boots nothing', function (): void {
    $composer = PackageRoot::composer(PackageRoot::locate((string) getcwd()));

    expect($composer['extra']['laravel']['providers'] ?? [])->toBe([])
        ->and($composer['require'])->toHaveKey('liberusoftware/ecommerce-reviews-and-ratings');
});

it('keeps unknown apart from unverified, in the labels and the colours both', function (): void {
    expect(Presentation::verificationLabel(VerificationState::Verified))->toBe('Verified purchase')
        ->and(Presentation::verificationLabel(VerificationState::Unverified))->toBe('Not a verified purchase')
        ->and(Presentation::verificationLabel(VerificationState::Unknown))->toBeNull()
        ->and(Presentation::verificationColor(VerificationState::Verified))->toBe('success')
        ->and(Presentation::verificationColor(VerificationState::Unverified))->toBe('warning')
        ->and(Presentation::verificationColor(VerificationState::Unknown))->toBeNull();
});

it('gives every closed enum case a colour, so none renders untyped', function (): void {
    foreach (DisplayState::cases() as $state) {
        expect(Presentation::displayStateColor($state))->toBeString()->not->toBe('');
    }

    foreach (ScreeningPriority::cases() as $priority) {
        expect(Presentation::screeningPriorityColor($priority))->toBeString()->not->toBe('');
    }
});

it('will not show a score without the scale it was given on', function (): void {
    expect(Presentation::score(4, 5))->toBe('4 / 5')
        ->and(Presentation::score(null, 5))->toBeNull()
        ->and(Presentation::score(4, null))->toBeNull()
        ->and(Presentation::score(null, null))->toBeNull();
});

it('signs replies with the name the panel was configured with', function (): void {
    Filament\Facades\Filament::setCurrentPanel(
        Panel::make()->id('named')->path('named')->plugin(
            ReviewsAndRatingsPlugin::make()->replyingAs(fn (): string => 'Acme Support'),
        ),
    );

    expect(ReviewsAndRatingsPlugin::replyName())->toBe('Acme Support');
});

it('falls back to the panel tenant when the host gives it no resolver', function (): void {
    $tenant = TestUser::factory()->create();

    Filament\Facades\Filament::setCurrentPanel(
        Panel::make()->id('panel-tenant')->path('panel-tenant')->plugin(ReviewsAndRatingsPlugin::make()),
    );
    Filament\Facades\Filament::setTenant($tenant, isQuiet: true);

    expect(ReviewsAndRatingsPlugin::tenant())->toBe((string) $tenant->getKey());
});

it('will not sign a reply when nobody is signed in', function (): void {
    Filament\Facades\Filament::setCurrentPanel(
        Panel::make()->id('anonymous')->path('anonymous')->plugin(ReviewsAndRatingsPlugin::make()),
    );
    auth()->logout();

    expect(fn (): string => ReviewsAndRatingsPlugin::replyName())
        ->toThrow(RuntimeException::class, 'could not resolve a name to sign a merchant reply');
});

it('boots without contributing anything of its own', function (): void {
    $panel = Panel::make()->id('booted')->path('booted');
    $plugin = ReviewsAndRatingsPlugin::make();

    $plugin->boot($panel);

    expect($panel->getResources())->toBe([]);
});
