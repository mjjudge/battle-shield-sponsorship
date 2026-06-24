<?php

namespace BattleShieldSponsorship\Database;

defined( 'ABSPATH' ) || exit;

class Migrator {

    private const DB_VERSION_OPTION = 'bss_db_version';

    /**
     * Ordered list of schema migrations keyed by the plugin version that introduced them.
     *
     * @var array<string, class-string<MigrationInterface>>
     */
    private const MIGRATIONS = [
        '0.0.1' => Migrations\CreateCampaignsTable::class,
        '0.0.2' => Migrations\CreateShieldsTable::class,
        '0.0.3' => Migrations\CreateContactsTable::class,
        '0.0.4' => Migrations\CreateSponsorshipsTable::class,
        '0.0.5' => Migrations\CreateSponsorshipItemsTable::class,
        '0.0.6' => Migrations\CreateReservationsTable::class,
        '0.0.7' => Migrations\CreateUploadTokensTable::class,
        '0.0.8' => Migrations\CreateEmailLogTable::class,
        '0.0.9' => Migrations\CreateAuditLogTable::class,
        '0.1.1' => Migrations\AddEventDatesToCampaigns::class,
        '0.1.2' => Migrations\UpdateShieldSides::class,
        '0.1.3' => Migrations\AddContactCompanyAndAddress::class,
        '0.1.4' => Migrations\AddShieldBiographyFields::class,
        '0.1.5' => Migrations\SetDefaultShieldPrice::class,
        '0.1.6' => Migrations\AddLogoNotNeededToSponsorships::class,
        '0.1.7' => Migrations\RecalculateArtworkStatus::class,
    ];

    public function run(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $installed = self::installed_version();

        foreach ( self::MIGRATIONS as $version => $class ) {
            if ( version_compare( $installed, $version, '<' ) ) {
                ( new $class() )->up();
                update_option( self::DB_VERSION_OPTION, $version, true );
                $installed = $version;
            }
        }

        // Always stamp the current plugin version after a successful run.
        update_option( self::DB_VERSION_OPTION, BSS_VERSION, true );
    }

    public static function installed_version(): string {
        return (string) get_option( self::DB_VERSION_OPTION, '0.0.0' );
    }
}
