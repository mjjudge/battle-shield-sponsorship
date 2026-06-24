BACKLOG.md
Battle Shield Sponsorship Plugin
Project Status
Status	Count
Not Started	1
In Progress	0
Blocked	0
Complete	74

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
None

IN PROGRESS
None

BLOCKED
None

COMPLETE

Post-Test Fixes (v0.1.x)
BSS-130 Fix manual sponsorship: £0 amount and no linked shields
Priority: Critical
Completed: 2026-06-23
Acceptance Criteria:
    • ManualSponsorshipService reads per-shield prices from the shields array the page sends ✓
    • total_amount calculated correctly from individual shield prices ✓
    • Shield items linked to the sponsorship ✓
    • Shields gain 'sponsored' status after creation ✓
    • Amount shows correctly in Sponsorships list ✓

BSS-131 Rename "Campaign" to "Event" throughout admin UI
Priority: High
Completed: 2026-06-23
Acceptance Criteria:
    • Menu shows "Events" / "Event Editor" ✓
    • All page titles, headings, buttons, notices use "Event" not "Campaign" ✓

BSS-132 Split event_date into event_start_date / event_end_date
Priority: High
Completed: 2026-06-23
Acceptance Criteria:
    • DB migration 0.1.0 adds event_start_date and event_end_date columns ✓
    • Event Editor shows "Event start" and "Event end" date fields ✓
    • Events list shows date range ✓

BSS-133 Rename "Sales open date" to "Sponsorship opens"
Priority: High
Completed: 2026-06-23
Acceptance Criteria:
    • Label in Event Editor reads "Sponsorship opens" ✓

BSS-134 Default price per shield £100.00, no spinner on price input
Priority: Medium
Completed: 2026-06-23
Acceptance Criteria:
    • New event form pre-fills default price with 100.00 ✓
    • Price input has no up/down incrementor arrows ✓

BSS-135 Rename shield sides to Royals / Rebels, remove Other
Priority: High
Completed: 2026-06-23
Acceptance Criteria:
    • DB migration 0.1.1 updates existing rows ✓
    • Shield editor dropdown shows Royals / Rebels only ✓
    • Shield list filter shows Royals / Rebels only ✓
    • Manual sponsorship page shows Royals / Rebels labels ✓

BSS-136 Shield image portrait rectangle display
Priority: Medium
Completed: 2026-06-23
Acceptance Criteria:
    • Shield image placeholder is portrait-oriented (150×225 px viewport) ✓
    • Image is not cropped at top/bottom in the editor preview ✓

BSS-137 Add "Test No Stripe" payment mode
Priority: High
Completed: 2026-06-23
Acceptance Criteria:
    • Settings Mode dropdown has three options: Test No Stripe / Test Stripe / Live ✓
    • In Test No Stripe mode checkout bypasses Stripe entirely ✓
    • Success page auto-confirms payment when mode is Test No Stripe ✓

BSS-138 Add address fields and rename Display name to Company name
Priority: Medium
Completed: 2026-06-23
Acceptance Criteria:
    • DB migration 0.1.2 adds company_name column and address columns ✓
    • Contact editor shows Company name field ✓
    • Address fields: line 1, line 2, city, county, postcode, country (all optional) ✓

BSS-139 Update Help page for new terminology and page requirements
Priority: Medium
Completed: 2026-06-23
Acceptance Criteria:
    • Help page uses "Events" not "Campaigns" ✓
    • Help page references Royals / Rebels ✓
    • Help page lists all four WordPress pages required with current slugs ✓
    • Help page explains all three payment modes ✓

Shield Data Import (v0.1.x)
BSS-140 Add birth/death fields to shields table and editor
Priority: High
Completed: 2026-06-23
Acceptance Criteria:
    • DB migration adds birth_date, death_date columns ✓
    • Shield editor shows Born and Died fields ✓
    • ShieldService persists and updates the new fields ✓

