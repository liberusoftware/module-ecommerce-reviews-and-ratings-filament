<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsFilamentServiceProvider;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures\TestPanelProvider;
use Liberu\Ecommerce\ReviewsAndRatings\ReviewsAndRatingsServiceProvider;
use Liberu\PackageTestbench\PackageTestCase;
use Liberu\PackageTestbench\TestUser;
use Liberu\PackageTestbench\UsesTestUser;
use Livewire\LivewireServiceProvider;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

abstract class TestCase extends PackageTestCase
{
    use UsesTestUser;

    /**
     * `app.debug` is true on purpose, and it is not a convenience.
     *
     * Livewire turns a `TypeError` raised inside a schema closure into a bare
     * `419 Page Expired` unless debug is on: `instance()` comes back null,
     * `errors()` is empty, nothing is written and nothing is thrown, so every
     * signature mistake looks like a session problem. This module is full of
     * integer scores, which is exactly where that trap lives.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.debug', true);
    }

    /**
     * Testbench runs no package discovery, and only *direct* dependencies'
     * `extra.laravel.providers` are picked up — so Livewire, which this package
     * reaches only through Filament, would never boot.
     *
     * The domain provider is named here rather than by putting the domain
     * package in `require-dev` as well: `composer validate` warns about a
     * package in both sections, and `Install` runs `composer validate`.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            // Filament before Livewire, which is the order Laravel's own
            // discovery produces and is not cosmetic: `filament/support`
            // rebinds Livewire's `DataStore` to its own subclass, and a bind
            // drops any instance already registered under that key. Boot
            // Livewire first and every component gets a fresh, empty data
            // store on each lookup — which surfaces as a null error bag while
            // rendering, nowhere near the cause.
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            SchemasServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            LivewireServiceProvider::class,
            ReviewsAndRatingsServiceProvider::class,
            ReviewsAndRatingsFilamentServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(TestUser::factory()->create(['name' => 'Ada Moderator']));

        Filament::setCurrentPanel('staff');
    }
}
