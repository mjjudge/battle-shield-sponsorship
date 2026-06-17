<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Unit/MigratorTest.php';
require_once __DIR__ . '/Unit/StripeWebhookProcessorTest.php';
require_once __DIR__ . '/Unit/TemplateRendererTest.php';

$tests = [
    'MigratorTest'               => [ \BattleShieldSponsorship\Tests\Unit\MigratorTest::class, 'run' ],
    'StripeWebhookProcessorTest' => [ \BattleShieldSponsorship\Tests\Unit\StripeWebhookProcessorTest::class, 'run' ],
    'TemplateRendererTest'       => [ \BattleShieldSponsorship\Tests\Unit\TemplateRendererTest::class, 'run' ],
];

$failed = 0;
foreach ( $tests as $name => $callable ) {
    try {
        $callable();
        echo "[PASS] {$name}\n";
    } catch ( \Throwable $e ) {
        $failed++;
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
    }
}

exit( $failed > 0 ? 1 : 0 );
