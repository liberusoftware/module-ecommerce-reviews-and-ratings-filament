<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies\ExpressionPolicy;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies\FlagPolicy;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies\ModerationDecisionPolicy;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Flag;
use Liberu\Ecommerce\ReviewsAndRatings\Models\ModerationDecision;

/**
 * Registers policies, and nothing else.
 *
 * No panel, no resource and no page is registered from here. A host attaches
 * {@see ReviewsAndRatingsPlugin} to the one panel it wants this surface on, so
 * installing the package contributes nothing until somebody decides where it
 * goes. The package ships no `extra.laravel.providers`, so Composer boots
 * nothing at all — the host's module manager registers this provider only when
 * the module is named in `MODULES_ENABLED`.
 *
 * Policies are the exception, and deliberately so. Laravel's unanswered gate
 * case is permissive, and Filament's is worse: a *present* policy missing the
 * method being asked about returns allow. Three domain models reach the panel,
 * so three policies are registered, each answering every ability by name.
 * Registering them with the panel instead would leave the same models
 * unprotected everywhere else in the application.
 */
class ReviewsAndRatingsFilamentServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public const array POLICIES = [
        Expression::class => ExpressionPolicy::class,
        Flag::class => FlagPolicy::class,
        ModerationDecision::class => ModerationDecisionPolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
