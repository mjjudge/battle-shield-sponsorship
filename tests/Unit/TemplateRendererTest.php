<?php

declare(strict_types=1);

namespace BattleShieldSponsorship\Tests\Unit;

final class TemplateRendererTest {

    public static function run(): void {
        self::require_class();
        self::test_subject_replaces_campaign_name_tag();
        self::test_subject_falls_back_to_default_for_unknown_key();
        self::test_body_replaces_sponsor_name_tag();
        self::test_body_wraps_in_html_layout();
        self::test_all_known_template_keys_produce_non_empty_subjects();
    }

    private static function require_class(): void {
        require_once dirname( __DIR__ ) . '/../plugin/src/Mail/TemplateRenderer.php';
    }

    private static function test_subject_replaces_campaign_name_tag(): void {
        $renderer = new \BattleShieldSponsorship\Mail\TemplateRenderer();
        $subject  = $renderer->render_subject( 'sponsorship_confirmation', [ 'campaign_name' => 'Evesham 2026' ] );

        self::assertContains( 'Evesham 2026', $subject, 'Subject should replace {campaign_name}' );
        self::assertNotContains( '{campaign_name}', $subject, 'Subject should not contain unreplaced tag' );
    }

    private static function test_subject_falls_back_to_default_for_unknown_key(): void {
        $renderer = new \BattleShieldSponsorship\Mail\TemplateRenderer();
        $subject  = $renderer->render_subject( 'nonexistent_key', [] );

        self::assertNotEmpty( $subject, 'Unknown key should still return a non-empty subject' );
    }

    private static function test_body_replaces_sponsor_name_tag(): void {
        $renderer = new \BattleShieldSponsorship\Mail\TemplateRenderer();
        $body     = $renderer->render_body( 'sponsorship_confirmation', [ 'sponsor_name' => 'Jane Smith', 'campaign_name' => 'Test' ] );

        self::assertContains( 'Jane Smith', $body, 'Body should replace {sponsor_name}' );
        self::assertNotContains( '{sponsor_name}', $body, 'Body should not contain unreplaced sponsor_name tag' );
    }

    private static function test_body_wraps_in_html_layout(): void {
        $renderer = new \BattleShieldSponsorship\Mail\TemplateRenderer();
        $body     = $renderer->render_body( 'artwork_reminder', [ 'sponsor_name' => 'Test' ] );

        self::assertContains( '<!DOCTYPE html>', $body, 'Body should be wrapped in HTML layout' );
        self::assertContains( '</html>', $body, 'Body should include closing html tag' );
    }

    private static function test_all_known_template_keys_produce_non_empty_subjects(): void {
        $renderer = new \BattleShieldSponsorship\Mail\TemplateRenderer();
        $keys     = [ 'sponsorship_confirmation', 'artwork_reminder', 'final_artwork_reminder', 'refund_confirmation', 'gdpr_removal' ];

        foreach ( $keys as $key ) {
            $subject = $renderer->render_subject( $key, [] );
            self::assertNotEmpty( $subject, "Template '{$key}' should produce a non-empty subject" );
        }
    }

    private static function assertContains( string $needle, string $haystack, string $message ): void {
        if ( false === strpos( $haystack, $needle ) ) {
            throw new \RuntimeException( $message . ' (needle: ' . json_encode( $needle ) . ')' );
        }
    }

    private static function assertNotContains( string $needle, string $haystack, string $message ): void {
        if ( false !== strpos( $haystack, $needle ) ) {
            throw new \RuntimeException( $message . ' (needle found in output)' );
        }
    }

    private static function assertNotEmpty( string $value, string $message ): void {
        if ( '' === $value ) {
            throw new \RuntimeException( $message );
        }
    }
}
