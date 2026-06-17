# Battle Shield Sponsorship — Admin Guide

## Installation

1. Upload the plugin ZIP via **Plugins → Add New → Upload Plugin** and activate it.
2. Go to **Shield Sponsorship → Settings** and complete all sections before opening sales.

---

## Initial Setup Checklist

### 1. Settings

Go to **Shield Sponsorship → Settings**.

**Stripe Integration**

- Set **Mode** to *Test* during setup, *Live* when ready to take real payments.
- Paste your Stripe publishable and secret keys for both test and live modes.
- Paste the **webhook secret** from your Stripe dashboard (see Stripe Setup below).

**Email**

- **From name / From email** — the sender that sponsors see in their inbox.
- **Treasurer email** — receives a notification with full payment details immediately after every confirmed sponsorship. Leave blank to disable.
- **Help / contact email** — shown to sponsors in email footers as `{help_email}`.

**Page Slugs**

Create four WordPress pages and enter their slugs here:

| Setting | Suggested slug | Shortcode to add |
| --- | --- | --- |
| Shop page | `shield-sponsorship` | `[battle_shield_shop]` |
| Payment success | `shield-sponsorship-complete` | `[battle_shield_success]` |
| Payment cancel | `shield-sponsorship-cancel` | `[battle_shield_cancel]` |
| Sponsor edit | `shield-sponsorship-edit` | `[battle_shield_edit]` |

### 2. Stripe Setup

