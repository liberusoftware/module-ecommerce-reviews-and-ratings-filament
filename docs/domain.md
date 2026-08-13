# What each surface shows, and why

This package holds no domain of its own. It reads through
`liberusoftware/ecommerce-reviews-and-ratings`'s queries and writes through its
actions, and every rule below is that package's rule, made visible.

## The three things called "a review"

The domain keeps three concepts apart that the host stores in one mutable row,
and the panel is shaped by that separation.

1. **An expression** — one person, one product, one moment. Immutable and
   append-only. An edit is a *new* expression that supersedes the old one.
2. **A moderation decision** — a separate record with an actor, a timestamp, an
   outcome and a reason from a closed enum. The display state is derived from the
   latest decision; there is no column anybody can flip.
3. **An aggregate** — a number derived from (1) filtered by (2), meaningless
   unless it names the population it summarises.

## The moderation queue

`ExpressionResource`, backed by the domain's `ModerationQueue` query.

**Filtered by state, not sorted by it.** "No decision first" is an ordering over
a nullable subquery, and Postgres, MySQL and SQLite do not agree about where
nulls sort. The queue defaults to `pending` — the one a moderator opens — and the
ordering the domain does provide is screening urgency, then age.

Every row carries:

| Column | Why it is there |
| --- | --- |
| Said at | `occurred_at`, distinct from when the row was recorded — an imported review happened before it existed here |
| Kind | A merchant reply is an expression too, and it is moderated like any other |
| Product reference + score | Opaque; this module never dereferences a product. A score always carries its scale |
| What was said | The body, truncated. Not searchable |
| Display state | Derived from the latest decision by the domain, never recomputed here |
| Screening | The priority a machine assigned, and the signals it raised. A machine queues; it never decides |
| **Edited after approval** | The single most important thing a moderator can be told: they approved something, and then it changed |
| Reports | How many readers flagged it |
| Purchase | `verified`, `unverified` — or nothing at all |

**What is not there:** the author reference and the display name. Not hidden —
absent from the row the table is built from, so there is no field for the next
person to make searchable.

## The expression detail

`ViewExpression`, backed by `StaffExpression`. Read-only, and it renders four
sections: what was said, who said it, custody, and what the machine found.

The **moderation history** is a relation manager below it, showing every decision
in order with the actor who took it. The host stores this as a boolean, so a
review approved, retracted and re-approved is indistinguishable from one approved
once. This is the information the host threw away.

**Verification is tri-state and stays tri-state.** A badge for `verified`, a
negative for `unverified`, and **nothing at all** for `unknown` — because the
absence of a badge is itself a claim, and "not a verified purchase" said when the
module never asked is a lie of omission.

## The decision action

An outcome and a reason, both `<select>` over the domain's closed enums, and
there is no third field. A moderation reason sits beside a person's name by
construction, and a free-text box beside an event log is where personal data gets
typed. The decision is **appended** — the display state is a fold, so retracting
an approval leaves both rows and both actors.

## The merchant reply

A reply is an expression by a different kind of author: screened, moderated,
pending until somebody decides otherwise, and never anonymous. It is signed with
the name the panel is configured with, never the shopper's.

The body is the one free-text field in this package, and its limit is
`SubmissionRules::MAX_BODY_LENGTH`, read from the domain. The host has three
disagreeing answers for the same column; this panel does not invent a fourth.

## The report queue

`FlagResource`, backed by `FlagQueue`. It shows when, why, and which expression.

**It never shows who reported it.** A reporter reference is an author reference,
and a queue that displayed one would be a page where a moderator learns which
shopper reported which neighbour. Reporting hides nothing and this queue decides
nothing: it routes a report to a person, and what that person decides is appended
to the expression, where it names them.

## The coverage widget

`RatingCoverageWidget`, on the expression detail, showing the product's
`DisplayedRatingAggregate` and `RecordedRatingTotal` side by side.

Each names its population. "4.3 stars" is not a fact; "4.3 of 5 — 374 over 87,
population: displayed" is. The numerator and the denominator are both shown so
the figure can be re-derived, which a rounded average cannot support. The gap
between the two figures is what withholding has cost the published number, and
this is the only place a moderator can see it.

The mean is rounded here, and only here, because a panel is a consumer.

## Authorization

Three domain models reach the panel, so three policies are registered, and each
answers **every** ability by name.

A model with no policy is exposed rather than safe — Laravel's unanswered gate
case is permissive — and Filament's is worse: `get_authorization_response()`
returns *allow* when a policy exists but lacks the method being asked about. A
partial policy is the same hazard as no policy and harder to see.

`associate`, `dissociate` and `dissociateAny` are live on a `hasMany` relation
manager and default open. They are refused by name on the policy *and* on the
relation manager, and every one of them is asserted.

Nothing in this package publishes `create`, `update` or `delete` on any model.
Every write goes through a domain action, which appends.
