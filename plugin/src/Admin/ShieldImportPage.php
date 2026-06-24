<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\ShieldService;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page: import shields from a JSON file and link images from the WordPress
 * media library (or, if not yet there, sideload from a local folder).
 *
 * Image lookup order per shield:
 *   1. Bulk-fetch all _wp_attached_file values at import start, then match by basename.
 *      This avoids LIKE wildcards and works identically on local and live servers.
 *   2. If not found in the library AND an images base folder is configured, copy + register.
 *
 * No filesystem paths are hardcoded — the JSON is uploaded via the form and the
 * images base folder is optional (only needed when images are not already in WP).
 */
class ShieldImportPage {

    private const NONCE_ACTION     = 'bss_shield_import';
    private const PATH_OPTION      = 'bss_shield_images_path';
    private const RESULT_TRANSIENT = 'bss_import_result';

    public function __construct() {
        add_action( 'admin_post_bss_run_shield_import', [ $this, 'handle_import' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_shields' );

        $saved_path = (string) get_option( self::PATH_OPTION, '' );
        $result     = get_transient( self::RESULT_TRANSIENT );
        delete_transient( self::RESULT_TRANSIENT );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Import Shields', 'battle-shield-sponsorship' ) . '</h1>';

        if ( is_array( $result ) ) {
            $this->render_result( $result );
        }

        echo '<p>' . esc_html__( 'Upload shields_import.json to create or update shield records. Images are matched automatically from the WordPress media library — no re-uploading required if they are already there.', 'battle-shield-sponsorship' ) . '</p>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="bss_run_shield_import" />';
        wp_nonce_field( self::NONCE_ACTION );

        echo '<table class="form-table" role="presentation">';

        echo '<tr><th scope="row"><label for="json_file">' . esc_html__( 'JSON file', 'battle-shield-sponsorship' ) . '</label></th>';
        echo '<td><input type="file" name="json_file" id="json_file" accept=".json" required />';
        echo '<p class="description">' . esc_html__( 'Upload shields_import.json.', 'battle-shield-sponsorship' ) . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="images_path">' . esc_html__( 'Images base folder', 'battle-shield-sponsorship' ) . '</label></th>';
        echo '<td><input type="text" name="images_path" id="images_path" class="large-text" value="' . esc_attr( $saved_path ) . '" placeholder="/home/user/shield-images" />';
        echo '<p class="description">' . esc_html__( 'Optional. Only needed if images are NOT already in the WordPress media library. Absolute path to the folder containing royals/ and rebels/ subfolders. Saved for next time.', 'battle-shield-sponsorship' ) . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="update_existing">' . esc_html__( 'Update existing shields', 'battle-shield-sponsorship' ) . '</label></th>';
        echo '<td><input type="checkbox" name="update_existing" id="update_existing" value="1" />';
        echo '<label for="update_existing"> ' . esc_html__( 'Overwrite bio/dates for shields that already exist (matched by name + side). Images are always linked if currently missing, regardless of this setting.', 'battle-shield-sponsorship' ) . '</label></td></tr>';

        echo '</table>';
        submit_button( __( 'Run Import', 'battle-shield-sponsorship' ) );
        echo '</form>';

        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bss-shields' ) ) . '">&larr; ' . esc_html__( 'Back to Shields', 'battle-shield-sponsorship' ) . '</a></p>';
        echo '</div>';
    }

    public function handle_import(): void {
        RequestGuard::require_capability( 'bss_manage_shields' );
        RequestGuard::verify_admin_nonce( self::NONCE_ACTION );

        $images_path     = rtrim( sanitize_text_field( wp_unslash( $_POST['images_path'] ?? '' ) ), '/\\' );
        $update_existing = ! empty( $_POST['update_existing'] );

        if ( '' !== $images_path ) {
            update_option( self::PATH_OPTION, $images_path, false );
        }

        // ── Parse uploaded JSON ───────────────────────────────────────────
        $file = $_FILES['json_file'] ?? null;
        if ( ! $file || ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            $this->finish_with_error( 'No JSON file uploaded.' );
        }

        $json = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        if ( false === $json ) {
            $this->finish_with_error( 'Could not read uploaded file.' );
        }

        $entries = json_decode( $json, true );
        if ( ! is_array( $entries ) ) {
            $this->finish_with_error( 'Invalid JSON: could not parse file.' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // ── Pre-build media library index: basename → attachment ID ───────
        // Fetches all _wp_attached_file values in one query and matches by
        // basename in PHP. Avoids LIKE wildcard escaping issues entirely.
        $media_index = $this->build_media_library_index();

        $service = new ShieldService();

        $created        = 0;
        $updated        = 0;
        $skipped        = 0;
        $images_linked  = 0;
        $images_missing = [];
        $errors         = [];
        $index_size     = count( $media_index );

        foreach ( $entries as $i => $entry ) {
            if ( ! is_array( $entry ) ) {
                $errors[] = "Entry #{$i}: not an object, skipped.";
                continue;
            }

            $name = trim( (string) ( $entry['name'] ?? '' ) );
            if ( '' === $name ) {
                $errors[] = "Entry #{$i}: missing name, skipped.";
                continue;
            }

            $side       = 'rebel' === strtolower( trim( (string) ( $entry['side'] ?? '' ) ) ) ? 'rebels' : 'royals';
            $image_file = trim( (string) ( $entry['image_file'] ?? '' ) );

            $data = [
                'name'            => $name,
                'side'            => $side,
                'description'     => $this->clean_placeholder( (string) ( $entry['bio'] ?? '' ) ),
                'birth_date'      => $this->clean_placeholder( (string) ( $entry['birth'] ?? '' ) ),
                'death_date'      => $this->clean_placeholder( (string) ( $entry['death'] ?? '' ) ),
                'suggested_price' => 100.00,
            ];

            $existing = $service->find_by_name_and_side( $name, $side );

            if ( $existing ) {
                $shield_id = (int) $existing->id;
                if ( $update_existing ) {
                    $service->update( $shield_id, $data );
                    $updated++;
                } else {
                    $skipped++;
                }
                // Always try to resolve + link the image (overwrites any stale value).
                if ( $image_file ) {
                    $attach_id = $this->resolve_image( $image_file, $images_path, $name, $media_index );
                    if ( $attach_id > 0 ) {
                        $service->update( $shield_id, [ 'image_id' => $attach_id ] );
                        $images_linked++;
                        $media_index[ basename( $image_file ) ] = $attach_id;
                    } elseif ( 0 === $attach_id ) {
                        $images_missing[] = basename( $image_file );
                    }
                }
            } else {
                $shield_id = $service->create( $data );
                $created++;
                if ( $image_file ) {
                    $attach_id = $this->resolve_image( $image_file, $images_path, $name, $media_index );
                    if ( $attach_id > 0 ) {
                        $service->update( $shield_id, [ 'image_id' => $attach_id ] );
                        $images_linked++;
                        $media_index[ basename( $image_file ) ] = $attach_id;
                    } elseif ( 0 === $attach_id ) {
                        $images_missing[] = basename( $image_file );
                    }
                }
            }
        }

        $result = compact( 'created', 'updated', 'skipped', 'images_linked', 'images_missing', 'errors', 'index_size' );
        set_transient( self::RESULT_TRANSIENT, $result, 60 );

        wp_safe_redirect( admin_url( 'admin.php?page=bss-shield-import' ) );
        exit;
    }

    /**
     * Returns a map of [ basename => attachment_id ] built from all _wp_attached_file
     * postmeta rows. Uses PHP basename() to strip the year/month folder prefix that
     * WordPress adds (e.g. "2026/06/filename.jpg" → "filename.jpg").
     *
     * @return array<string, int>
     */
    private function build_media_library_index(): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = '_wp_attached_file'"
        );

        $index = [];
        foreach ( $rows ?? [] as $row ) {
            $key = basename( (string) $row->meta_value );
            if ( '' !== $key && ! isset( $index[ $key ] ) ) {
                $index[ $key ] = (int) $row->post_id;
            }
        }
        return $index;
    }

    /**
     * Resolves an image attachment ID:
     * 1. Looks up basename in the pre-built media library index.
     * 2. If not found AND $images_path is set, sideloads from the filesystem.
     *
     * @param  array<string, int> $media_index
     * @return int  > 0 = attachment ID, 0 = not found, -1 = sideload/WP error.
     */
    private function resolve_image( string $image_file, string $images_path, string $shield_name, array $media_index ): int {
        $basename = basename( $image_file );

        if ( isset( $media_index[ $basename ] ) ) {
            return $media_index[ $basename ];
        }

        if ( '' === $images_path ) {
            return 0;
        }

        return $this->sideload_image( $images_path, $image_file, $shield_name );
    }

    /**
     * Copies a file from the local images folder into WP uploads and registers it.
     *
     * @return int  Attachment ID on success, -1 on error, 0 if source file not found.
     */
    private function sideload_image( string $base_path, string $image_file, string $shield_name ): int {
        $full_path = $base_path . DIRECTORY_SEPARATOR . $image_file;

        if ( ! file_exists( $full_path ) ) {
            return 0;
        }

        $upload_dir = wp_upload_dir();
        $filename   = basename( $full_path );
        $dest_path  = $upload_dir['path'] . DIRECTORY_SEPARATOR . wp_unique_filename( $upload_dir['path'], $filename );

        if ( ! copy( $full_path, $dest_path ) ) {
            return -1;
        }

        $mime      = wp_check_filetype( $dest_path )['type'] ?: 'image/jpeg';
        $attach_id = wp_insert_attachment(
            [
                'post_title'     => $shield_name,
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_mime_type' => $mime,
            ],
            $dest_path
        );

        if ( is_wp_error( $attach_id ) ) {
            return -1;
        }

        wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $dest_path ) );

        return $attach_id;
    }