BSS-141 Build shields JSON import tool
Priority: High
Completed: 2026-06-23
Acceptance Criteria:
    • Admin page reads shields_import.json ✓
    • Creates shield records for all 47 entries (skips existing name+side matches) ✓
    • Maps side "Royal" → royals, "Rebel" → rebels ✓
    • Cleans "We are trying to find…" bio/date placeholders to blank ✓
    • Sideloads each shield's image into WP media library ✓
    • Links attachment ID to shield record ✓
    • Reports created/skipped/image-imported counts ✓

BSS-142 Create enhanced shields_import.json with explicit image mappings
Priority: High
Completed: 2026-06-23
Acceptance Criteria:
    • All 47 shields have correct side mapping ✓
    • image_file field contains relative path (royals/ or rebels/ prefix) or null ✓
    • Prince Edward → Shield_Edward_copy image, not Edward_I_of_England ✓
    • Adam de Everingham appears as two entries (Royal + Rebel) ✓
    • Geoffrey de Geneville correctly maps to Geoffrey_de_Granville image file ✓

BSS-143 Fix Events list empty after creating a new Event (migration version stamp bug)
Priority: Critical
Completed: 2026-06-24
Acceptance Criteria:
    • BSS_VERSION bumped from 0.1.0 to 0.1.5 so migrator re-runs ✓
    • Migration keys for AddEventDatesToCampaigns through SetDefaultShieldPrice shifted to 0.1.1–0.1.5 ✓
    • event_start_date and event_end_date columns present in bss_campaigns ✓
    • New events appear in the Events list immediately after creation ✓

BSS-144 Fix "No campaigns exist" text in Add Manual Sponsorship page
Priority: Medium
Completed: 2026-06-24
Acceptance Criteria:
    • Empty-state message reads "No events exist. Please create an event first." ✓
    • Event dropdown label updated from "Campaign" to "Event" ✓

BSS-145 Public shield catalogue — show biography, born, and died
Priority: Medium
Status: NOT STARTED
Acceptance Criteria:
    • Biography (description) displayed on the public-facing shield detail/catalogue page
    • Born and Died dates shown when present
    • Matches the existing shield catalogue design

BSS-146 Manual sponsorships enter the same artwork reminder workflow as online sponsorships
Priority: High
Completed: 2026-06-24
Acceptance Criteria:
    • artwork_status reset to 'incomplete' after manual creation regardless of admin-set display_name ✓
    • Upload token created before mark_paid() so the confirmation email includes the upload link ✓
    • SponsorConfirmationNotifier sends sponsorship_confirmation email on bss_payment_confirmed ✓
    • SponsorConfirmationNotifier creates an upload token for online Stripe sponsorships too (fixes latent gap) ✓
    • Daily cron picks up manual sponsorships with artwork_status='incomplete' and sends reminders ✓
    • Artwork marked complete only when the sponsor submits the upload form ✓

BSS-147 Artwork status requires both display name and logo (or explicit logo waiver)
Priority: High
Completed: 2026-06-24
Acceptance Criteria:
    • refresh_artwork_status() marks complete only when display_name non-empty AND (logo present OR logo_not_needed set) ✓
    • logo_not_needed boolean column added to bss_sponsorships via migration 0.1.6 ✓
    • Admin can tick "No logo required" on the sponsorship view page ✓
    • Sponsor can tick "I do not plan to upload a logo or image for the back of the shield" on upload page ✓
    • Ticking either waiver re-evaluates artwork_status immediately ✓
    • Sponsor upload page shows a status summary of what is still outstanding ✓
    • Admin view shows note when sponsor has waived the logo ✓

BSS-148 Artwork reminder emails state what is specifically outstanding
Priority: High
Completed: 2026-06-24
Acceptance Criteria:
    • Reminder email body includes {outstanding_items} — bulleted list of what's missing ✓
    • {print_warning} tag appears when display name is absent, flagging that printing is blocked ✓
    • Default artwork_reminder and final_artwork_reminder templates updated to use these tags ✓
    • Admins can override template text via Email Templates page ✓

