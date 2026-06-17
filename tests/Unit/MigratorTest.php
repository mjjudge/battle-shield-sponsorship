<?php

declare(strict_types=1);

namespace BattleShieldSponsorship\Tests\Unit;

use BattleShieldSponsorship\Database\MigrationInterface;

final class MigratorTest {

    public static function run(): void {
        self::test_no_migrations_runs_cleanly();
        self::test_migrations_run_in_order();
        self::test_already_applied_migrations_are_skipped();
    }

    private static function test_no_migrations_runs_cleanly(): void {
        $store = [];
        $migrator = self::make_migrator( [], $store );
        $migrator->run();

        // After an empty migration list the version should be stamped at BSS_VERSION.
        self::assertSame( BSS_VERSION, $store['bss_db_version'] ?? '', 'Empty migration list should stamp BSS_VERSION.' );
    }

    private static function test_migrations_run_in_order(): void {
        $order = [];
        $store = [];

        $a = new class ( $order, 'a' ) implements MigrationInterface {
            public function __construct( private array &$order, private string $tag ) {}
            public function up(): void { $this->order[] = $this->tag; }
        };

        $b = new class ( $order, 'b' ) implements MigrationInterface {
            public function __construct( private array &$order, private string $tag ) {}
            public function up(): void { $this->order[] = $this->tag; }
        };

        $migrator = self::make_migrator(
            [ '0.2.0' => $a, '0.3.0' => $b ],
            $store,
            '0.0.0'
        );
        $migrator->run();

        self::assertSame( [ 'a', 'b' ], $order, 'Migrations should run in version order.' );
    }

    private static function test_already_applied_migrations_are_skipped(): void {
        $order = [];
        $store = [ 'bss_db_version' => '0.2.0' ];

        $a = new class ( $order, 'a' ) implements MigrationInterface {
            public function __construct( private array &$order, private string $tag ) {}
            public function up(): void { $this->order[] = $this->tag; }
        };

        $b = new class ( $order, 'b' ) implements MigrationInterface {
            public function __construct( private array &$order, private string $tag ) {}
            public function up(): void { $this->order[] = $this->tag; }
        };

        $migrator = self::make_migrator(
            [ '0.2.0' => $a, '0.3.0' => $b ],
            $store,
            '0.2.0'
        );
        $migrator->run();

        self::assertSame( [ 'b' ], $order, 'Already-applied migrations should be skipped.' );
    }

    /**
     * Returns a Migrator subclass that uses an in-memory store instead of WP options,
     * and accepts pre-built migration objects rather than class names.
     *
     * @param array<string, MigrationInterface> $migrations
     * @param array<string, mixed>              $store
     */
    private static function make_migrator( array $migrations, array &$store, string $installed = '0.0.0' ): object {
        $store['bss_db_version'] = $installed;

        return new class ( $migrations, $store ) {

            /** @param array<string, MigrationInterface> $migrations */
            public function __construct(
                private array $migrations,
                private array &$store,
            ) {}

            public function run(): void {
                $installed = $this->installed_version();

                foreach ( $this->migrations as $version => $instance ) {
                    if ( version_compare( $installed, $version, '<' ) ) {
                        $instance->up();
                        $this->store['bss_db_version'] = $version;
                        $installed = $version;
                    }
                }

                $this->store['bss_db_version'] = BSS_VERSION;
            }

            public function installed_version(): string {
                return (string) ( $this->store['bss_db_version'] ?? '0.0.0' );
            }
        };
    }

    private static function assertSame( mixed $expected, mixed $actual, string $message ): void {
        if ( $expected !== $actual ) {
            throw new \RuntimeException(
                $message . ' Expected: ' . json_encode( $expected ) . ' Actual: ' . json_encode( $actual )
            );
        }
    }
}
