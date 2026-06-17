<?php

declare(strict_types=1);

namespace BattleShieldSponsorship\Tests\Unit;

use BattleShieldSponsorship\Services\StripeWebhookProcessor;

final class StripeWebhookProcessorTest {

    public static function run(): void {
        self::test_missing_event_type_returns_400();
        self::test_checkout_completed_calls_mark_paid();
        self::test_checkout_completed_is_idempotent_when_already_paid();
        self::test_checkout_expired_calls_mark_abandoned();
        self::test_checkout_expired_skips_terminal_statuses();
        self::test_payment_failed_calls_mark_failed();
        self::test_charge_refunded_calls_mark_refunded_by_charge();
        self::test_charge_refunded_skips_already_refunded();
        self::test_missing_sponsorship_id_returns_400();
        self::test_unknown_event_returns_200();
    }

    private static function test_missing_event_type_returns_400(): void {
        $processor = new StripeWebhookProcessor();
        $service   = self::make_service();
        $result    = $processor->process( [], $service );

        self::assertSame( false, $result['ok'], 'Empty payload should return ok=false' );
        self::assertSame( 400, $result['status'], 'Empty payload should return 400' );
    }

    private static function test_checkout_completed_calls_mark_paid(): void {
        $log       = [];
        $service   = self::make_service( 1, 'pending', null, $log );
        $processor = new StripeWebhookProcessor();

        $result = $processor->process( self::session_completed_payload( 1, 'pi_abc' ), $service );

        self::assertSame( true, $result['ok'], 'Should return ok=true' );
        self::assertContains( 'mark_paid:1', $log, 'Should call mark_paid for sponsorship 1' );
    }

    private static function test_checkout_completed_is_idempotent_when_already_paid(): void {
        $log       = [];
        $service   = self::make_service( 1, 'paid', null, $log );
        $processor = new StripeWebhookProcessor();

        $processor->process( self::session_completed_payload( 1, 'pi_abc' ), $service );

        self::assertNotContains( 'mark_paid:1', $log, 'Should not call mark_paid when already paid' );
    }

    private static function test_checkout_expired_calls_mark_abandoned(): void {
        $log       = [];
        $service   = self::make_service( 2, 'pending', null, $log );
        $processor = new StripeWebhookProcessor();

        $processor->process( self::session_event_payload( 'checkout.session.expired', 2 ), $service );

        self::assertContains( 'mark_abandoned:2', $log, 'Should call mark_abandoned for expired session' );
    }

    private static function test_checkout_expired_skips_terminal_statuses(): void {
        foreach ( [ 'paid', 'refunded', 'failed', 'abandoned' ] as $status ) {
            $log     = [];
            $service = self::make_service( 3, $status, null, $log );
            ( new StripeWebhookProcessor() )->process( self::session_event_payload( 'checkout.session.expired', 3 ), $service );
            self::assertNotContains( 'mark_abandoned:3', $log, "Should not call mark_abandoned when status is {$status}" );
        }
    }

    private static function test_payment_failed_calls_mark_failed(): void {
        $log       = [];
        $service   = self::make_service( 4, 'pending', null, $log );
        $processor = new StripeWebhookProcessor();

        $processor->process( self::session_event_payload( 'payment_intent.payment_failed', 4 ), $service );

        self::assertContains( 'mark_failed:4', $log, 'Should call mark_failed on payment_intent.payment_failed' );
    }

    private static function test_charge_refunded_calls_mark_refunded_by_charge(): void {
        $log     = [];
        $service = self::make_service( 5, 'paid', 'ch_test123', $log );
        ( new StripeWebhookProcessor() )->process( [
            'type' => 'charge.refunded',
            'data' => [ 'object' => [ 'id' => 'ch_test123' ] ],
        ], $service );

        self::assertContains( 'mark_refunded:5', $log, 'Should call mark_refunded when charge found' );
    }

