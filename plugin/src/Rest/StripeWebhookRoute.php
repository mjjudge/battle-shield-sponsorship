<?php

namespace BattleShieldSponsorship\Rest;

use BattleShieldSponsorship\Services\StripeService;
use BattleShieldSponsorship\Services\StripeWebhookProcessor;
use BattleShieldSponsorship\Services\SponsorshipService;

defined( 'ABSPATH' ) || exit;

class StripeWebhookRoute {

    public function register(): void {
        register_rest_route( 'bss/v1', '/stripe/webhook', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function handle( \WP_REST_Request $request ): \WP_REST_Response {
        $payload   = $request->get_body();
        $sig       = $request->get_header( 'stripe-signature' );

        if ( empty( $sig ) ) {
            return new \WP_REST_Response( [ 'error' => 'Missing signature' ], 400 );
        }

        $stripe    = new StripeService();
        $event_data = $stripe->verify_webhook_signature( $payload, $sig );

        if ( is_wp_error( $event_data ) ) {
            return new \WP_REST_Response( [ 'error' => $event_data->get_error_message() ], 400 );
        }

        $processor = new StripeWebhookProcessor();
        $processor->process( $event_data, new SponsorshipService() );

        return new \WP_REST_Response( [ 'ok' => true ], 200 );
    }
}
