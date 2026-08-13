# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this package
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-13

### Added

- `ReviewsAndRatingsPlugin`, attached to one panel at a time by the host. The
  package registers nothing globally.
- `ExpressionResource` — the moderation queue, filtered by derived display
  state, ordered by screening urgency, with edited-after-approval flagged on
  every row.
- `ViewExpression` — read-only expression detail carrying the whole moderation
  history, the screening signals, the custody chain and the vote tallies, with
  a decision action and a merchant reply action.
- `ModerationHistoryRelationManager` — every decision anybody made, in order,
  with every unpublished ability refused by name.
- `FlagResource` — the reader report queue. It carries no reporter reference.
- `RatingCoverageWidget` — the displayed and recorded aggregates side by side,
  each naming its population.
- `ExpressionPolicy`, `FlagPolicy` and `ModerationDecisionPolicy`, each
  answering every ability by name.

### Notes

- Reads go through the domain package's queries and writes through its actions.
  Nothing in this panel derives a display state, a supersession or an aggregate
  of its own.
- No author reference and no display name is searchable, filterable, or present
  in a table row.
- No incrementing row id appears in any URL.