    private static function test_charge_refunded_skips_already_refunded(): void {
        $log     = [];
        $service = self::make_service( 6, 'refunded', 'ch_already', $log );
        ( new StripeWebhookProcessor() )->process( [
            'type' => 'charge.refunded',
            'data' => [ 'object' => [ 'id' => 'ch_already' ] ],
        ], $service );

        self::assertNotContains( 'mark_refunded:6', $log, 'Should not re-mark already-refunded sponsorship' );
    }

    private static function test_missing_sponsorship_id_returns_400(): void {
        $service = self::make_service();
        $result  = ( new StripeWebhookProcessor() )->process( [
            'type' => 'checkout.session.completed',
            'data' => [ 'object' => [] ],
        ], $service );

        self::assertSame( 400, $result['status'], 'Missing sponsorship_id should return 400' );
    }

    private static function test_unknown_event_returns_200(): void {
        $service = self::make_service( 1, 'pending' );
        $result  = ( new StripeWebhookProcessor() )->process( [
            'type' => 'customer.subscription.created',
            'data' => [ 'object' => [ 'metadata' => [ 'sponsorship_id' => '1' ] ] ],
        ], $service );

        self::assertSame( 200, $result['status'], 'Unknown event should return 200' );
    }

    /**
     * Builds a minimal checkout.session.completed payload.
     */
    private static function session_completed_payload( int $sponsorship_id, string $payment_intent ): array {
        return [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'metadata'       => [ 'sponsorship_id' => (string) $sponsorship_id ],
                    'payment_intent' => $payment_intent,
                ],
            ],
        ];
    }

    /**
     * Builds a generic session event payload (expired, payment_failed).
     */
    private static function session_event_payload( string $event, int $sponsorship_id ): array {
        return [
            'type' => $event,
            'data' => [
                'object' => [
                    'metadata' => [ 'sponsorship_id' => (string) $sponsorship_id ],
                ],
            ],
        ];
    }

    /**
     * Creates a minimal SponsorshipService test double.
     *
     * @param array<string> $log  Log of method calls, e.g. 'mark_paid:1'
     */
    private static function make_service(
        int $sponsorship_id = 0,
        string $status = 'pending',
        ?string $charge_id = null,
        array &$log = []
    ): object {
        $sponsorship = $sponsorship_id > 0 ? (object) [
            'id'             => $sponsorship_id,
            'payment_status' => $status,
            'stripe_charge_id' => $charge_id,
        ] : null;

        return new class ( $sponsorship, $charge_id, $log ) {

            public function __construct(
                private readonly ?object $sponsorship,
                private readonly ?string $charge_id,
                private array &$log,
            ) {}

            public function get_by_id( int $id ): ?object {
                return $this->sponsorship && (int) $this->sponsorship->id === $id ? $this->sponsorship : null;
            }

            public function get_by_stripe_charge( string $charge_id ): ?object {
                if ( $this->charge_id === $charge_id ) {
                    return $this->sponsorship;
                }
                return null;
            }

            public function mark_paid( int $id, string $intent, string $charge ): void {
                $this->log[] = 'mark_paid:' . $id;
            }

            public function mark_abandoned( int $id ): void {
                $this->log[] = 'mark_abandoned:' . $id;
            }

            public function mark_failed( int $id ): void {
                $this->log[] = 'mark_failed:' . $id;
            }

            public function mark_refunded( int $id ): void {
                $this->log[] = 'mark_refunded:' . $id;
            }
        };
    }

    private static function assertSame( mixed $expected, mixed $actual, string $message ): void {
        if ( $expected !== $actual ) {
            throw new \RuntimeException( $message . ' Expected: ' . json_encode( $expected ) . ' Actual: ' . json_encode( $actual ) );
        }
    }

    private static function assertContains( string $needle, array $haystack, string $message ): void {
        if ( ! in_array( $needle, $haystack, true ) ) {
            throw new \RuntimeException( $message . ' Haystack: ' . json_encode( $haystack ) );
        }
    }

    private static function assertNotContains( string $needle, array $haystack, string $message ): void {
        if ( in_array( $needle, $haystack, true ) ) {
            throw new \RuntimeException( $message . ' (found in: ' . json_encode( $haystack ) . ')' );
        }
    }
}
