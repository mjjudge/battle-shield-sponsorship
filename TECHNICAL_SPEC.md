Battle Shield Sponsorship WordPress Plugin — Technical Spec
1. Purpose
Create a standalone WordPress plugin to manage Battle of Evesham shield sponsorships.
The plugin allows sponsors to browse available shields, select one or more shields, pay by Stripe, upload logo/artwork/text, and receive confirmation. Administrators can manage shields, sponsors, payments, refunds, reminders, GDPR requests, and generate print-ready A4 sponsorship patches.
2. Working Plugin Name
Battle Shield Sponsorship
Suggested slug:
battle-shield-sponsorship
Suggested main plugin file:
battle-shield-sponsorship.php
Admin menu icon: small shield SVG.

3. Core Concepts
Shield
A persistent physical shield used across events.
Each shield has:
    • Shield ID
    • Shield image
    • Baron/Royalist name
    • Side: Baron, Royalist, or possibly Other
    • Short historical description
    • Suggested sponsorship price
    • Current physical state:
        ◦ Available
        ◦ Reserved
        ◦ Sponsored/Paid
        ◦ Unavailable/Damaged
    • Optional admin notes
Shields are not tied permanently to a single year. A shield can be sponsored by different sponsors in different campaigns.
Campaign
A sponsorship campaign, usually annual.
Examples:
    • Battle of Evesham 2026
    • Medieval Market 2027
Each campaign has:
    • Name
    • Event date
    • Sponsorship opening date
    • Artwork/logo cut-off date
    • Reminder frequency, default every 2 days
    • Final reminder timing, default 1 day before cut-off
    • Stripe account/config selection
    • Gift Aid enabled/disabled
    • Active/inactive status
Sponsor Contact
A persistent contact record across campaigns.
Fields:
    • Contact name
    • Sponsor display name / company name
    • Email
    • Phone
    • Website URL
    • Marketing opt-in
    • GDPR status
    • Anonymised flag
    • Created date
    • Last updated date
Sponsorship / Purchase
A transaction in which one sponsor sponsors one or more shields.
Fields:
    • Campaign ID
    • Contact ID
    • Sponsor display name
    • Payment method:
        ◦ Stripe card
        ◦ Bank transfer
        ◦ Cash
        ◦ Other
    • Stripe payment intent/session ID where relevant
    • Total amount
    • Payment status
    • Refund status
    • Gift Aid declaration where enabled
    • Upload token
    • Token creation date
    • Artwork completion status
    • Created date
    • Updated date
Sponsorship Item
Each shield inside a purchase.
Fields:
    • Purchase ID
    • Shield ID
    • Price paid
    • Status:
        ◦ Reserved
        ◦ Paid complete
        ◦ Paid incomplete
        ◦ Refunded
    • Patch data snapshot

4. Public User Journey
Browse Shields
A public shortcode displays the shield shop.
Example shortcode:
[battle_shield_shop campaign="2026"]
The shop must show:
    • Shield image
    • Baron/Royalist name
    • Side
    • Short description
    • Availability
    • Price
    • Search by name
    • Filter by Baron/Royalist
    • Filter by availability
Unavailable or sponsored shields remain visible but cannot be selected.
Select Shields
Sponsors can select one or more available shields.
Rules:
    • Selected shields are reserved for 30 minutes.
    • A scheduled clean-up job releases expired reservations.
    • Reserved shields cannot be purchased by another sponsor during the hold period.
Checkout
Mandatory at payment:
    • Contact name
    • Sponsor display name / company name
    • Email
    • Acceptance of terms/GDPR text
    • Marketing opt-in yes/no
Optional at payment:
    • Phone
    • Website URL
    • Sponsor short text
    • Logo upload
    • Gift Aid declaration, only if enabled for the campaign
Stripe Checkout is used for MVP.
After Successful Payment
The shield is considered sponsored immediately after successful payment.
If all artwork fields are complete, status becomes:
Paid complete
If logo/text is missing, status becomes:
Paid incomplete
The sponsor receives a confirmation email containing:
    • Payment confirmation
    • Shields sponsored
    • Receipt/invoice
    • Secure edit/upload link
    • Artwork deadline
    • Patch preview/mock-up if possible
The treasurer receives a copy invoice/receipt email.

