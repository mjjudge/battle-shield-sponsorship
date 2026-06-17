<?php

namespace BattleShieldSponsorship\Services;

defined( 'ABSPATH' ) || exit;

class StripeService {

    /**
     * Create a Stripe Checkout session for a sponsorship.
     *
     * @param array<string, mixed> $line_items  [ ['name' => '...', 'amount_gbp' => 0.00], ... ]
     * @return array{session_id:string, checkout_url:string}
     */
    public function create_checkout_session( int $sponsorship_id, array $line_items, string $customer_email = '' ): array {
        $settings   = $this->settings();
        $secret_key = $settings['stripe_secret_key'] ?? '';

        if ( '' === $secret_key ) {
            return $this->local_fallback( $sponsorship_id );
        }

        $success_url = add_query_arg( [ 'sponsorship_id' => $sponsorship_id ], home_url( '/' . ( $settings['success_page_slug'] ?? 'shield-sponsorship-success' ) ) );
        $cancel_url  = add_query_arg( [ 'sponsorship_id' => $sponsorship_id ], home_url( '/' . ( $settings['cancel_page_slug'] ?? 'shield-sponsorship-cancel' ) ) );

        $body = [
            'mode'                                          => 'payment',
            'success_url'                                   => $success_url,
            'cancel_url'                                    => $cancel_url,
            'client_reference_id'                           => (string) $sponsorship_id,
            'metadata[sponsorship_id]'                      => (string) $sponsorship_id,
        ];

        if ( '' !== $customer_email ) {
            $body['customer_email'] = $customer_email;
        }

        foreach ( $line_items as $i => $item ) {
            $body[ "line_items[{$i}][quantity]" ]                                 = 1;
            $body[ "line_items[{$i}][price_data][currency]" ]                     = 'gbp';
            $body[ "line_items[{$i}][price_data][product_data][name]" ]            = sanitize_text_field( $item['name'] );
            $body[ "line_items[{$i}][price_data][unit_amount]" ]                   = (string) max( 1, (int) round( (float) $item['amount_gbp'] * 100 ) );
        }

        $response = wp_remote_post(
            'https://api.stripe.com/v1/checkout/sessions',
            [
                'headers' => [ 'Authorization' => 'Bearer ' . $secret_key ],
                'body'    => $body,
                'timeout' => 20,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $this->local_fallback( $sponsorship_id );
        }

        $status  = (int) wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status || ! is_array( $decoded ) || empty( $decoded['id'] ) || empty( $decoded['url'] ) ) {
            return $this->local_fallback( $sponsorship_id );
        }

        return [
            'session_id'   => sanitize_text_field( (string) $decoded['id'] ),
            'checkout_url' => esc_url_raw( (string) $decoded['url'] ),
        ];
    }

    public function verify_webhook_signature( string $raw_body, string $provided_signature, string $secret ): bool {
        if ( '' === $secret || '' === $provided_signature || '' === $raw_body ) {
            return false;
        }

        $parts = [];
        foreach ( explode( ',', $provided_signature ) as $segment ) {
            [ $k, $v ] = array_pad( explode( '=', trim( $segment ), 2 ), 2, '' );
            $parts[ $k ] = $v;
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if ( '' === $timestamp || '' === $signature ) {
            return false;
        }

        $signed_payload = $timestamp . '.' . $raw_body;
        $expected       = hash_hmac( 'sha256', $signed_payload, $secret );

        return hash_equals( $expected, $signature );
    }

    public function refund( string $payment_intent_id, string $charge_id = '' ): string {
        $settings   = $this->settings();
        $secret_key = $settings['stripe_secret_key'] ?? '';

        if ( '' === $secret_key ) {
            return '';
        }

        $identifier = $charge_id ?: $payment_intent_id;
        $body       = $charge_id ? [ 'charge' => $charge_id ] : [ 'payment_intent' => $payment_intent_id ];

        $response = wp_remote_post(
            'https://api.stripe.com/v1/refunds',
            [
                'headers' => [ 'Authorization' => 'Bearer ' . $secret_key ],
                'body'    => $body,
                'timeout' => 20,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $status  = (int) wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status || ! is_array( $decoded ) || empty( $decoded['id'] ) ) {
            return '';
        }

        return sanitize_text_field( (string) $decoded['id'] );
    }

    /** @return array<string, mixed> */
    private function settings(): array {
        return (array) get_option( 'bss_settings', [] );
    }

    /** @return array{session_id:string, checkout_url:string} */
    private function local_fallback( int $sponsorship_id ): array {
        $settings    = $this->settings();
        $slug        = $settings['success_page_slug'] ?? 'shield-sponsorship-success';
        $session_id  = 'bss_local_' . bin2hex( random_bytes( 12 ) );
        $url         = add_query_arg( [ 'sponsorship_id' => $sponsorship_id, 'session_id' => $session_id ], home_url( '/' . $slug ) );

        return [
            'session_id'   => $session_id,
            'checkout_url' => $url,
        ];
    }
}
