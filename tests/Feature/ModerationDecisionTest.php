<?php

declare(strict_types=1);

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\Pages\ViewExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Tests\Fixtures\Scenario;
use Liberu\PackageTestbench\TestUser;
use Livewire\Livewire;

beforeEach(function (): void {
    Scenario::screener();
});

it('appends a decision that names the moderator taking it', function (): void {
    $reference = Scenario::review();
    $actor = (string) TestUser::query()->firstOrFail()->getKey();

    $page = Livewire::test(ViewExpression::class, ['record' => $reference])
        ->callAction('decide', [
            'outcome' => ModerationOutcome::Approved->value,
            'reason' => ModerationReason::Compliant->value,
        ])
        ->assertHasNoActionErrors();

    $review = $page->instance()->review();

    expect($review->displayState)->toBe(DisplayState::Approved)
        ->and($review->moderationHistory)->toHaveCount(1)
        ->and($review->moderationHistory[0]->actorReference)->toBe($actor)
        ->and($review->moderationHistory[0]->reason)->toBe(ModerationReason::Compliant);
});

it('retracts by appending, never by flipping', function (): void {
    $reference = Scenario::review();
    Scenario::decide($reference, ModerationOutcome::Approved, ModerationReason::Compliant, 'staff-1');

    $review = Livewire::test(ViewExpression::class, ['record' => $reference])
        ->callAction('decide', [
            'outcome' => ModerationOutcome::Withheld->value,
            'reason' => ModerationReason::UndisclosedIncentive->value,
        ])
        ->instance()
        ->review();

    expect($review->displayState)->toBe(DisplayState::Withheld)
        ->and($review->moderationHistory)->toHaveCount(2)
        ->and($review->moderationHistory[0]->outcome)->toBe(ModerationOutcome::Approved);
});

it('will not take a decision with no outcome or no reason', function (): void {
    $reference = Scenario::review();

    Livewire::test(ViewExpression::class, ['record' => $reference])
        ->callAction('decide', ['outcome' => null, 'reason' => null])
        ->assertHasActionErrors(['outcome' => 'required', 'reason' => 'required']);
});

it('offers the closed enums entire, and nothing off them', function (): void {
    expect(array_keys(ViewExpression::outcomeOptions()))
        ->toBe(array_map(fn (ModerationOutcome $o): string => $o->value, ModerationOutcome::cases()))
        ->and(array_keys(ViewExpression::reasonOptions()))
        ->toBe(array_map(fn (ModerationReason $r): string => $r->value, ModerationReason::cases()));
});

/**
 * A free-text box beside a moderation reason sits beside a person's name by
 * construction, and that is where personal data gets typed. The reply body is
 * the one field in this panel whose whole purpose is free text.
 */
it('offers no free text anywhere except the reply body', function (): void {
    $reference = Scenario::review();

    $page = Livewire::test(ViewExpression::class, ['record' => $reference])->instance();

    $free = [];

    foreach ($page->getCachedHeaderActions() as $action) {
        foreach ($action->getSchema(Schema::make($page))?->getComponents() ?? [] as $component) {
            expect($component)->toBeInstanceOf(Component::class);

            if ($component instanceof TextInput || $component instanceof Textarea) {
                $free[] = $action->getName().'.'.$component->getName();
            }
        }
    }

    expect($free)->toBe(['reply.body']);
});

it('takes its reason from a select rather than a box somebody types in', function (): void {
    $reference = Scenario::review();

    $page = Livewire::test(ViewExpression::class, ['record' => $reference])->instance();
    $decide = $page->getAction('decide');

    $components = $decide?->getSchema(Schema::make($page))?->getComponents() ?? [];

    expect($decide?->getName())->toBe('decide')
        ->and($components)->toHaveCount(2);

    foreach ($components as $component) {
        expect($component)->toBeInstanceOf(Select::class);
    }
});

it('hides the decision action once an expression has been superseded', function (): void {
    $original = Scenario::review();
    Scenario::revise($original);

    Livewire::test(ViewExpression::class, ['record' => $original])
        ->assertActionHidden('decide');
});
