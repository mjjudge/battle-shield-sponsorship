AGENTS.md
Battle Shield Sponsorship Plugin
This document defines mandatory working practices for all humans and AI agents contributing to this repository.
These instructions apply to:
    • Claude Code
    • ChatGPT Codex
    • Gemini CLI
    • Aider
    • Cline
    • Roo Code
    • GitHub Copilot
    • Human contributors
When instructions conflict, this document takes precedence over agent defaults.

Project Goal
Create a standalone WordPress plugin that manages sponsorship of Battle of Evesham shields.
The plugin must:
    • Allow sponsors to sponsor one or more shields
    • Accept Stripe payments
    • Manage sponsor artwork and logos
    • Generate print-ready A4 sponsorship patches
    • Support GDPR compliance
    • Support future campaigns and events
    • Be maintainable by non-technical Battle of Evesham administrators

Core Principles
Simplicity First
Prefer the simplest solution that satisfies the requirement.
Avoid:
    • unnecessary abstractions
    • premature optimisation
    • over-engineering
    • excessive configuration

WordPress Native
Prefer native WordPress capabilities wherever practical.
Examples:
    • WP Cron
    • WP REST API
    • WP Media Library
    • WP Roles and Capabilities
    • wp_mail()
Avoid introducing external frameworks unless there is a clear benefit.

Service-Oriented Architecture
Controllers should be thin.
Business logic belongs in Services.
Preferred pattern:
Controller
    ↓
Service
    ↓
Repository / Database
Avoid placing business rules inside:
    • admin page classes
    • shortcode handlers
    • REST route handlers
    • template files

Source of Truth
The following documents are authoritative:
Document	Purpose
TECHNICAL_SPEC.md	Functional specification
BACKLOG.md	Project state and work tracking
MEMORY.md	Historical decisions
README.md	User-facing documentation
If documentation conflicts with code:
    1. Investigate.
    2. Determine intended behaviour.
    3. Update documentation and code so they agree.

Mandatory Workflow
Step 1 - Read First
Before making changes:
Read:
    • AGENTS.md
    • BACKLOG.md
    • MEMORY.md
    • Relevant section of TECHNICAL_SPEC.md
Do not begin implementation before understanding existing decisions.

Step 2 - Verify Backlog Item Exists
Before writing code:
Locate an existing backlog item.
If no backlog item exists:
Create one.
Then proceed.
No implementation may occur without a backlog item.

Step 3 - Update Status
When starting work:
Move item from:
NOT STARTED
to:
IN PROGRESS
Only one contributor should own a task at a time.

Step 4 - Implement
Complete implementation.
Include:
    • code
    • tests
    • documentation
where applicable.

Step 5 - Verify
Before marking complete:
    • code builds
    • tests pass
    • documentation updated
    • acceptance criteria satisfied

Step 6 - Close Task
Move item into:
COMPLETE
Update project statistics.
A task is not complete until BACKLOG.md has been updated.
Failure to update BACKLOG.md is considered a defect.

Backlog Rules
BACKLOG.md is the authoritative source of project status.
Every backlog item must exist in exactly one state:
    • NOT STARTED
    • IN PROGRESS
    • BLOCKED
    • COMPLETE
Never duplicate items between sections.

Status Definitions
NOT STARTED
Work has not begun.
No code written.

IN PROGRESS
Work is actively being undertaken.
Partial implementation exists.

BLOCKED
Progress cannot continue.
Reason must be documented.
Required format:
Blocked By:
- explanation

COMPLETE
Implementation finished.
Acceptance criteria met.
Tests passing.
Documentation updated.

Database Rules
All schema changes must be implemented using migrations.
Requirements:
    • idempotent
    • versioned
    • reversible where practical
Never modify production schema manually.
Never rely on activation-time SQL without migration tracking.

Security Rules
Treat all external input as untrusted.
Must validate:
    • POST requests
    • GET parameters
    • REST payloads
    • uploaded files
Use:
    • capability checks
    • sanitisation
    • escaping
    • nonces
    • prepared SQL statements
Never trust user-supplied filenames.

Stripe Rules
Stripe is the source of truth for payment state.
Webhook processing must be:
    • idempotent
    • replay-safe
    • logged
Never rely solely on browser redirects for payment confirmation.

Audit Logging
The following actions must be logged:
    • sponsorship creation
    • sponsorship modification
    • logo replacement
    • payment received
    • refund issued
    • GDPR anonymisation
    • campaign changes
    • shield availability changes
Logs must contain:
    • timestamp
    • actor
    • action
    • entity
    • outcome

Email Rules
All outbound emails must:
    • use templates
    • be logged
    • support HTML
    • support plain text fallback
Email content must be editable through admin settings where specified.

GDPR Rules
Personal data must be minimised.
Support:
    • anonymisation
    • consent tracking
    • marketing preferences
    • export requests
Historical sponsorship records should remain intact where legally permissible.

Documentation Rules
Any feature change requires review of:
    • TECHNICAL_SPEC.md
    • BACKLOG.md
    • README.md
    • ADMIN_GUIDE.md
Documentation should be updated as part of the same change.

Testing Rules
Business logic belongs in unit tests.
Integration tests should cover:
    • Stripe workflows
    • sponsorship lifecycle
    • reservation cleanup
    • patch generation
    • GDPR anonymisation
Bug fixes should include a test where practical.

Build Rules
Release ZIPs are generated only from:
plugin/
Output location:
dist/
Version format:
MAJOR.MINOR.PATCH
Examples:
0.1.0
0.2.0
1.0.0

Release Process
Before creating a release:
    • backlog updated
    • documentation updated
    • tests passing
    • version updated
    • changelog recorded
Release ZIP created in:
dist/
Example:
battle-shield-sponsorship-0.1.0.zip

Decision Recording
Important decisions must be recorded in MEMORY.md.
Examples:
    • architecture decisions
    • workflow decisions
    • Stripe design decisions
    • patch generation decisions
    • GDPR decisions
Future contributors should be able to understand why decisions were made.

Golden Rule
The project state must be understandable by someone opening the repository for the first time.
If a change is not reflected in:
    • BACKLOG.md
    • TECHNICAL_SPEC.md
    • MEMORY.md
then the repository is incomplete.
