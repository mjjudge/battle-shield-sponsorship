<?php

namespace BattleShieldSponsorship\Core;

defined( 'ABSPATH' ) || exit;

class Roles {

    /**
     * Capabilities granted to each plugin role.
     *
     * @var array<string, array<string, bool>>
     */
    private const ROLE_CAPS = [
        'bss_manager' => [
            'read'                => true,
            'bss_access'          => true,
            'bss_manage_shields'  => true,
            'bss_manage_campaigns'     => true,
            'bss_manage_sponsorships'  => true,
            'bss_manage_contacts'      => true,
            'bss_generate_patches'     => true,
            'bss_manage_settings'      => false,
            'bss_process_refunds'      => false,
            'bss_manage_gdpr'          => false,
        ],
        'bss_admin' => [
            'read'                => true,
            'bss_access'          => true,
            'bss_manage_shields'  => true,
            'bss_manage_campaigns'     => true,
            'bss_manage_sponsorships'  => true,
            'bss_manage_contacts'      => true,
            'bss_generate_patches'     => true,
            'bss_manage_settings'      => true,
            'bss_process_refunds'      => true,
            'bss_manage_gdpr'          => true,
        ],
    ];

    private const ALL_CAPS = [
        'bss_access',
        'bss_manage_shields',
        'bss_manage_campaigns',
        'bss_manage_sponsorships',
        'bss_manage_contacts',
        'bss_generate_patches',
        'bss_manage_settings',
        'bss_process_refunds',
        'bss_manage_gdpr',
    ];

    /**
     * Register runtime hooks on every page load.
     * Grants bss_manager capabilities to WordPress Editors dynamically without
     * permanently modifying the editor role in the database.
     */
    public static function boot(): void {
        add_filter(
            'user_has_cap',
            static function ( array $allcaps, array $caps, array $args, \WP_User $user ): array {
                if ( ! in_array( 'editor', $user->roles, true ) ) {
                    return $allcaps;
                }
                foreach ( self::ROLE_CAPS['bss_manager'] as $cap => $granted ) {
                    if ( $granted ) {
                        $allcaps[ $cap ] = true;
                    }
                }
                return $allcaps;
            },
            10,
            4
        );
    }

    public static function register(): void {
        foreach ( self::ROLE_CAPS as $role => $caps ) {
            $display_name = match ( $role ) {
                'bss_manager' => __( 'Shield Sponsorship Manager', 'battle-shield-sponsorship' ),
                'bss_admin'   => __( 'Shield Sponsorship Admin', 'battle-shield-sponsorship' ),
                default       => $role,
            };

            // add_role() is a no-op when the role already exists.
            add_role( $role, $display_name, $caps );

            // Keep existing role capabilities in sync as the plugin evolves.
            $role_obj = get_role( $role );
            if ( $role_obj ) {
                foreach ( self::ALL_CAPS as $cap ) {
                    if ( ! empty( $caps[ $cap ] ) ) {
                        $role_obj->add_cap( $cap );
                    } else {
                        $role_obj->remove_cap( $cap );
                    }
                }
            }
        }

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( self::ALL_CAPS as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }

    /** Reserved for uninstall flow — not called on deactivation. */
    public static function remove(): void {
        foreach ( array_keys( self::ROLE_CAPS ) as $role ) {
            remove_role( $role );
        }

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( self::ALL_CAPS as $cap ) {
                $admin->remove_cap( $cap );
            }
        }
    }
}