5. Later Upload / Edit Journey
Sponsors do not need a WordPress login.
Each purchase has a secure tokenised URL.
Example:
/shield-sponsorship/edit/?token=abc123
The page allows the sponsor to update, before cut-off:
    • Sponsor display name
    • Logo/image
    • Short text
    • Website
    • Phone
    • Other display details
After cut-off:
    • The form is locked.
    • The page explains that the deadline has passed.
    • It asks them to email helpgrow@battleofevesham.co.uk.
    • It clearly states that updates are not guaranteed to appear on the physical shield.
The successful payment email should always include this edit link, even if the sponsor completed all fields at checkout.

6. Reminder Emails
For Paid incomplete purchases:
    • Send reminder every configurable number of days.
    • Default: every 2 days.
    • Stop reminders once artwork is complete.
    • Send final reminder one day before cut-off.
    • After cut-off, no more standard upload reminders.
Reminder email should include:
    • Sponsor name
    • Shield(s) sponsored
    • Missing items
    • Secure upload/edit link
    • Cut-off date
    • Contact email for help
If a sponsor has multiple incomplete transactions, the reminder email may include multiple upload links.

7. Patch Generation
The MVP must generate print-ready A4 patches.
Initial layout is fixed, based on the supplied example:
    • Top: army/side label, e.g. “Rebel Army”
    • Main title: {Baron/Royalist Name} is supported by:
    • Central bordered sponsor box
    • Sponsor logo
    • Sponsor display name
    • Optional sponsor description
    • Optional phone
    • Optional website
    • Bottom: BattleofEvesham.co.uk
Output:
    • Individual print-ready PDF per shield
    • Batch download ZIP of all campaign patches
    • Batch generation on or after artwork cut-off
    • Ability for admins to regenerate a single patch after editing details
Text handling:
    • Maximum character count configurable
    • Font size adjusts down for longer text
    • Hard maximum to prevent unreadable patches
    • Missing logo falls back to sponsor display name only

8. Admin Features
Admin menu pages:
    1. Dashboard
    2. Campaigns
    3. Shields
    4. Sponsorships
    5. Contacts
    6. Manual Sponsorships
    7. Refunds
    8. Patch Generator
    9. Email Templates
    10. Reports / Exports
    11. Settings
    12. Logs
    13. Help
Dashboard
Shows:
    • Active campaign
    • Number of shields available
    • Number reserved
    • Number sponsored complete
    • Number sponsored incomplete
    • Number unavailable/damaged
    • Upcoming cut-off date
    • Recent payments
    • Recent uploads
    • Recent errors
Manual Sponsorships
Admins can enter sponsorships manually for:
    • Card
    • Bank transfer
    • Cash
    • Other
Manual sponsorships:
    • Do not require invoice/receipt generation
    • Can include full sponsor details
    • Can include logo upload
    • Can reserve/sponsor one or more shields
    • Must mark shields as sponsored
Refunds
Admins can refund Stripe payments from WordPress.
Refund action should:
    • Call Stripe refund API
    • Update purchase status
    • Update shield/sponsorship status appropriately
    • Log the refund
    • Send refund confirmation email
    • Preserve historical record

9. GDPR / Data Protection
Marketing opt-in must be explicit.
Admin-only anonymisation process:
    • Contact name removed/anonymised
    • Email removed/anonymised
    • Phone removed/anonymised
    • Address, if ever added, removed/anonymised
    • Historical sponsorship records retained
    • Sponsor display name on historical public/business sponsorship records may remain unless removal is specifically requested
Marketing emails must include wording such as:
“To be removed from future Battle of Evesham sponsorship/contact emails, please reply to this email or contact the organisers.”
Reports:
    • CSV export of opted-in contacts
    • CSV export of sponsors by campaign
    • CSV export for mail merge

10. Settings
Settings page should include:
Stripe
    • Test/live mode
    • Publishable key
    • Secret key
    • Webhook secret
    • Treasurer invoice email
    • Payment success page
    • Payment failure page
Campaign defaults
    • Default price
    • Reservation timeout, default 30 minutes
    • Reminder frequency, default 2 days
    • Default artwork cut-off wording
    • Contact/help email
Gift Aid
    • Enable/disable Gift Aid globally
    • Enable/disable per campaign
    • Gift Aid declaration text
    • Charity name/number fields, when applicable