BSS-149 Online checkout collects contact name and email
Priority: High
Completed: 2026-06-24
Acceptance Criteria:
    • Basket/checkout form includes required contact_name and contact_email fields ✓
    • Contact record created at checkout; sponsorship linked to contact ✓
    • Confirmation email can now be sent to online sponsors ✓
    • Hint text explains the email is used for the artwork upload link ✓

BSS-150 Fix artwork status showing Complete when logo is missing (stale cached value)
Priority: Critical
Completed: 2026-06-24
Acceptance Criteria:
    • Migration 0.1.7 (RecalculateArtworkStatus) resets artwork_status to 'incomplete' for any paid sponsorship where display_name is blank or logo is absent and logo_not_needed = 0 ✓
    • BSS_VERSION bumped to 0.1.7 so migration runs on next admin page load ✓

BSS-151 Sponsorship list — remove ID column, sort alphabetically, add delete
Priority: Medium
Completed: 2026-06-24
Acceptance Criteria:
    • ID column removed from sponsorship list ✓
    • List sorted alphabetically by Sponsor display name; blank display names sort last ✓
    • Delete action available in list Actions column ✓
    • Delete action available on sponsorship view page ✓
    • Delete requires JS confirmation before proceeding ✓
    • All shields released back to 'available' on delete ✓
    • Sponsorship items, upload tokens, and reservations cleaned up on delete ✓
    • Success notice shown after deletion ✓
    • SponsorshipService.delete() handles all cleanup; action protected by per-ID nonce ✓

Email
BSS-072 Treasurer notification email
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Invoice/receipt delivered to treasurer on payment ✓
    • Treasurer email configurable in Settings (not hardcoded) ✓
    • Silently skips if treasurer_email is blank ✓

Patch Generation
BSS-083 Dynamic text scaling
Priority: Medium
Completed: 2026-06-17
Acceptance Criteria:
    • Large text fits layout without overflow ✓
    • Font size scales by mb_strlen on display_name and sponsor_text ✓

BSS-085 ZIP download generation
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Single ZIP of all PDFs produced ✓
    • Batch buttons: all/complete × PDF/ZIP ✓

Documentation
BSS-120 Create ADMIN_GUIDE.md
Priority: Medium
Completed: 2026-06-17
Acceptance Criteria:
    • Installation guide ✓
    • Operational guide ✓

BSS-121 Create README.md
Priority: Medium
Completed: 2026-06-17
Acceptance Criteria:
    • Installation documented ✓

Foundation
BSS-001 Create repository structure
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Repository created ✓
    • Folder structure matches technical specification ✓
    • Dist folder present ✓
    • Build scripts present ✓

BSS-002 Plugin bootstrap
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Plugin activates ✓
    • Plugin deactivates ✓
    • Version constant defined ✓ (BSS_VERSION)
    • Installer executes ✓

BSS-003 Database migration framework
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Migration runner created ✓
    • Idempotent migrations supported ✓
    • Migration history tracked ✓ (bss_db_version WP option)

Database
BSS-010 Create campaigns table
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Campaign CRUD supported ✓
    • Active campaign flag supported ✓

BSS-011 Create shields table
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Shield records stored ✓
    • Historical data preserved ✓

BSS-012 Create contacts table
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Sponsor records stored ✓
    • GDPR fields included ✓

BSS-013 Create sponsorship table
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Purchases stored ✓
    • Payment metadata stored ✓

BSS-014 Create sponsorship items table
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Multiple shields per purchase supported ✓

BSS-015 Create reservations table
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Reservation expiry supported ✓

BSS-016 Create upload tokens table
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Secure token storage supported ✓

BSS-017 Create email log table
Priority: Medium
Completed: 2026-06-17
Acceptance Criteria:
    • All outgoing email recorded ✓

