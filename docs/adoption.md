# Adopting this package

## 1. Composer

The domain package is not on Packagist, so the host adds the VCS repository
entry itself. Composer honours `repositories` **only from the root manifest** —
this package declares one for its own CI, and that does nothing for a consumer.

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-reviews-and-ratings" },
        { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-reviews-and-ratings-filament" }
    ]
}
```

```bash
composer require liberusoftware/ecommerce-reviews-and-ratings-filament
```

Installing boots nothing. The package ships no `extra.laravel.providers`; the
host's module manager registers
`Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsFilamentServiceProvider`
only when `ecommerce-reviews-and-ratings-filament` is named in `MODULES_ENABLED`,
and the domain module's provider likewise. Enable both.

## 2. Attach the plugin to one panel

```php
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('staff')
        ->plugin(
            ReviewsAndRatingsPlugin::make()
                ->tenantUsing(fn (): string => Filament::getTenant()->getKey())
                ->replyingAs(fn (): string => 'Acme Support'),
        );
}
```

`tenantUsing()` defaults to Filament's own panel tenant, and `replyingAs()` to
the authenticated user's `name`. Both **refuse rather than default** when they
cannot answer:

- an unresolved tenant throws, because `where('tenant_id', null)` compiles to
  `is null` and would list exactly the orphan rows no business owns;
- an unresolved reply name throws, because the domain refuses an anonymous
  merchant reply and this package holds no identity of its own.

Attach it to the panel your moderators use, and no other.

## 3. Bind the seams

Both live in the domain package, and the distinction between them is the point:
**an unbound optional seam is safe when its absence removes a claim, and unsafe
when its absence removes a control.**

### `ScreensContent` — bind this

```php
$this->app->bind(ScreensContent::class, YourScreener::class);
```

Unbound is a **configuration error, not a deployment**. Any write carrying a body
— including every merchant reply written from this panel — refuses with 503, and
the panel surfaces that refusal as a danger notification saying so. A module that
accepted free text with no screening and no moderator notification would be
publishing unscreened speech on a merchant's page.

An implementation never rejects. It says how urgently a person should look, and
the panel shows that as the screening priority and its signals.

### `ConfirmsPurchase` — bind it or do not

```php
$this->app->bind(ConfirmsPurchase::class, YourOrderLookup::class);
```

Unbound is a **valid deployment**: a merchant with no order history wired up, or
one that does not want the badge. Everything becomes `unknown`, and the panel
renders nothing at all for it — not a negative. Do not bind a stub that answers
`false`: that would make the panel say "we checked, they did not buy it" about
every review in the store.

## 4. Migrating off the host's own screens

The host's `ProductReviewResource` and its `approve()` / `reject()` HTTP routes
are the surface this replaces. Two things about the host are worth knowing before
you switch anything off:

- **`approve()` is gated by a role check beside a policy that is never
  consulted.** A permission grant that works in the panel does nothing over HTTP,
  and vice versa. This package has one authorization path: policies.
- **`is_verified_purchase` is `false` on every row that has ever existed.**
  Nothing in the host assigns it. Do not carry those values across as
  `unverified` — they are `unknown`, and the difference is a claim shown to
  shoppers.

Data migration is the domain package's business, not this one's. This package
reads whatever is in the `reviews_*` tables.

## 5. What is not in 0.1.0

- **No bulk moderation.** Every decision names an actor and carries a reason;
  deciding fifty things at once with one reason is how a reason stops meaning
  anything. If it arrives, it arrives per-reason.
- **No pagination on the report queue.** It reads one bounded page of fifty.
- **No panel-side erasure or export.** `EraseAuthor` and `AuthorExport` are
  domain actions and belong on a data-protection surface, not a moderation one.
- **No product names.** This module holds an opaque product reference and never
  dereferences it. A host that wants names renders them itself.
