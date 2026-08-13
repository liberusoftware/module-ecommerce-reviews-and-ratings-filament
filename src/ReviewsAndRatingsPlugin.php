<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Filament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Expressions\ExpressionResource;
use Liberu\Ecommerce\ReviewsAndRatings\Filament\Resources\Flags\FlagResource;
use RuntimeException;

/**
 * The one thing a host attaches, to the one panel it wants this surface on.
 *
 * Nothing here registers globally: a package that pushed resources onto every
 * panel would put a moderation queue in front of whoever the application let
 * into any of them.
 *
 * It also carries the two facts this module cannot derive. The tenant, because
 * there is no tenancy contract in the fleet and every domain query is scoped by
 * one; and the name a merchant reply is signed with, because the domain refuses
 * an anonymous reply and this package holds no identity of its own.
 */
final class ReviewsAndRatingsPlugin implements Plugin
{
    public const string ID = 'ecommerce-reviews-and-ratings';

    private ?Closure $tenantResolver = null;

    private ?Closure $replyNameResolver = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * The plugin attached to the current panel.
     *
     * Resolved through the panel rather than the container so two panels can
     * carry two configurations — a staff panel scoped to one tenant and a
     * support panel scoped to another are the same package, twice.
     */
    public static function current(): self
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        $plugin = $panel?->hasPlugin(self::ID) === true ? $panel->getPlugin(self::ID) : null;

        if (! $plugin instanceof self) {
            throw new RuntimeException('The reviews and ratings plugin is not attached to the current Filament panel.');
        }

        return $plugin;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            ExpressionResource::class,
            FlagResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    /** How this panel decides which business it is looking at. */
    public function tenantUsing(Closure $resolver): self
    {
        $this->tenantResolver = $resolver;

        return $this;
    }

    /** The display name a merchant reply written from this panel is signed with. */
    public function replyingAs(Closure $resolver): self
    {
        $this->replyNameResolver = $resolver;

        return $this;
    }

    /**
     * The tenant every query on this panel is scoped to.
     *
     * It refuses rather than returning null, because `where('tenant_id', null)`
     * compiles to `is null` and would list exactly the orphan rows no tenant
     * owns. A panel this module cannot scope is a panel it must not read.
     */
    public static function tenant(): string
    {
        $plugin = self::current();

        $tenant = $plugin->tenantResolver instanceof Closure
            ? ($plugin->tenantResolver)()
            : self::panelTenant();

        if (! is_string($tenant) || trim($tenant) === '') {
            throw new RuntimeException(
                'Reviews and ratings could not resolve a tenant for this panel. '
                .'Give the plugin one with ->tenantUsing(fn (): string => ...).',
            );
        }

        return $tenant;
    }

    /** Who is deciding. Every moderation decision names an actor, always. */
    public static function actor(): string
    {
        $id = Filament::auth()->id();

        if ($id === null || $id === '') {
            throw new RuntimeException('Reviews and ratings cannot record a decision with no authenticated actor.');
        }

        return (string) $id;
    }

    /** The name a reply carries. Never the shopper's, never blank. */
    public static function replyName(): string
    {
        $plugin = self::current();

        $name = $plugin->replyNameResolver instanceof Closure
            ? ($plugin->replyNameResolver)()
            : self::authenticatedName();

        if (! is_string($name) || trim($name) === '') {
            throw new RuntimeException(
                'Reviews and ratings could not resolve a name to sign a merchant reply with. '
                .'Give the plugin one with ->replyingAs(fn (): string => ...).',
            );
        }

        return trim($name);
    }

    private static function panelTenant(): ?string
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Model ? (string) $tenant->getKey() : null;
    }

    private static function authenticatedName(): ?string
    {
        $user = Filament::auth()->user();

        if (! $user instanceof Model) {
            return null;
        }

        $name = $user->getAttribute('name');

        return is_string($name) ? $name : null;
    }
}
