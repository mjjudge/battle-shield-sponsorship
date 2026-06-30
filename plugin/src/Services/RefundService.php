<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Audit\Logger;

defined( 'ABSPATH' ) || exit;

class RefundService {

    /**
     * Process a full or partial refund.
     *
     * @param float $amount  Amount to refund in pounds. 0.0 means refund the full amount.
     * @return array{ok:bool, error?:string}
     */
    public function process( int $sponsorship_id, string $reason = '', float $amount = 0.0 ): array {
        $service     = new SponsorshipService();
        $sponsorship = $service->get_by_id( $sponsorship_id );

        if ( ! is_object( $sponsorship ) ) {
            return [ 'ok' => false, 'error' => 'sponsorship_not_found' ];
        }

        if ( 'paid' !== (string) $sponsorship->payment_status ) {
            return [ 'ok' => false, 'error' => 'not_refundable' ];
        }

        $total = (float) $sponsorship->total_amount;

        // Default to full amount; clamp to total if caller somehow passed too much.
        if ( $amount <= 0.0 || $amount > $total ) {
            $amount = $total;
        }

        $is_partial = $amount < $total - 0.001;
        $is_stripe  = 'stripe' === (string) $sponsorship->payment_method;

        if ( $is_stripe ) {
            $amount_pence = $is_partial ? (int) round( $amount * 100 ) : 0;
            $result       = $this->stripe_refund( $sponsorship, $amount_pence );
            if ( ! $result['ok'] ) {
                return $result;
            }
            Logger::log( 'refund_issued', 'sponsorship', $sponsorship_id, null, [
                'stripe_refund_id' => $result['refund_id'] ?? '',
                'amount'           => $amount,
                'reason'           => $reason,
            ] );
        }

        if ( $is_partial ) {
            $service->mark_partial_refund( $sponsorship_id );
            if ( ! $is_stripe ) {
                Logger::log( 'manual_partial_refund_processed', 'sponsorship', $sponsorship_id, null, [
                    'amount' => $amount,
                    'reason' => $reason,
                ] );
            }
        } else {
            $service->mark_refunded( $sponsorship_id );
            if ( ! $is_stripe ) {
                Logger::log( 'manual_refund_processed', 'sponsorship', $sponsorship_id, null, [ 'reason' => $reason ] );
            }
        }

        return [ 'ok' => true ];
    }

    /**
     * @param object $sponsorship
     * @param int    $amount_pence  0 = full refund via Stripe.
     * @return array{ok:bool, refund_id?:string, error?:string}
     */
    private function stripe_refund( object $sponsorship, int $amount_pence = 0 ): array {
        $intent_id = (string) ( $sponsorship->stripe_payment_intent_id ?? '' );
        $charge_id = (string) ( $sponsorship->stripe_charge_id ?? '' );

        if ( '' === $intent_id && '' === $charge_id ) {
            return [ 'ok' => false, 'error' => 'no_stripe_identifier' ];
        }

        $refund_id = ( new StripeService() )->refund( $intent_id, $charge_id, $amount_pence );

        if ( '' === $refund_id ) {
            return [ 'ok' => false, 'error' => 'stripe_refund_failed' ];
        }

        return [ 'ok' => true, 'refund_id' => $refund_id ];
    }
}
