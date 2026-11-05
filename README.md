# Reviews and Ratings — Filament

The staff moderation surface for
[`liberusoftware/ecommerce-reviews-and-ratings`](https://github.com/liberusoftware/module-ecommerce-reviews-and-ratings).

**The moderation queue is the module.** Everything else here exists to serve it.

The application this was extracted from has one moderation control: a boolean
toggle on a review edit form. Approving, retracting and re-approving all leave
the same row state, nobody can say who did any of it, and a review edited after
approval looks exactly like one that was never touched. This package is what a
moderator actually needs instead.

## What it gives a panel

| Surface | What it is for |
| --- | --- |
| **Moderation queue** | Expressions filtered by derived display state, urgent screening first, **edited-after-approval flagged on every row** |
| **Expression detail** | Read-only: what was said, who said it, what the machine found, the whole custody chain, and every decision anybody made |
| **Decision action** | An outcome and a reason, both from closed enums. No free text. |
| **Merchant reply** | A reply written through the domain — screened, moderated, and signed with the merchant |
| **Report queue** | What readers have flagged, and never who flagged it |
| **Coverage widget** | The displayed and the recorded aggregates side by side, each naming its population |

## Installing

```bash
composer require liberusoftware/ecommerce-reviews-and-ratings-filament
```

The domain package is not on Packagist, so the host adds a VCS repository entry
for it as well. See [docs/adoption.md](docs/adoption.md) for the whole of it.

Then attach the plugin to the one panel that should carry a moderation queue:

```php
use Liberu\Ecommerce\ReviewsAndRatings\Filament\ReviewsAndRatingsPlugin;

$panel->plugin(
    ReviewsAndRatingsPlugin::make()
        ->tenantUsing(fn (): string => Filament::getTenant()->getKey())
        ->replyingAs(fn (): string => config('app.name')),
);
```

Installing this package registers no panel, no resource and no page. A package
that pushed a moderation queue onto every panel would put shoppers' speech in
front of whoever the application happened to let into any of them.

## What it will not do

- It will not **edit an expression**. A review is a historical fact; an edit is
  a new expression that supersedes the old one, and only its author makes one.
- It will not **search or filter by an author**. Search terms and filter state
  both persist into the query string, and an author reference or display name is
  the sensitive thing in this module. Neither is even present in a table row.
- It will not **put a row id in a URL**. Records bind by their opaque reference.
- It will not **dereference a product**. It shows the product reference; a host
  that wants a name wires one in.
- It will not **hold an opinion**. Every published figure comes from the domain's
  own queries, and the panel names the population each one summarises.

## Documentation

- [docs/domain.md](docs/domain.md) — what each surface shows, and why
- [docs/adoption.md](docs/adoption.md) — installing, attaching, seams, migrating off the host's own screens
- [docs/runbook.md](docs/runbook.md) — operating it, and what each refusal means

## Licence

MIT. See [LICENSE.md](LICENSE.md).

<!-- module-presentation-guide:begin -->
## Presentation architecture and project hosts

- Capability/source key: `planned:ecommerce:reviews-and-ratings`.
- Actual Composer identity: `liberusoftware/ecommerce-reviews-and-ratings-filament`.
- [Filament capability plan](https://github.com/liberusoftware/documentation/blob/main/projects/ecommerce/filament/reviews-and-ratings.md) · [Filament standard](https://github.com/liberusoftware/documentation/blob/main/standards/FILAMENT.md) · [Module architecture](https://github.com/liberusoftware/documentation/blob/main/architecture/MODULES.md).

The application owns panel composition, routing, session integration and deployment. This module owns its focused presentation and delegates domain behavior to reviewed public owner contracts. Keep host App aliases, duplicate records and client-only authorization outside reusable package boundaries.

Documented consumer project families:

- [Ecommerce](https://github.com/liberusoftware/project-ecommerce-laravel-filament): Catalogs, checkout, orders and fulfillment.

Other web, mobile and desktop presentations are listed in the [project catalog](https://github.com/liberusoftware/documentation/blob/main/projects/REPOSITORIES.md). Those skeletons provide starting structures; links do not establish feature parity, compatible releases or completed device/API integrations. Independent presentation packages keep their own Composer, npm or pub metadata.
<!-- module-presentation-guide:end -->
