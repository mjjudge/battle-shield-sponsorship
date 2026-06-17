<?php

namespace BattleShieldSponsorship\Admin;

defined( 'ABSPATH' ) || exit;

class Menu {

    public const TOP_SLUG = 'battle-shield-sponsorship';

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'register_pages' ] );
    }

    public function register_pages(): void {
        add_menu_page(
            __( 'Shield Sponsorship', 'battle-shield-sponsorship' ),
            __( 'Shield Sponsorship', 'battle-shield-sponsorship' ),
            'bss_access',
            self::TOP_SLUG,
            '',
            $this->shield_icon(),
            56
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Dashboard', 'battle-shield-sponsorship' ),
            __( 'Dashboard', 'battle-shield-sponsorship' ),
            'bss_access',
            self::TOP_SLUG,
            [ new DashboardPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Campaigns', 'battle-shield-sponsorship' ),
            __( 'Campaigns', 'battle-shield-sponsorship' ),
            'bss_manage_campaigns',
            'bss-campaigns',
            [ new CampaignListPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Campaign Editor', 'battle-shield-sponsorship' ),
            __( 'Campaign Editor', 'battle-shield-sponsorship' ),
            'bss_manage_campaigns',
            'bss-campaign-edit',
            [ new CampaignEditPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Shields', 'battle-shield-sponsorship' ),
            __( 'Shields', 'battle-shield-sponsorship' ),
            'bss_manage_shields',
            'bss-shields',
            [ new ShieldListPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Shield Editor', 'battle-shield-sponsorship' ),
            __( 'Shield Editor', 'battle-shield-sponsorship' ),
            'bss_manage_shields',
            'bss-shield-edit',
            [ new ShieldEditPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Sponsorships', 'battle-shield-sponsorship' ),
            __( 'Sponsorships', 'battle-shield-sponsorship' ),
            'bss_manage_sponsorships',
            'bss-sponsorships',
            [ new SponsorshipListPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Sponsorship Detail', 'battle-shield-sponsorship' ),
            __( 'Sponsorship Detail', 'battle-shield-sponsorship' ),
            'bss_manage_sponsorships',
            'bss-sponsorship-view',
            [ new SponsorshipViewPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Contacts', 'battle-shield-sponsorship' ),
            __( 'Contacts', 'battle-shield-sponsorship' ),
            'bss_manage_contacts',
            'bss-contacts',
            [ new ContactListPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Contact Editor', 'battle-shield-sponsorship' ),
            __( 'Contact Editor', 'battle-shield-sponsorship' ),
            'bss_manage_contacts',
            'bss-contact-edit',
            [ new ContactEditPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Manual Sponsorship', 'battle-shield-sponsorship' ),
            __( 'Manual Sponsorship', 'battle-shield-sponsorship' ),
            'bss_manage_sponsorships',
            'bss-manual-sponsorship',
            [ new ManualSponsorshipPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Refunds', 'battle-shield-sponsorship' ),
            __( 'Refunds', 'battle-shield-sponsorship' ),
            'bss_process_refunds',
            'bss-refunds',
            [ new RefundPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Patch Generator', 'battle-shield-sponsorship' ),
            __( 'Patch Generator', 'battle-shield-sponsorship' ),
            'bss_generate_patches',
            'bss-patches',
            [ new PatchGeneratorPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Reports', 'battle-shield-sponsorship' ),
            __( 'Reports', 'battle-shield-sponsorship' ),
            'bss_manage_sponsorships',
            'bss-reports',
            [ new ReportingPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Email Templates', 'battle-shield-sponsorship' ),
            __( 'Email Templates', 'battle-shield-sponsorship' ),
            'bss_manage_settings',
            'bss-email-templates',
            [ new EmailTemplatesPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Logs', 'battle-shield-sponsorship' ),
            __( 'Logs', 'battle-shield-sponsorship' ),
            'bss_manage_sponsorships',
            'bss-logs',
            [ new LogsPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Settings', 'battle-shield-sponsorship' ),
            __( 'Settings', 'battle-shield-sponsorship' ),
            'bss_manage_settings',
            'bss-settings',
            [ new SettingsPage(), 'render' ]
        );

        add_submenu_page(
            self::TOP_SLUG,
            __( 'Help', 'battle-shield-sponsorship' ),
            __( 'Help', 'battle-shield-sponsorship' ),
            'bss_access',
            'bss-help',
            [ new HelpPage(), 'render' ]
        );
    }

    private function shield_icon(): string {
        $path = BSS_PLUGIN_DIR . 'assets/images/shield-menu-icon.svg';
        if ( ! file_exists( $path ) ) {
            return 'dashicons-shield';
        }
        return 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( $path ) );
    }
}