    private function clean_placeholder( string $value ): string {
        $value = trim( $value );
        if ( '' === $value || in_array( strtolower( $value ), [ 'n/a', 'unknown', '-', 'none' ], true ) ) {
            return '';
        }
        if ( false !== stripos( $value, 'we are trying to find' ) ) {
            return '';
        }
        return $value;
    }

    /** @param array<string,mixed> $result */
    private function render_result( array $result ): void {
        $created        = (int) ( $result['created'] ?? 0 );
        $updated        = (int) ( $result['updated'] ?? 0 );
        $skipped        = (int) ( $result['skipped'] ?? 0 );
        $images_linked  = (int) ( $result['images_linked'] ?? 0 );
        $images_missing = (array) ( $result['images_missing'] ?? [] );
        $errors         = (array) ( $result['errors'] ?? [] );
        $index_size     = (int) ( $result['index_size'] ?? -1 );

        $lines = [];
        if ( $created )       { $lines[] = sprintf( _n( '%d shield created.', '%d shields created.', $created, 'battle-shield-sponsorship' ), $created ); }
        if ( $updated )       { $lines[] = sprintf( _n( '%d shield updated.', '%d shields updated.', $updated, 'battle-shield-sponsorship' ), $updated ); }
        if ( $skipped )       { $lines[] = sprintf( _n( '%d shield skipped (already exists).', '%d shields skipped (already exist).', $skipped, 'battle-shield-sponsorship' ), $skipped ); }
        if ( $images_linked ) { $lines[] = sprintf( _n( '%d image linked.', '%d images linked.', $images_linked, 'battle-shield-sponsorship' ), $images_linked ); }
        if ( $index_size >= 0 ) { $lines[] = sprintf( __( '(Media library index: %d files found.)', 'battle-shield-sponsorship' ), $index_size ); }
        if ( ! $lines )       { $lines[] = __( 'Import complete.', 'battle-shield-sponsorship' ); }

        $class = ( $errors || $images_missing ) ? 'notice notice-warning' : 'notice notice-success';
        echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( implode( '  ', $lines ) ) . '</p>';

        if ( $images_missing ) {
            echo '<p><strong>' . esc_html__( 'Images not found in media library or on disk:', 'battle-shield-sponsorship' ) . '</strong></p><ul>';
            foreach ( $images_missing as $f ) {
                echo '<li>' . esc_html( (string) $f ) . '</li>';
            }
            echo '</ul>';
        }

        if ( $errors ) {
            echo '<p><strong>' . esc_html__( 'Errors:', 'battle-shield-sponsorship' ) . '</strong></p><ul>';
            foreach ( $errors as $e ) {
                echo '<li>' . esc_html( (string) $e ) . '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    /** @param non-empty-string $message */
    private function finish_with_error( string $message ): never {
        set_transient( self::RESULT_TRANSIENT, [ 'errors' => [ $message ] ], 60 );
        wp_safe_redirect( admin_url( 'admin.php?page=bss-shield-import' ) );
        exit;
    }
}