BSS-018 Create audit log table
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Sensitive changes logged ✓

Shield Management
BSS-020 Shield administration page
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Add shield ✓
    • Edit shield ✓
    • Archive shield ✓
    • Upload shield image ✓

BSS-021 Shield availability management
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Available ✓
    • Reserved ✓
    • Sponsored ✓
    • Damaged ✓
States supported

BSS-022 Shield search and filtering
Priority: Medium
Completed: 2026-06-17
Acceptance Criteria:
    • Search by name ✓
    • Filter by side ✓
    • Filter by availability ✓

Campaign Management
BSS-030 Campaign administration page
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Create campaign ✓
    • Edit campaign ✓
    • Archive campaign ✓

BSS-031 Artwork cut-off management
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Campaign cut-off date stored ✓
    • Final reminder date calculated ✓

Public Shop
BSS-040 Shield catalogue shortcode
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Public display works ✓
    • Responsive layout ✓ (CSS enqueued)

BSS-041 Multi-shield selection
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Multiple shields selectable ✓ (basket UI)

BSS-042 Reservation engine
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • 30-minute reservation ✓
    • Expiry handling ✓

BSS-043 Reservation cleanup job
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Expired reservations released ✓ (hourly cron)

Stripe
BSS-050 Stripe integration
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Test mode ✓
    • Live mode ✓

BSS-051 Stripe checkout
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Payment succeeds ✓
    • Payment fails cleanly ✓

BSS-052 Stripe webhooks
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Payment confirmation received ✓
    • Duplicate webhook protection ✓ (idempotent mark_paid)

BSS-053 Stripe refunds
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Admin refund available ✓
    • Refund logged ✓

Sponsorship Workflow
BSS-060 Purchase workflow
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Sponsor details collected ✓
    • Payment linked ✓

BSS-061 Upload token generation
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Secure edit URL generated ✓

BSS-062 Sponsor edit page
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Logo upload ✓
    • Text editing ✓
    • Contact updates ✓

BSS-063 Artwork completion detection
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Complete status ✓
    • Incomplete status ✓
Calculated correctly

Email System
BSS-070 Email framework
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • HTML email support ✓
    • Template support ✓

BSS-071 Purchase confirmation email
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Sent after successful payment ✓

BSS-073 Reminder email engine
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Reminder schedule configurable ✓ (per-campaign frequency_days)

BSS-074 Final deadline reminder
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Sent day before cut-off ✓ (final_reminder_days_before)

BSS-075 Refund confirmation email
Priority: Medium
Completed: 2026-06-17
Acceptance Criteria:
    • Sent after refund ✓

Patch Generation
BSS-080 PDF generation framework
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • A4 PDF generated ✓ (mPDF)

BSS-081 Fixed patch template
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Matches approved design ✓ (PatchGenerationService::render_patch_html)

BSS-082 Logo rendering
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Uploaded logo displayed ✓

BSS-084 Batch patch generation
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Entire campaign downloadable ✓ (generate_for_campaign)

Manual Sponsorships
BSS-090 Manual sponsorship page
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Cash ✓
    • Card ✓
    • Bank transfer ✓
    • Other ✓
Supported

BSS-091 Admin editing of sponsorship data
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Text editable ✓
    • Logo replaceable ✓

GDPR
BSS-100 Marketing consent management
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Consent stored ✓
    • Exportable ✓

BSS-101 Contact export
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • CSV export ✓

BSS-102 GDPR anonymisation
Priority: High
Completed: 2026-06-17
Acceptance Criteria:
    • Contact anonymised ✓
    • Sponsorship retained ✓

Reporting
BSS-110 Sponsorship reports
Priority: Medium
Completed: 2026-06-17
Acceptance Criteria:
    • Revenue totals ✓
    • Shield counts ✓

BSS-111 Contact reports
Priority: Medium
Completed: 2026-06-17
Acceptance Criteria:
    • Contact exports ✓
    • Marketing exports ✓
