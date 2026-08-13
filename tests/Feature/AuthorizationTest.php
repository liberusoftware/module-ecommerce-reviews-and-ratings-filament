<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies\ExpressionPolicy;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies\FlagPolicy;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Policies\ModerationDecisionPolicy;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\ExpressionResource;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\RelationManagers\ModerationHistoryRelationManager;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Flags\FlagResource;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsFilamentServiceProvider;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Flag;
use Liberu\Ecommerce\ReviewsAndRatings\Models\ModerationDecision;

/**
 * A model with no policy is exposed, not safe, and a policy missing the method
 * being asked about is the same hazard: Filament's authorization helper returns
 * **allow** for an ability a present policy does not name. So every ability is
 * named, and every one of them is asserted here rather than assumed.
 */
$unpublished = [
    'create', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny',
    'restore', 'restoreAny', 'replicate', 'reorder',
    'attach', 'attachAny', 'detach', 'detachAny',
    'associate', 'associateAny', 'dissociate', 'dissociateAny',
];

it('registers a policy for every domain model this panel touches', function (): void {
    expect(ReviewsAndRatingsFilamentServiceProvider::POLICIES)->toBe([
        Expression::class => ExpressionPolicy::class,
        Flag::class => FlagPolicy::class,
        ModerationDecision::class => ModerationDecisionPolicy::class,
    ]);

    foreach (ReviewsAndRatingsFilamentServiceProvider::POLICIES as $model => $policy) {
        expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
    }
});

it('refuses every unpublished ability by name, on every policy', function (string $policy) use ($unpublished): void {
    $instance = new $policy();

    foreach ($unpublished as $ability) {
        expect(method_exists($instance, $ability))->toBeTrue("[{$policy}] does not answer [{$ability}].")
            ->and($instance->{$ability}())->toBeFalse("[{$policy}] allows [{$ability}].");
    }
})->with([[ExpressionPolicy::class], [FlagPolicy::class], [ModerationDecisionPolicy::class]]);

it('publishes reading, and only reading', function (string $policy): void {
    $instance = new $policy();

    expect($instance->viewAny())->toBeTrue()
        ->and($instance->view())->toBeTrue();
})->with([[ExpressionPolicy::class], [FlagPolicy::class], [ModerationDecisionPolicy::class]]);

it('refuses every unpublished ability on the resource itself, by name', function (string $resource): void {
    $record = new Expression();

    expect($resource::canViewAny())->toBeTrue()
        ->and($resource::canCreate())->toBeFalse()
        ->and($resource::canEdit($record))->toBeFalse()
        ->and($resource::canDelete($record))->toBeFalse()
        ->and($resource::canDeleteAny())->toBeFalse()
        ->and($resource::canForceDelete($record))->toBeFalse()
        ->and($resource::canForceDeleteAny())->toBeFalse()
        ->and($resource::canRestore($record))->toBeFalse()
        ->and($resource::canRestoreAny())->toBeFalse()
        ->and($resource::canReplicate($record))->toBeFalse()
        ->and($resource::canReorder())->toBeFalse()
        ->and($resource::canGloballySearch())->toBeFalse();
})
    // Nested rows on purpose: a bare two-element list of class strings is a
    // callable array to PHP, and Pest evaluates a callable dataset rather than
    // iterating it.
    ->with([[ExpressionResource::class], [FlagResource::class]]);

/**
 * `canAssociate`, `canDissociate` and `canDissociateAny` are live on a `hasMany`
 * and default open. They are the reason the moderation history is a relation
 * manager at all rather than another infolist section — the hazard is real, so
 * it gets refused and asserted rather than avoided.
 */
it('refuses every unpublished ability on the relation manager, by name', function (): void {
    $manager = new ModerationHistoryRelationManager();
    $record = new ModerationDecision();

    $call = static fn (string $method, mixed ...$arguments): mixed => Closure::bind(
        fn (): mixed => $this->{$method}(...$arguments),
        $manager,
        ModerationHistoryRelationManager::class,
    )();

    expect($manager->isReadOnly())->toBeTrue();

    foreach (['canCreate', 'canDeleteAny', 'canForceDeleteAny', 'canRestoreAny', 'canReorder', 'canAttach', 'canDetachAny', 'canAssociate', 'canDissociateAny'] as $ability) {
        expect($call($ability))->toBeFalse("[{$ability}] is open on the moderation history.");
    }

    foreach (['canEdit', 'canDelete', 'canForceDelete', 'canRestore', 'canReplicate', 'canDetach', 'canDissociate'] as $ability) {
        expect($call($ability, $record))->toBeFalse("[{$ability}] is open on the moderation history.");
    }
});
