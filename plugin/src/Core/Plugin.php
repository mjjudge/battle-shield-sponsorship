<?php

namespace BattleShieldSponsorship\Core;

defined( 'ABSPATH' ) || exit;

class Plugin {

    public function boot(): void {
        Roles::boot();

        // Self-heal: re-sync capabilities if an admin is missing them (e.g. after plugin update).
        $admin = get_role( 'administrator' );
        if ( $admin && ! $admin->has_cap( 'bss_access' ) ) {
            Roles::register();
        }

        // Auto-run pending schema migrations without requiring deactivation/reactivation.
        if ( is_admin() && version_compare( \BattleShieldSponsorship\Database\Migrator::installed_version(), BSS_VERSION, '<' ) ) {
            add_action( 'admin_init', static function (): void {
                ( new \BattleShieldSponsorship\Database\Migrator() )->run();
            } );
        }

        load_plugin_textdomain(
            'battle-shield-sponsorship',
            false,
            dirname( plugin_basename( BSS_PLUGIN_FILE ) ) . '/languages'
        );

        $this->register_admin();
        $this->register_rest();
        $this->register_public();
        $this->register_cron_hooks();
        $this->register_event_hooks();
    }

    private function register_admin(): void {
        if ( ! is_admin() ) {
            return;
        }

        ( new \BattleShieldSponsorship\Admin\Menu() )->register();

        // Instantiate page classes that register admin_post handlers in their constructors.
        new \BattleShieldSponsorship\Admin\CampaignEditPage();
        new \BattleShieldSponsorship\Admin\ShieldEditPage();
        new \BattleShieldSponsorship\Admin\SponsorshipViewPage();
        new \BattleShieldSponsorship\Admin\ContactEditPage();
        new \BattleShieldSponsorship\Admin\ManualSponsorshipPage();
        new \BattleShieldSponsorship\Admin\RefundPage();
        new \BattleShieldSponsorship\Admin\PatchGeneratorPage();
        new \BattleShieldSponsorship\Admin\ReportingPage();
        new \BattleShieldSponsorship\Admin\EmailTemplatesPage();
        new \BattleShieldSponsorship\Admin\SettingsPage();

        new \BattleShieldSponsorship\Admin\CampaignListPage();
    }

    private function register_rest(): void {
        add_action( 'rest_api_init', static function (): void {
            ( new \BattleShieldSponsorship\Rest\StripeWebhookRoute() )->register();
        } );
    }

    private function register_public(): void {
        ( new \BattleShieldSponsorship\BSPublic\ShopShortcode() )->register();
        ( new \BattleShieldSponsorship\BSPublic\CheckoutController() )->register();
        ( new \BattleShieldSponsorship\BSPublic\EditTokenPage() )->register();
        ( new \BattleShieldSponsorship\BSPublic\PaymentPagesHandler() )->register();
    }

    private function register_cron_hooks(): void {
        ( new \BattleShieldSponsorship\Services\ReservationCleanupService() )->ensure_schedule();
        ( new \BattleShieldSponsorship\Services\ReminderService() )->ensure_schedule();
    }

    private function register_event_hooks(): void {
        add_action( 'bss_payment_confirmed', static function ( int $sponsorship_id ): void {
            ( new \BattleShieldSponsorship\Mail\TreasurerNotifier() )->send( $sponsorship_id );
        } );
    }
}
