<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures;

use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ScreensContent;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ScreeningOutcome;

/** A screener that says whatever the test needs. It never rejects, because none may. */
final readonly class StubScreener implements ScreensContent
{
    public function __construct(private ScreeningOutcome $outcome) {}

    public function screen(string $body, string $locale): ScreeningOutcome
    {
        return $this->outcome;
    }
}
