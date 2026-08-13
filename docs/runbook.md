# Runbook

## Every refusal this panel can show, and what it means

Domain refusals reach the moderator as themselves, with the status an HTTP
adapter would answer with. The panel never swallows one — a surface that did
would report a write it never made.

| Notification | Status | What happened | What to do |
| --- | --- | --- | --- |
| `Refused (503)` — no `ScreensContent` bound | 503 | Automated screening is not configured. Nothing carrying free text can be written | Bind `ScreensContent`. This is a configuration error, not a deployment |
| `Refused (503)` — screening failed | 503 | The screener threw | Check the screener. Reviews are not being accepted anywhere while this lasts |
| `Refused (404)` | 404 | The expression is not in this business | Check the tenant the panel is scoped to |
| `Refused (409)` — superseded | 409 | Somebody edited it while you had it open | Reload; the successor is a separate row with its own decisions |
| `Refused (409)` — duplicate | 409 | A live reply already exists for that expression | Supersede the existing reply instead |
| `Refused (410)` — redacted | 410 | The author has been erased | Nothing to reply to. The moderation history is intact and still readable |
| `Refused (422)` | 422 | The reply body is empty, whitespace, or over 5000 characters | Rewrite it |
| `could not resolve a tenant for this panel` | — | The plugin has no tenant resolver and Filament has no panel tenant | Give the plugin `->tenantUsing(...)` |
| `could not resolve a name to sign a merchant reply` | — | No `replyingAs()` and the authenticated user has no name | Give the plugin `->replyingAs(...)` |
| `cannot record a decision with no authenticated actor` | — | The session went away mid-decision | Sign in again. No decision is ever recorded without an actor |

## Daily operation

**Open the moderation queue.** It defaults to `Pending` — expressions nobody has
decided about — ordered urgent screening first, then oldest first.

**Watch the `Escalated` filter.** Automated screening never rejects; when it
finds something serious it appends an escalation decision attributed to
`system:screening` and the expression lands in that state. It is waiting for a
person, and it is not displayed while it waits.

**Treat `Edited after approval` as the priority it is.** A red triangle on a row
means this expression supersedes one that had already been approved: somebody
changed what they said after you agreed to display it. The detail page names the
expression it superseded, and that older row keeps its own decisions.

**Reports do not hide anything.** A flagged expression stays exactly as displayed
as it was. The report queue is a routing device; the only thing that changes what
a shopper sees is a decision on the expression itself.

## Reading a decision

Every decision names its actor and its reason, and none of them is ever
overwritten. To answer "why is this displayed", open the expression and read the
last row of the moderation history — and to answer "who let this through in
March", read the row from March. Both questions are answerable, always, because
nothing here is a flag.

## Reading the coverage widget

`Displayed` counts only expressions whose latest decision is `approved`.
`Recorded` counts everything, whatever anybody decided. `Not displayed` is the
gap — what moderation has cost the number on the product page.

An erased author's expression **stays in both figures**. Erasure removes a person
from a page; it does not retroactively move a figure that has already been
published.

If `Displayed` and `Recorded` disagree by more than you expect, the withheld and
rejected filters on the queue will tell you which expressions account for it.

## Things this panel deliberately cannot do

- Edit a review. If a body has to go, that is erasure, and it is a
  data-protection action in the domain package, not a moderation one.
- Delete a moderation decision. The audit trail is append-only.
- Search or filter by an author. Search terms and filter state both persist into
  the query string; the queue carries no author field at all.
- Clear a flag. A report is a fact that a reader reported something.
