<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;

defined( 'ABSPATH' ) || exit;

class HelpPage {

    public function render(): void {
        RequestGuard::require_capability( 'bss_access' );

        $settings         = (array) get_option( 'bss_settings', [] );
        $shop_slug        = (string) ( $settings['shop_page_slug'] ?? 'shield-sponsorship' );
        $success_slug     = (string) ( $settings['success_page_slug'] ?? 'shield-sponsorship-complete' );
        $cancel_slug      = (string) ( $settings['cancel_page_slug'] ?? 'shield-sponsorship-cancel' );
        $edit_slug        = (string) ( $settings['edit_page_slug'] ?? 'shield-sponsorship-edit' );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Battle Shield Sponsorship — Help', 'battle-shield-sponsorship' ) . '</h1>';

        echo '<h2>' . esc_html__( 'Getting Started', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<ol>';
        echo '<li>' . esc_html__( 'Create an Event (Events → New Event). Set the event start and end dates, artwork cut-off, and default requested donation per shield (£100 by default).', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Add Shields (Shields → Add Shield). Each shield belongs to a side — Royals (Henry III) or Rebels (Simon de Montfort) — and has a requested donation amount (defaults to £100.00).', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Configure Settings: choose payment mode, enter Stripe keys if using Stripe, set email addresses, and confirm page slugs.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Create the four WordPress pages listed below, adding the relevant shortcode to each.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Set the event to Active to open the shop.', 'battle-shield-sponsorship' ) . '</li>';
        echo '</ol>';

        echo '<h2>' . esc_html__( 'Required WordPress Pages', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<p>' . esc_html__( 'Create these four pages in WordPress. The slugs must match what is configured in Settings → Page Slugs.', 'battle-shield-sponsorship' ) . '</p>';
        echo '<table class="widefat" style="max-width:760px;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Page title', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Slug (from Settings)', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Shortcode to add', 'battle-shield-sponsorship' ) . '</th>';
        echo '</tr></thead><tbody>';
        $pages = [
            [ __( 'Shield Sponsorship Shop', 'battle-shield-sponsorship' ), $shop_slug,    '[battle_shield_shop]' ],
            [ __( 'Payment Confirmed', 'battle-shield-sponsorship' ),        $success_slug, '[battle_shield_success]' ],
            [ __( 'Payment Cancelled', 'battle-shield-sponsorship' ),        $cancel_slug,  '[battle_shield_cancel]' ],
            [ __( 'Edit Your Sponsorship', 'battle-shield-sponsorship' ),    $edit_slug,    '[battle_shield_edit]' ],
        ];
        foreach ( $pages as [ $title, $slug, $code ] ) {
            echo '<tr>';
            echo '<td>' . esc_html( $title ) . '</td>';
            echo '<td><code>' . esc_html( $slug ) . '</code></td>';
            echo '<td><code>' . esc_html( $code ) . '</code></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__( 'Payment Modes', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<dl>';
        echo '<dt><strong>' . esc_html__( 'Test – No Stripe', 'battle-shield-sponsorship' ) . '</strong></dt>';
        echo '<dd>' . esc_html__( 'Bypasses Stripe entirely. Payments are confirmed automatically when the sponsor reaches the success page. Use this to test the full workflow (emails, artwork links, sponsorship records) without any Stripe account or keys.', 'battle-shield-sponsorship' ) . '</dd>';
        echo '<dt><strong>' . esc_html__( 'Test – Stripe', 'battle-shield-sponsorship' ) . '</strong></dt>';
        echo '<dd>' . esc_html__( 'Uses Stripe test keys. Real Stripe test cards are charged. Requires test API keys and a webhook endpoint configured in Stripe.', 'battle-shield-sponsorship' ) . '</dd>';
        echo '<dt><strong>' . esc_html__( 'Live', 'battle-shield-sponsorship' ) . '</strong></dt>';
        echo '<dd>' . esc_html__( 'Uses Stripe live keys. Real money is charged. Switch to this only when you are ready to take real payments.', 'battle-shield-sponsorship' ) . '</dd>';
        echo '</dl>';

        echo '<h2>' . esc_html__( 'Shortcode Reference', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<table class="widefat" style="max-width:700px;">';
        echo '<thead><tr><th>' . esc_html__( 'Shortcode', 'battle-shield-sponsorship' ) . '</th><th>' . esc_html__( 'Purpose', 'battle-shield-sponsorship' ) . '</th></tr></thead><tbody>';
        $shortcodes = [
            '[battle_shield_shop]'    => __( 'Shield browsing and checkout (shop page)', 'battle-shield-sponsorship' ),
            '[battle_shield_success]' => __( 'Payment success confirmation (success page)', 'battle-shield-sponsorship' ),
            '[battle_shield_cancel]'  => __( 'Payment cancelled message (cancel page)', 'battle-shield-sponsorship' ),
            '[battle_shield_edit]'    => __( 'Sponsor artwork upload form (edit page — requires token in URL)', 'battle-shield-sponsorship' ),
        ];
        foreach ( $shortcodes as $code => $desc ) {
            echo '<tr><td><code>' . esc_html( $code ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__( 'Sponsor Workflow', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<ol>';
        echo '<li>' . esc_html__( 'Sponsor visits the shop page, selects one or more shields, and checks out.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'On payment success, a sponsorship record is created and a confirmation email is sent with a unique edit link.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'The sponsor clicks their edit link to upload a logo and sponsor text.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Admins are reminded when artwork is missing via the daily cron job.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Once all artwork is received, generate PDF patches from Patches → Generate.', 'battle-shield-sponsorship' ) . '</li>';
        echo '</ol>';

        echo '<h2>' . esc_html__( 'Manual Sponsorships', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<p>' . esc_html__( 'Use Sponsorships → Add Manual Sponsorship to record a payment taken outside the online shop (cheque, cash, bank transfer). The sponsorship is marked as paid immediately and the sponsor receives a confirmation email with their edit link.', 'battle-shield-sponsorship' ) . '</p>';
        echo '<p>' . esc_html__( 'When you type a known email address and move focus away from the field, the form automatically looks up the existing contact and pre-fills their name, phone number, postal address, marketing consent, and — if they declared Gift Aid on a previous sponsorship — the Gift Aid checkbox. Any changes you make before saving are written back to the contact record.', 'battle-shield-sponsorship' ) . '</p>';
        echo '<p>' . esc_html__( 'The "Sponsor has given consent to ongoing battle event related marketing communication" checkbox records whether this sponsor has opted in. Address fields (address line 1 &amp; 2, city, county, postcode, country) are optional and are stored on the contact record for future correspondence.', 'battle-shield-sponsorship' ) . '</p>';

        echo '<h2>' . esc_html__( 'Shields: Royals and Rebels', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<p>' . esc_html__( 'Shields are grouped by historical side. Royals fought for King Henry III; Rebels fought for Simon de Montfort, Earl of Leicester. Sponsors can filter by side when browsing the shop.', 'battle-shield-sponsorship' ) . '</p>';

        echo '<h2>' . esc_html__( 'Refunds', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<p>' . esc_html__( 'Go to Refunds and click "Refund" next to any paid sponsorship. The refund amount defaults to the full amount paid — reduce it to issue a partial refund. The amount can never exceed the total originally paid.', 'battle-shield-sponsorship' ) . '</p>';
        echo '<ul>';
        echo '<li>' . esc_html__( 'Stripe sponsorships: the partial or full amount is sent to Stripe immediately. The refund appears in the sponsor\'s bank account within the usual Stripe timescales.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Manual sponsorships (cash, cheque, bank transfer): no automated transfer is made. The refund amount and reason are recorded in the audit log for your own records.', 'battle-shield-sponsorship' ) . '</li>';
        echo '</ul>';
        echo '<p>' . esc_html__( 'After any refund (partial or full) the sponsorship is removed from the refundable list. Partial refunds are flagged with refund_status = "partial" in the database; full refunds are flagged "full".', 'battle-shield-sponsorship' ) . '</p>';

        echo '<h2>' . esc_html__( 'GDPR', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<p>' . esc_html__( 'To handle a data removal request: open the contact in Contacts → Edit Contact and click "Anonymise Contact". This permanently removes personal details while preserving anonymised sponsorship records for accounting.', 'battle-shield-sponsorship' ) . '</p>';

        echo '<h2>' . esc_html__( 'Stripe Webhook URL', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<p>' . esc_html__( 'When using Stripe (Test – Stripe or Live mode), add this URL to your Stripe webhook dashboard:', 'battle-shield-sponsorship' ) . '</p>';
        echo '<code>' . esc_html( rest_url( 'bss/v1/stripe/webhook' ) ) . '</code>';
        echo '<p>' . esc_html__( 'Events to enable: checkout.session.completed, checkout.session.expired, payment_intent.payment_failed, charge.refunded', 'battle-shield-sponsorship' ) . '</p>';

        echo '</div>';
    }
}
