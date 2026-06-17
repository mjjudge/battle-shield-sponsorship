MEMORY.md
Battle Shield Sponsorship Plugin
This document records important decisions, assumptions, principles and historical context for the project.
Unlike BACKLOG.md, this document is not task-oriented.
Its purpose is to explain why decisions were made so future contributors understand the reasoning behind the implementation.

Project Purpose
The Battle Shield Sponsorship Plugin exists to reduce the administrative burden involved in securing sponsorship for Battle of Evesham shields.
Historically, sponsorship administration has involved:
    • contacting potential sponsors
    • collecting payments
    • chasing artwork
    • generating sponsorship patches
    • producing receipts
    • maintaining sponsor records
The plugin should automate as much of this process as practical whilst remaining simple for volunteers to operate.

Project Principles
Administrative Time Is The Primary Cost
The objective is not simply collecting sponsorship revenue.
The primary objective is reducing volunteer effort.
When making design decisions, prefer solutions that:
    • reduce manual administration
    • reduce sponsor chasing
    • reduce data re-entry
    • reduce patch production effort
even if they introduce slightly more technical complexity.

Sponsorship Is Complete After Payment
A shield becomes sponsored when payment succeeds.
Artwork completion is a separate concern.
A sponsor who never uploads a logo still owns the sponsorship.
At minimum:
    • sponsor display name
    • shield allocation
must be available for patch generation.

No Sponsor Accounts
Sponsors should never need WordPress accounts.
All sponsor self-service functionality should operate using secure tokenised links.
Reasons:
    • lower friction
    • fewer support requests
    • simpler GDPR management
    • easier mobile experience

Contacts Persist Across Campaigns
Sponsors are valuable long-term supporters.
Contacts should remain available across campaigns.
Examples:
    • Battle of Evesham
    • Medieval Market
    • Future fundraising events
The contact database is a strategic asset.

Physical Shields Persist Across Years
Shields are physical assets.
A shield may be sponsored by different organisations in different years.
The shield catalogue therefore exists independently of campaigns.

Artwork Deadline Philosophy
Sponsors should be encouraged to provide artwork before the cut-off date.
After the cut-off date:
    • sponsorship remains valid
    • online records remain editable only by administrators
    • no guarantee is made that changes will appear on printed materials
This balances sponsor flexibility with operational practicality.

Patch Design Decisions
Initial Layout
Version 1 uses a fixed layout.
Reasons:
    • simplest implementation
    • fastest route to production
    • predictable printing results
Future versions may introduce a visual editor.

PDF Is The Source Of Truth
Generated PDF files are the official output.
Administrators should not need external design software to produce patches.
The plugin should be capable of generating all required print artwork.

Batch Production
Patch generation should support whole-campaign production.
Reason:
Printing occurs as a batch activity after the artwork cut-off date.

Stripe Decisions
Stripe Is Payment Authority
Payment status should come from Stripe webhooks.
Never rely solely on browser redirects.
Reason:
Browser sessions fail.
Webhooks are reliable.

Refunds Must Be Logged
All refunds must create audit entries.
Reason:
Financial transparency.
Volunteer accountability.
Treasurer record keeping.

GDPR Decisions
Historical Sponsorship Records Remain
Where legally permissible:
    • sponsorship records remain
    • financial records remain
Personal information may be anonymised.
Reason:
Preserve event history.
Preserve financial audit trail.

Marketing Consent Is Explicit
Consent must never be inferred.
Consent must be:
    • actively given
    • timestamped
    • recorded

Definition Of Done
A task may only be moved to COMPLETE when all of the following are true.
Requirement 1
Implementation exists.
The functionality works.

Requirement 2
Acceptance criteria are satisfied.
Every acceptance criterion listed in BACKLOG.md has been completed.

Requirement 3
Tests pass.
Where tests exist:
    • unit tests pass
    • integration tests pass

Requirement 4
Documentation is updated.
Review:
    • BACKLOG.md
    • TECHNICAL_SPEC.md
    • README.md
    • ADMIN_GUIDE.md
Update where necessary.

Requirement 5
Project status is accurate.
The task has been moved from:
IN PROGRESS
to:
COMPLETE
in BACKLOG.md.

Requirement 6
No known unfinished work remains.
If additional work is discovered:
    • create a new backlog item
    • do not hide work inside an existing completed item

Requirement 7
The repository remains releasable.
The project should remain in a buildable state after the task is completed.

Repository Discipline
The Backlog Is Authoritative
BACKLOG.md is the official source of project status.
Never use memory, chat history or assumptions in place of the backlog.

Undocumented Decisions Are Temporary
If a significant design decision is made:
record it here.
Future contributors should understand:
    • what was decided
    • why it was decided
    • when it was decided

Future Volunteer Principle
Assume the next maintainer is a volunteer with no knowledge of the project.
Documentation should allow them to understand:
    • how the plugin works
    • why it works that way
    • how to safely extend it