Email templates
Editable with simple HTML editor:
    • Successful sponsorship
    • Failed payment
    • Upload reminder
    • Final upload reminder
    • Artwork received
    • Refund confirmation
    • Admin new sponsorship notification
    • Admin artwork upload notification
    • GDPR removal acknowledgement
Patch settings
    • Default layout
    • Fonts
    • Colours
    • Logo max dimensions
    • Sponsor text max characters
    • Website display on/off
    • Phone display on/off
GDPR
    • Privacy text
    • Terms text
    • Marketing opt-in wording
    • Data retention/anonymisation wording

11. Suggested Repository Structure
battle-shield-sponsorship/
├── AGENTS.md
├── BACKLOG.md
├── CLAUDE.md
├── LICENSE
├── MEMORY.md
├── README.md
├── TECHNICAL_SPEC.md
├── WORDPRESS_SETUP.md
├── .env.example
├── .gitignore
├── uninstall.php
├── dist/
├── docs/
│   └── ADMIN_GUIDE.md
├── plugin/
│   ├── battle-shield-sponsorship.php
│   ├── README.md
│   ├── uninstall.php
│   ├── assets/
│   │   ├── css/
│   │   ├── images/
│   │   │   └── shield-menu-icon.svg
│   │   └── js/
│   ├── languages/
│   ├── src/
│   │   ├── Admin/
│   │   ├── Audit/
│   │   ├── Core/
│   │   ├── Database/
│   │   │   └── Migrations/
│   │   ├── Mail/
│   │   ├── Patch/
│   │   ├── Public/
│   │   ├── Rest/
│   │   ├── Security/
│   │   └── Services/
│   └── templates/
│       ├── admin/
│       ├── email/
│       └── public/
├── scripts/
│   ├── build-zip
│   ├── test
│   └── test-watch
└── tests/
    ├── bootstrap.php
    ├── run.php
    ├── Integration/
    └── Unit/

12. Architecture
Use the same pattern as the Duck Race plugin:
    • Thin controllers
    • Thick service classes
    • Versioned database migrations
    • WP REST API for Stripe webhooks
    • Audit logging for sensitive state changes
    • Shortcodes for public pages
    • No WooCommerce dependency
    • No sponsor login accounts
    • Tokenised secure sponsor edit links
    • Build script to create versioned ZIPs in /dist

13. Key Services
Suggested services:
    • CampaignService
    • ShieldService
    • ShieldAvailabilityService
    • ReservationService
    • ReservationCleanupService
    • ContactService
    • SponsorshipService
    • ManualSponsorshipService
    • StripeService
    • StripeWebhookProcessor
    • RefundService
    • EmailService
    • ReminderService
    • PatchGenerationService
    • UploadTokenService
    • GdprService
    • ReportingService
    • AuditLogger

14. Database Tables
Suggested custom tables:
    • bss_campaigns
    • bss_shields
    • bss_contacts
    • bss_sponsorships
    • bss_sponsorship_items
    • bss_reservations
    • bss_upload_tokens
    • bss_email_log
    • bss_audit_log
    • bss_refunds
    • bss_patch_files
    • bss_settings_meta, optional if not using WP options

15. MVP Scope
MVP should include:
    • Shield catalogue admin
    • Campaign admin
    • Public shield shop shortcode
    • Multi-shield selection
    • 30-minute reservation hold
    • Stripe Checkout
    • Stripe webhook handling
    • Successful payment handling
    • Sponsor contact capture
    • Logo/text upload during checkout or later
    • Secure tokenised edit/upload page
    • Reminder emails
    • Final reminder email
    • Admin manual sponsorship entry
    • Stripe refunds from WordPress
    • GDPR anonymisation
    • Marketing opt-in export
    • Individual and batch A4 PDF patch generation
    • Basic logs
    • Versioned ZIP build script

16. Future Enhancements
Possible later features:
    • Visual patch layout editor
    • Drag-and-drop patch designer
    • Multiple logo/text variants within one transaction
    • Public sponsor directory
    • Shield location mapping
    • Renewal emails for previous sponsors
    • Early-bird pricing
    • Waiting list for popular shields
    • CRM integration
    • Mailchimp/Brevo export
    • QR codes on shield patches
    • Sponsor self-service history page