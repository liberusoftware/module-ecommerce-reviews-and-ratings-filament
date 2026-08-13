<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsPlugin;

/**
 * The one panel these tests act on.
 *
 * It exists in `tests/`, not in `src/`, because the package registers no panel:
 * a host attaches the plugin to whichever panel it wants this surface on, and
 * shipping one would put a moderation queue wherever the application happened
 * to let somebody in.
 */
final class TestPanelProvider extends PanelProvider
{
    public const string TENANT = 'tenant-a';

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('staff')
            ->path('staff')
            ->plugin(
                ReviewsAndRatingsPlugin::make()
                    ->tenantUsing(fn (): string => self::TENANT),
            );
    }
}
