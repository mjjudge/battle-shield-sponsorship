<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Generates print-ready A4 PDF patches using mPDF.
 *
 * Each page contains one sponsorship: the shield image (if any), the sponsor
 * display name, sponsor text, and logo. A4 landscape, 2-up per page layout
 * is used when generating a batch so it can be cut into individual patches.
 *
 * Requires mPDF installed via composer in the plugin directory.
 * Run: composer require mpdf/mpdf --working-dir=<plugin_dir>
 */
class PatchGenerationService {

    private const A4_PORTRAIT_W  = 210;
    private const A4_PORTRAIT_H  = 297;

    public function generate_for_sponsorship( int $sponsorship_id ): void {
        $sponsorship_service = new SponsorshipService();
        $sponsorship         = $sponsorship_service->get_by_id( $sponsorship_id );

        if ( ! $sponsorship || 'paid' !== (string) $sponsorship->payment_status ) {
            wp_die( esc_html__( 'Sponsorship not found or not paid.', 'battle-shield-sponsorship' ) );
        }

        $patches = [ $this->build_patch_data( $sponsorship, $sponsorship_service ) ];
        $this->output_pdf( $patches, 'patch-' . $sponsorship_id );
    }

    public function generate_for_campaign( int $campaign_id, bool $complete_only = false ): void {
        $sponsorship_service = new SponsorshipService();
        $filters = [
            'campaign_id'    => $campaign_id,
            'payment_status' => 'paid',
        ];
        if ( $complete_only ) {
            $filters['artwork_status'] = 'complete';
        }

        $sponsorships = $sponsorship_service->get_all( $filters );

        if ( empty( $sponsorships ) ) {
            wp_die( esc_html__( 'No sponsorships found for PDF generation.', 'battle-shield-sponsorship' ) );
        }

        $patches = [];
        foreach ( $sponsorships as $s ) {
            $patches[] = $this->build_patch_data( $s, $sponsorship_service );
        }

        $this->output_pdf( $patches, 'patches-campaign-' . $campaign_id );
    }

    private function build_patch_data( object $sponsorship, SponsorshipService $service ): array {
        $shield_service = new ShieldService();
        $items          = $service->get_items( (int) $sponsorship->id );

        $shield_names  = [];
        $shield_images = [];

        foreach ( $items as $item ) {
            $shield = $shield_service->get_by_id( (int) $item->shield_id );
            if ( $shield ) {
                $shield_names[] = (string) $shield->name;
                if ( $shield->image_id ) {
                    $path = get_attached_file( (int) $shield->image_id );
                    if ( $path ) {
                        $shield_images[] = $path;
                    }
                }
            }
        }

        $logo_path = '';
        if ( $sponsorship->logo_attachment_id ) {
            $path = get_attached_file( (int) $sponsorship->logo_attachment_id );
            if ( $path ) {
                $logo_path = $path;
            }
        }

        return [
            'display_name'  => (string) $sponsorship->display_name,
            'sponsor_text'  => (string) ( $sponsorship->sponsor_text ?? '' ),
            'shield_names'  => $shield_names,
            'shield_images' => $shield_images,
            'logo_path'     => $logo_path,
        ];
    }

    private function output_pdf( array $patches, string $filename ): void {
        $mpdf = new \Mpdf\Mpdf( [
            'format'      => 'A4',
            'orientation' => 'P',
            'margin_top'  => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'margin_right' => 10,
        ] );

        $mpdf->SetTitle( 'Battle Shield Sponsorship Patches' );
        $mpdf->SetAuthor( get_option( 'blogname', 'Battle of Evesham' ) );

        foreach ( $patches as $i => $patch ) {
            if ( $i > 0 ) {
                $mpdf->AddPage();
            }
            $mpdf->WriteHTML( $this->render_patch_html( $patch ) );
        }

        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '.pdf"' );
        $mpdf->Output( '', \Mpdf\Output\Destination::DOWNLOAD );
    }

    private function render_patch_html( array $patch ): string {
        $display_name = esc_html( $patch['display_name'] );
        $sponsor_text = esc_html( $patch['sponsor_text'] );
        $shield_names = esc_html( implode( ', ', $patch['shield_names'] ) );

        // Scale font size down for long names so they don't overflow the patch.
        $name_len      = mb_strlen( $patch['display_name'] );
        $name_font     = match ( true ) {
            $name_len > 40 => '12pt',
            $name_len > 30 => '15pt',
            $name_len > 20 => '18pt',
            default        => '22pt',
        };

        $text_len      = mb_strlen( $patch['sponsor_text'] );
        $text_font     = match ( true ) {
            $text_len > 120 => '9pt',
            $text_len > 80  => '10pt',
            default         => '11pt',
        };

        $shield_img_html = '';
        if ( ! empty( $patch['shield_images'][0] ) ) {
            $shield_img_html = '<img src="' . esc_attr( $patch['shield_images'][0] ) . '" style="max-width:180px;max-height:180px;" />';
        }

        $logo_html = '';
        if ( $patch['logo_path'] ) {
            $logo_html = '<img src="' . esc_attr( $patch['logo_path'] ) . '" style="max-width:120px;max-height:80px;" />';
        }

        return '
<style>
body { font-family: sans-serif; margin: 0; padding: 0; }
.patch { border: 2px solid #333; padding: 24px; text-align: center; }
.patch__shield { margin-bottom: 16px; }
.patch__sponsor-name { font-size: ' . $name_font . '; font-weight: bold; margin: 12px 0; }
.patch__shields { font-size: 10pt; color: #555; margin-bottom: 8px; }
.patch__sponsor-text { font-size: ' . $text_font . '; margin: 8px 0; }
.patch__logo { margin-top: 16px; }
</style>
<div class="patch">
  <div class="patch__shield">' . $shield_img_html . '</div>
  <div class="patch__sponsor-name">' . $display_name . '</div>
  <div class="patch__shields">' . $shield_names . '</div>
  ' . ( $sponsor_text ? '<div class="patch__sponsor-text">' . $sponsor_text . '</div>' : '' ) . '
  ' . ( $logo_html ? '<div class="patch__logo">' . $logo_html . '</div>' : '' ) . '
</div>';
    }
}
