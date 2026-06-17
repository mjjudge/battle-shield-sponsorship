<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;

defined( 'ABSPATH' ) || exit;

class HelpPage {

    public function render(): void {
        RequestGuard::require_capability( 'bss_access' );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Battle Shield Sponsorship — Help', 'battle-shield-sponsorship' ) . '</h1>';

        echo '<h2>' . esc_html__( 'Getting Started', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<ol>';
        echo '<li>' . esc_html__( 'Create a Campaign (Campaigns → New Campaign). Set the event date, artwork cut-off, and default price.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Add Shields (Shields → Add Shield). Each shield belongs to a side (Baron/Royalist/Other) and has a suggested price.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Configure Stripe keys and page slugs in Settings.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Create WordPress pages for the shop, success, cancel, and sponsor-edit URLs, adding the relevant shortcode to each.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Set the campaign to Active to open the shop.', 'battle-shield-sponsorship' ) . '</li>';
        echo '</ol>';

        echo '<h2>' . esc_html__( 'Shortcodes', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<table class="widefat" style="max-width:700px;">';
        echo '<thead><tr><th>' . esc_html__( 'Shortcode', 'battle-shield-sponsorship' ) . '</th><th>' . esc_html__( 'Purpose', 'battle-shield-sponsorship' ) . '</th></tr></thead><tbody>';
        $shortcodes = [
            '[battle_shield_shop]'          => __( 'Shield browsing and checkout (use on shop page)', 'battle-shield-sponsorship' ),
            '[battle_shield_success]'        => __( 'Payment success confirmation (use on success page)', 'battle-shield-sponsorship' ),
            '[battle_shield_cancel]'         => __( 'Payment cancelled message (use on cancel page)', 'battle-shield-sponsorship' ),
            '[battle_shield_edit]'           => __( 'Sponsor artwork upload form (use on edit page — requires token in URL)', 'battle-shield-sponsorship' ),
        ];
        foreach ( $shortcodes as $code => $desc ) {
            echo '<tr><td><code>' . esc_html( $code ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__( 'Sponsor Workflow', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<ol>';
        echo '<li>' . esc_html__( 'Sponsor visits the shop page, selects a shield, and checks out via Stripe.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'On payment success, a sponsorship record is created and a confirmation email is sent with a unique edit link.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'The sponsor clicks their edit link to upload a logo and sponsor text.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Admins are reminded when artwork is missing via the daily cron job.', 'battle-shield-sponsorship' ) . '</li>';
        echo '<li>' . esc_html__( 'Once all artwork is received, generate PDF patches from Patches → Generate.', 'battle-shield-sponsorship' ) . '</li>';
        echo '</ol>';

        echo '<h2>' . esc_html__( 'Manual Sponsorships', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<p>' . esc_html__( 'Use Sponsorships → Add Manual Sponsorship to record a payment taken outside the online shop (cheque, cash, bank transfer). The sponsorship is marked as paid immediately and the sponsor receives a confirmation email with their edit link.', 'battle-shield-sponsorship' ) . '</p>';

        echo '<h2>' . esc_html__( 'GDPR', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<p>' . esc_html__( 'To handle a data removal request: open the contact in Contacts → Edit Contact and click "Anonymise Contact". This permanently removes personal details while preserving anonymised sponsorship records for accounting.', 'battle-shield-sponsorship' ) . '</p>';

        echo '<h2>' . esc_html__( 'Webhook URL', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<p>' . esc_html__( 'Add this URL to your Stripe webhook dashboard:', 'battle-shield-sponsorship' ) . '</p>';
        echo '<code>' . esc_html( rest_url( 'bss/v1/stripe/webhook' ) ) . '</code>';
        echo '<p>' . esc_html__( 'Events to enable: checkout.session.completed, checkout.session.expired, payment_intent.payment_failed, charge.refunded', 'battle-shield-sponsorship' ) . '</p>';

        echo '</div>';
    }
}
