<?php

namespace BattleShieldSponsorship\Services;

defined( 'ABSPATH' ) || exit;

class ReservationCleanupService {

    public const CRON_HOOK = 'bss_cleanup_stale_reservations';

    public function run_scheduled(): void {
        ( new ReservationService() )->expire_stale();
    }

    public static function ensure_schedule(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
        }
    }

    public static function clear_schedule(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }
}