1. Log in to the [Stripe Dashboard](https://dashboard.stripe.com) and go to **Developers → Webhooks**.
2. Click **Add endpoint** and enter your webhook URL:
   ```text
   https://your-site.example.com/wp-json/bss/v1/stripe/webhook
   ```
3. Select these events:
   - `checkout.session.completed`
   - `checkout.session.expired`
   - `payment_intent.payment_failed`
   - `charge.refunded`
4. Copy the **Signing secret** and paste it into Settings → Stripe webhook secret.

### 3. Create a Campaign

Go to **Shield Sponsorship → Campaigns → New Campaign**.

| Field | Notes |
| --- | --- |
| Campaign name | e.g. *Battle of Evesham 2026* |
| Event date | The date of the battle re-enactment |
| Sales open date | When the shop becomes publicly accessible |
| Artwork cut-off date | Deadline for sponsors to upload logos |
| Default price per shield | Used as the suggested price for new shields |
| Reminder frequency | How many days between artwork reminder emails |
| Final reminder days before cut-off | When to send the urgent final reminder |
| Reservation timeout | How long a basket hold lasts (default 30 min) |
| Gift Aid | Enable if you are eligible to claim Gift Aid |

Set the campaign **Status** to *Active* when you are ready to open sales.

### 4. Add Shields

Go to **Shield Sponsorship → Shields → Add Shield**.

- Enter the shield's name, which side it fought on, a short historical description, and a suggested price.
- Upload a shield image via the media picker — this appears on the public shop.
- Set **Physical state** to *Available* for shields ready to sponsor.

---

## Day-to-Day Operations

### Viewing Sponsorships

**Shield Sponsorship → Sponsorships** lists all sponsorships. Use the filters at the top to narrow by campaign, payment status, or artwork status.

Click **View** on any row to see full details, edit the sponsor's display name and logo, or trigger a refund.

### Recording a Manual Sponsorship

Use **Shield Sponsorship → Manual Sponsorship** to record a payment taken outside the online shop (phone, post, in person).

- Select the campaign and shields, enter the sponsor's contact details, and choose the payment method.
- The sponsorship is marked as paid immediately and the sponsor receives a confirmation email with their unique edit link.

### Processing a Refund

Go to **Shield Sponsorship → Refunds**. Find the sponsorship and click **Refund**.

- For Stripe payments the refund is submitted to Stripe automatically.
- For manual payments a note is recorded and the sponsorship is marked refunded; you handle the money separately.
- The sponsor receives a refund confirmation email.

### Chasing Missing Artwork

The plugin runs a daily check automatically. Any paid sponsorship without a complete logo and display name receives a reminder email at the frequency configured per campaign.

You can also see which sponsorships are missing artwork at a glance from **Shield Sponsorship → Sponsorships** (look at the Artwork column).

### Generating PDF Patches

Go to **Shield Sponsorship → Patches**.

> **Note:** Patch generation requires mPDF. Install it by running:
> ```bash
> composer require mpdf/mpdf --working-dir=<path-to-plugin>
> ./scripts/build-zip
> ```
> and uploading the new ZIP.

Select a campaign and choose a download option:

| Button | What you get |
| --- | --- |
| Download all patches (PDF) | Single PDF, one page per sponsorship |
| Download complete artwork only (PDF) | Same, but skips incomplete artwork |
| Download all patches (ZIP) | One PDF per sponsorship, bundled in a ZIP |
| Download complete artwork only (ZIP) | Same, but skips incomplete artwork |

You can also download a single sponsorship's patch from the individual patches table.

### Reports and Exports

**Shield Sponsorship → Reports** shows a campaign summary (revenue, shield counts, artwork status, Gift Aid count) and offers two CSV exports:

- **Sponsorships CSV** — one row per paid sponsorship with all financial fields.
- **Opted-in Contacts CSV** — contacts who have consented to marketing.

---

## Email Templates

**Shield Sponsorship → Email Templates** lets you customise the subject and body of every outgoing email.

Available templates:

| Key | When sent |
| --- | --- |
| Sponsorship Confirmation | After a successful payment (to sponsor) |
| Artwork Upload Reminder | Daily cron, until artwork is complete |
| Final Artwork Reminder | Sent N days before the cut-off |
| Refund Confirmation | After a refund is processed |
| GDPR Removal Confirmation | After a contact is anonymised |

Available tags: `{sponsor_name}` `{display_name}` `{campaign_name}` `{shield_names}` `{cutoff_date}` `{edit_url}` `{total_amount}` `{payment_method}` `{help_email}`

Click **Reset to Default** to restore the built-in wording.

---

## GDPR

To handle a removal request:

1. Go to **Shield Sponsorship → Contacts**, find the contact, and click **Edit**.
2. Scroll to the **GDPR Anonymisation** section and click **Anonymise Contact**.
3. The contact's personal details are permanently deleted. Sponsorship records are retained with an anonymised reference for accounting purposes.
4. The contact receives a GDPR removal confirmation email before deletion.

---

## Roles and Permissions

| Role | Capabilities |
| --- | --- |
| `bss_manager` | Shields, campaigns, sponsorships, contacts, patches |
| `bss_admin` | All of the above plus settings, refunds, and GDPR |
| Administrator | All capabilities |
| Editor | Granted `bss_manager` capabilities automatically |

---

## Logs

**Shield Sponsorship → Logs** shows two tabs:

- **Audit Log** — every sensitive state change (payment received, refund issued, GDPR anonymisation, etc.) with before/after values.
- **Email Log** — every outbound email attempt with recipient, subject, and sent/failed status.

---

## Troubleshooting

**Stripe payments complete but sponsorship stays pending**

The plugin uses webhooks, not the redirect URL, as the source of truth. Check:

1. The webhook endpoint is registered in the Stripe dashboard.
2. The webhook secret in Settings matches the one in Stripe.
3. The **Shield Sponsorship → Logs → Email Log** shows a `treasurer_notification` entry (confirms the webhook fired).

**Emails not sending**

Check the Email Log for `failed` entries. The error column shows `wp_mail_failed`, which usually means your WordPress mail configuration needs a transactional mail plugin (e.g. WP Mail SMTP).

**Reservations not expiring**

The cleanup runs as a WordPress cron job. If WP-Cron is disabled on your server, set up a real cron job:

```bash
*/5 * * * * curl -s https://your-site.example.com/wp-cron.php?doing_wp_cron > /dev/null
```
