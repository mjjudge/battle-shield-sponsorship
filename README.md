# Battle Shield Sponsorship

A WordPress plugin for managing shield sponsorships at the Battle of Evesham re-enactment event.

## What it does

Sponsors browse available shields on a public shop page, choose one or more, pay via Stripe Checkout, and receive a unique link to upload their logo and sponsor text. Administrators manage the full lifecycle — shields, campaigns, contacts, sponsorships, full and partial refunds, artwork reminders, GDPR, and print-ready PDF patch generation.

## Requirements

- WordPress 6.0+
- PHP 8.2+
- A Stripe account (test keys for development, live keys for production)
- Composer (for mPDF patch generation — see below)

## Installation

1. Upload `battle-shield-sponsorship-<version>.zip` via **Plugins → Add New → Upload Plugin**
2. Activate the plugin
3. Go to **Shield Sponsorship → Settings** and enter your Stripe keys and page slugs
4. Create WordPress pages for the shop, success, cancel and sponsor-edit flows (see shortcodes below)
5. Create a campaign and add your shields

## Building the ZIP

```bash
./scripts/build-zip
# produces dist/battle-shield-sponsorship-<version>.zip
```

## Shortcodes

| Shortcode | Page purpose |
| --- | --- |
| `[battle_shield_shop]` | Public shield catalogue and checkout |
| `[battle_shield_success]` | Stripe payment success landing page |
| `[battle_shield_cancel]` | Stripe payment cancel landing page |
| `[battle_shield_edit]` | Sponsor artwork upload (requires `?token=` in URL) |

## Patch generation (mPDF)

PDF patch generation requires mPDF. Install it once, then rebuild the ZIP:

```bash
composer require mpdf/mpdf --working-dir=plugin
./scripts/build-zip
```

## Stripe webhook

Register this endpoint in your Stripe dashboard:

```text
https://your-site.example.com/wp-json/bss/v1/stripe/webhook
```

Enable these events: `checkout.session.completed`, `checkout.session.expired`, `payment_intent.payment_failed`, `charge.refunded`

## Development

```bash
# Run tests
php tests/run.php

# Build ZIP
./scripts/build-zip
```

See `ADMIN_GUIDE.md` for operational documentation.

## Recent changes (v0.5.0)

- **Manual sponsorship — contact auto-fill:** tabbing out of the email field looks up an existing contact and pre-fills name, phone, postal address, marketing consent, and Gift Aid (from prior sponsorships). Changes are written back to the contact record on save.
- **Manual sponsorship — address fields:** postal address (line 1 &amp; 2, city, county, postcode, country) added to the form as optional fields.
- **Manual sponsorship — marketing consent:** new checkbox "Sponsor has given consent to ongoing battle event related marketing communication", pre-populated from the contact record.
- **Partial refunds:** the Refunds page now shows an editable amount field defaulting to the full amount paid. Reduce it to issue a partial refund. For Stripe sponsorships the partial amount is sent directly to the Stripe API; for manual sponsorships it is recorded in the audit log.
