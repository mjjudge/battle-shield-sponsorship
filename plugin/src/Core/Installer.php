<?php

namespace BattleShieldSponsorship\Core;

defined( 'ABSPATH' ) || exit;

class Installer {

    public static function activate(): void {
        Roles::register();
        ( new \BattleShieldSponsorship\Database\Migrator() )->run();
        ( new \BattleShieldSponsorship\Services\ReservationCleanupService() )->ensure_schedule();
        ( new \BattleShieldSponsorship\Services\ReminderService() )->ensure_schedule();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        // Preserve all data on deactivation.
        ( new \BattleShieldSponsorship\Services\ReservationCleanupService() )->clear_schedule();
        ( new \BattleShieldSponsorship\Services\ReminderService() )->clear_schedule();
        flush_rewrite_rules();
    }
}
