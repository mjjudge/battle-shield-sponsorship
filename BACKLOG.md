BACKLOG.md
Battle Shield Sponsorship Plugin
Project Status
Status	Count
Not Started	0
In Progress	0
Blocked	0
Complete	0

Backlog Management Rules
These rules are mandatory.
Rule 1 - No Untracked Work
No code, documentation, database change, migration, UI component, API endpoint, email template, shortcode, or configuration change may be implemented unless it exists as a backlog item.
If work is discovered during implementation:
    1. Stop.
    2. Add a backlog item.
    3. Continue implementation.

Rule 2 - Status Must Be Accurate
Every backlog item must be in exactly one state:
    • NOT STARTED
    • IN PROGRESS
    • BLOCKED
    • COMPLETE
Items must never appear in multiple sections.

Rule 3 - Completion Requirements
A task may only be moved to COMPLETE when:
    • Code is written
    • Tests pass
    • Documentation is updated
    • Relevant backlog child items are complete

Rule 4 - New Features
Every new feature requires:
    • Functional implementation task
    • Test task
    • Documentation task
unless explicitly agreed otherwise.

Rule 5 - Commit Discipline
Before creating a release ZIP:
    • BACKLOG.md must be updated
    • TECHNICAL_SPEC.md must reflect reality
    • README.md must reflect reality

Rule 6 - Version Discipline
Every release must:
    • Move completed items to COMPLETE
    • Record version number
    • Record release date
    • Record key features delivered

Release History
Unreleased
Current development version.

NOT STARTED
Foundation
BSS-001 Create repository structure
Priority: High
Acceptance Criteria:
    • Repository created
    • Folder structure matches technical specification
    • Dist folder present
    • Build scripts present

BSS-002 Plugin bootstrap
Priority: High
Acceptance Criteria:
    • Plugin activates
    • Plugin deactivates
    • Version constant defined
    • Installer executes

BSS-003 Database migration framework
Priority: High
Acceptance Criteria:
    • Migration runner created
    • Idempotent migrations supported
    • Migration history tracked

Database
BSS-010 Create campaigns table
Priority: High
Acceptance Criteria:
    • Campaign CRUD supported
    • Active campaign flag supported

BSS-011 Create shields table
Priority: High
Acceptance Criteria:
    • Shield records stored
    • Historical data preserved

BSS-012 Create contacts table
Priority: High
Acceptance Criteria:
    • Sponsor records stored
    • GDPR fields included

BSS-013 Create sponsorship table
Priority: High
Acceptance Criteria:
    • Purchases stored
    • Payment metadata stored

BSS-014 Create sponsorship items table
Priority: High
Acceptance Criteria:
    • Multiple shields per purchase supported

BSS-015 Create reservations table
Priority: High
Acceptance Criteria:
    • Reservation expiry supported

BSS-016 Create upload tokens table
Priority: High
Acceptance Criteria:
    • Secure token storage supported

BSS-017 Create email log table
Priority: Medium
Acceptance Criteria:
    • All outgoing email recorded

BSS-018 Create audit log table
Priority: High
Acceptance Criteria:
    • Sensitive changes logged

Shield Management
BSS-020 Shield administration page
Priority: High
Acceptance Criteria:
    • Add shield
    • Edit shield
    • Archive shield
    • Upload shield image

BSS-021 Shield availability management
Priority: High
Acceptance Criteria:
    • Available
    • Reserved
    • Sponsored
    • Damaged
States supported

BSS-022 Shield search and filtering
Priority: Medium
Acceptance Criteria:
    • Search by name
    • Filter by side
    • Filter by availability

Campaign Management
BSS-030 Campaign administration page
Priority: High
Acceptance Criteria:
    • Create campaign
    • Edit campaign
    • Archive campaign

BSS-031 Artwork cut-off management
Priority: High
Acceptance Criteria:
    • Campaign cut-off date stored
    • Final reminder date calculated

Public Shop
BSS-040 Shield catalogue shortcode
Priority: High
Acceptance Criteria:
    • Public display works
    • Responsive layout

