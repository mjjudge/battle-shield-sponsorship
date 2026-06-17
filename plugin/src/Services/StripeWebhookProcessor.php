<?php

namespace BattleShieldSponsorship\Services;

defined( 'ABSPATH' ) || exit;

class StripeWebhookProcessor {

    /**
     * Process a decoded Stripe webhook payload.
     *
     * @param array<string, mixed> $payload
     * @return array{ok:bool, status:int, error?:string}
     */
    public function process( array $payload, object $sponsorship_service ): array {
        $event  = (string) ( $payload['type'] ?? '' );
        $object = is_array( $payload['data']['object'] ?? null ) ? $payload['data']['object'] : [];

        if ( '' === $event ) {
            return [ 'ok' => false, 'status' => 400, 'error' => 'missing_event_type' ];
        }

        if ( 'charge.refunded' === $event ) {
            $charge_id    = (string) ( $object['id'] ?? '' );
            if ( '' !== $charge_id ) {
                $sponsorship = $sponsorship_service->get_by_stripe_charge( $charge_id );
                if ( $sponsorship && 'refunded' !== (string) $sponsorship->payment_status ) {
                    $sponsorship_service->mark_refunded( (int) $sponsorship->id );
                }
            }
            return [ 'ok' => true, 'status' => 200 ];
        }

        $sponsorship_id = (int) ( $object['metadata']['sponsorship_id'] ?? $object['client_reference_id'] ?? 0 );

        if ( $sponsorship_id <= 0 ) {
            return [ 'ok' => false, 'status' => 400, 'error' => 'missing_sponsorship_id' ];
        }

        $sponsorship = $sponsorship_service->get_by_id( $sponsorship_id );

        if ( ! is_object( $sponsorship ) ) {
            return [ 'ok' => false, 'status' => 404, 'error' => 'sponsorship_not_found' ];
        }

        if ( 'checkout.session.completed' === $event ) {
            if ( 'paid' !== (string) $sponsorship->payment_status ) {
                $sponsorship_service->mark_paid(
                    $sponsorship_id,
                    (string) ( $object['payment_intent'] ?? '' ),
                    (string) ( $object['payment_intent'] ?? '' )
                );
            }
            return [ 'ok' => true, 'status' => 200 ];
        }

        if ( in_array( $event, [ 'checkout.session.expired', 'checkout.session.async_payment_failed' ], true ) ) {
            $terminal = [ 'paid', 'refunded', 'failed', 'abandoned' ];
            if ( ! in_array( (string) $sponsorship->payment_status, $terminal, true ) ) {
                $sponsorship_service->mark_abandoned( $sponsorship_id );
            }
            return [ 'ok' => true, 'status' => 200 ];
        }

        if ( 'payment_intent.payment_failed' === $event ) {
            $terminal = [ 'paid', 'refunded', 'failed', 'abandoned' ];
            if ( ! in_array( (string) $sponsorship->payment_status, $terminal, true ) ) {
                $sponsorship_service->mark_failed( $sponsorship_id );
            }
            return [ 'ok' => true, 'status' => 200 ];
        }

        return [ 'ok' => true, 'status' => 200 ];
    }
}
