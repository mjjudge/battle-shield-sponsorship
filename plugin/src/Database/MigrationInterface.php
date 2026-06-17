<?php

namespace BattleShieldSponsorship\Database;

defined( 'ABSPATH' ) || exit;

interface MigrationInterface {

    public function up(): void;
}