BSS-041 Multi-shield selection
Priority: High
Acceptance Criteria:
    • Multiple shields selectable

BSS-042 Reservation engine
Priority: High
Acceptance Criteria:
    • 30-minute reservation
    • Expiry handling

BSS-043 Reservation cleanup job
Priority: High
Acceptance Criteria:
    • Expired reservations released

Stripe
BSS-050 Stripe integration
Priority: High
Acceptance Criteria:
    • Test mode
    • Live mode

BSS-051 Stripe checkout
Priority: High
Acceptance Criteria:
    • Payment succeeds
    • Payment fails cleanly

BSS-052 Stripe webhooks
Priority: High
Acceptance Criteria:
    • Payment confirmation received
    • Duplicate webhook protection

BSS-053 Stripe refunds
Priority: High
Acceptance Criteria:
    • Admin refund available
    • Refund logged

Sponsorship Workflow
BSS-060 Purchase workflow
Priority: High
Acceptance Criteria:
    • Sponsor details collected
    • Payment linked

BSS-061 Upload token generation
Priority: High
Acceptance Criteria:
    • Secure edit URL generated

BSS-062 Sponsor edit page
Priority: High
Acceptance Criteria:
    • Logo upload
    • Text editing
    • Contact updates

BSS-063 Artwork completion detection
Priority: High
Acceptance Criteria:
    • Complete status
    • Incomplete status
Calculated correctly

Email System
BSS-070 Email framework
Priority: High
Acceptance Criteria:
    • HTML email support
    • Template support

BSS-071 Purchase confirmation email
Priority: High
Acceptance Criteria:
    • Sent after successful payment

BSS-072 Treasurer notification email
Priority: High
Acceptance Criteria:
    • Invoice/receipt delivered

BSS-073 Reminder email engine
Priority: High
Acceptance Criteria:
    • Reminder schedule configurable

BSS-074 Final deadline reminder
Priority: High
Acceptance Criteria:
    • Sent day before cut-off

BSS-075 Refund confirmation email
Priority: Medium
Acceptance Criteria:
    • Sent after refund

Patch Generation
BSS-080 PDF generation framework
Priority: High
Acceptance Criteria:
    • A4 PDF generated

BSS-081 Fixed patch template
Priority: High
Acceptance Criteria:
    • Matches approved design

BSS-082 Logo rendering
Priority: High
Acceptance Criteria:
    • Uploaded logo displayed

BSS-083 Dynamic text scaling
Priority: Medium
Acceptance Criteria:
    • Large text fits layout

BSS-084 Batch patch generation
Priority: High
Acceptance Criteria:
    • Entire campaign downloadable

BSS-085 ZIP download generation
Priority: High
Acceptance Criteria:
    • Single ZIP produced

Manual Sponsorships
BSS-090 Manual sponsorship page
Priority: High
Acceptance Criteria:
    • Cash
    • Card
    • Bank transfer
    • Other
Supported

BSS-091 Admin editing of sponsorship data
Priority: High
Acceptance Criteria:
    • Text editable
    • Logo replaceable

GDPR
BSS-100 Marketing consent management
Priority: High
Acceptance Criteria:
    • Consent stored
    • Exportable

BSS-101 Contact export
Priority: High
Acceptance Criteria:
    • CSV export

BSS-102 GDPR anonymisation
Priority: High
Acceptance Criteria:
    • Contact anonymised
    • Sponsorship retained

Reporting
BSS-110 Sponsorship reports
Priority: Medium
Acceptance Criteria:
    • Revenue totals
    • Shield counts

BSS-111 Contact reports
Priority: Medium
Acceptance Criteria:
    • Contact exports
    • Marketing exports

Documentation
BSS-120 Create ADMIN_GUIDE.md
Priority: Medium
Acceptance Criteria:
    • Installation guide
    • Operational guide

BSS-121 Create README.md
Priority: Medium
Acceptance Criteria:
    • Installation documented

IN PROGRESS
None

BLOCKED
None

COMPLETE
None