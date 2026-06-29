<?php

namespace BattleShieldSponsorship\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Generates print-ready A4 portrait PDF patches using mPDF.
 *
 * One patch per shield. A sponsorship covering three shields produces a
 * three-page PDF — each page shows the shield's army / person name in the
 * fixed header, and the sponsor's brand details in the bordered box below.
 *
 * Custom fonts (place TTF files in plugin/assets/fonts/):
 *   Luminari-Regular.ttf    — header "Royal/Rebel Army" and footer URL
 *   MyriadPro-Regular.ttf   — body text (converted from OTF via fonttools)
 *   MyriadPro-Bold.ttf      — bold body text
 *   MyriadPro-BoldItalic.ttf
 * Falls back to mPDF built-in DejaVu fonts when files are absent.
 */
class PatchGenerationService {

    // ── Brand colours ─────────────────────────────────────────────────────────
    private const RED   = '#bd0e1d';
    private const BLUE  = '#0e53a1';
    private const WHITE = '#FFFFFF';

    // ── A4 portrait: margin_top=10, margin_bottom=10 → 190×277 mm content area
    // The table fills PAGE_H; footer URL is a fixed-position element at 15 mm
    // from the physical page bottom (= the last 5 mm of the content area).
    private const PAGE_W = 190;
    private const PAGE_H = 272; // table height; 277 - 5 mm reserved for footer

    // ── Sponsor box ───────────────────────────────────────────────────────────
    private const BOX_W_PCT = 97;  // % of PAGE_W
    private const BOX_H     = 189; // mm
    private const BOX_PAD_V = 7;   // mm top + bottom inside border
    private const BOX_PAD_H = 6;   // mm left + right inside border
    private const BOX_BORDER = '2pt solid #000000';

    // ── Fixed-height page rows (must sum exactly to PAGE_H = 272 mm) ──────────
    //
    //   ROW_HEADER   20
    //   ROW_GAP_1     2
    //   ROW_NAME     17  (dynamic, ~17 mm default)
    //   ROW_SUPPORT  14  (11 mm text + 3 mm padding-bottom below text)
    //   ROW_GAP_2    22  (air gap above box top)
    //   ROW_BOX       186   (BOX_H — sponsor box; 10 mm taller to compensate)
    //   ─────────────── 272 mm ✓
    //
    //   Footer "BattleofEvesham.co.uk" is positioned:fixed; bottom:8mm
    //   so it sits close to the physical page bottom on every page.
    //
    private const ROW_HEADER  = 20;
    private const ROW_GAP_1   = 2;
    private const ROW_NAME    = 17;
    private const ROW_SUPPORT = 14;  // 11 mm text + 3 mm padding-bottom below text
    private const ROW_GAP_2   = 22;  // air gap between 'is supported by' and box top
    private const ROW_FOOTER_GAP  = 8;   // empty row below box — fixed-position footer lives here

    // ── Type sizes (pt) ───────────────────────────────────────────────────────
    private const PT_ARMY           = 46;  // 32 × 1.44 — "Royal Army" / "Rebel Army"
    private const PT_NAME_BASE      = 44;  // 29 × 1.51 — baron/royal name (default)
    private const PT_SUPPORT        = 44;  // always matches PT_NAME_BASE
    private const PT_FOOTER         = 39;  // 24 × 1.61 — BattleofEvesham.co.uk
    private const PT_DISPLAY_IN_BOX = 58;  // 29 × 2    — sponsor display name in box
    private const PT_TEXT_MIN       = 48;  // 24 × 2    — sponsor text floor
    private const PT_TEXT_MAX       = 56;  // 28 × 2    — sponsor text ceiling
    private const PT_CONTACT        = 42;  // sponsor URL / phone (28 × 1.5)

    // ── Logo constraints ──────────────────────────────────────────────────────
    // Max width = inner box width (147 mm ≤ 75 % of A4 = 157.5 mm).
    // Max height = 40 % of A4 (119 mm), further capped by available space.
    private const LOGO_MAX_H_ABS = 119;
    private const LOGO_MIN_H     = 40;

    private const SIDE_LABELS = [
        'royals' => 'Royal Army',
        'rebels' => 'Rebel Army',
        'other'  => '',
    ];

    // Font families resolved once per batch; reused across render_* methods.
    private string $display_font = 'dejavuserif, serif';
    private string $text_font    = 'dejavusans, sans-serif';

    // ── Public API ────────────────────────────────────────────────────────────

    public function generate_for_sponsorship( int $sponsorship_id ): void {
        $service     = new SponsorshipService();
        $sponsorship = $service->get_by_id( $sponsorship_id );

        if ( ! $sponsorship || 'paid' !== (string) $sponsorship->payment_status ) {
            wp_die( esc_html__( 'Sponsorship not found or not paid.', 'battle-shield-sponsorship' ) );
        }

        $patches = $this->build_patches( $sponsorship );

        if ( empty( $patches ) ) {
            wp_die( esc_html__( 'No shields found for this sponsorship.', 'battle-shield-sponsorship' ) );
        }

        $label = sanitize_file_name( $patches[0]['shield_name'] ?: (string) $sponsorship_id );
        $this->stream_pdf( $patches, 'patch-' . $label );
    }

    public function preview_for_sponsorship( int $sponsorship_id ): void {
        $service     = new SponsorshipService();
        $sponsorship = $service->get_by_id( $sponsorship_id );

        if ( ! $sponsorship || 'paid' !== (string) $sponsorship->payment_status ) {
            wp_die( esc_html__( 'Preview unavailable.', 'battle-shield-sponsorship' ) );
        }

        $patches = $this->build_patches( $sponsorship );
        if ( empty( $patches ) ) {
            wp_die( esc_html__( 'No shields found for this sponsorship.', 'battle-shield-sponsorship' ) );
        }

        $this->build_mpdf( $patches )
             ->Output( 'patch-preview.pdf', \Mpdf\Output\Destination::INLINE );
    }

    public function generate_for_campaign( int $campaign_id, bool $complete_only = false ): void {
        $service      = new SponsorshipService();
        $sponsorships = $service->get_all( $this->campaign_filters( $campaign_id, $complete_only ) );

        if ( empty( $sponsorships ) ) {
            wp_die( esc_html__( 'No sponsorships found for PDF generation.', 'battle-shield-sponsorship' ) );
        }

        $patches = [];
        foreach ( $sponsorships as $s ) {
            foreach ( $this->build_patches( $s ) as $p ) {
                $patches[] = $p;
            }
        }

        $order = [ 'royals' => 0, 'rebels' => 1, 'other' => 2 ];
        usort( $patches, static function ( array $a, array $b ) use ( $order ): int {
            $sa = $order[ $a['shield_side'] ] ?? 9;
            $sb = $order[ $b['shield_side'] ] ?? 9;
            return $sa !== $sb ? $sa - $sb : strcmp( $a['shield_name'], $b['shield_name'] );
        } );

        $this->stream_pdf( $patches, 'patches-campaign-' . $campaign_id );
    }

    public function generate_zip_for_campaign( int $campaign_id, bool $complete_only = false ): void {
        if ( ! class_exists( '\ZipArchive' ) ) {
            wp_die( esc_html__( 'ZipArchive PHP extension is not available on this server.', 'battle-shield-sponsorship' ) );
        }

        $service      = new SponsorshipService();
        $sponsorships = $service->get_all( $this->campaign_filters( $campaign_id, $complete_only ) );

        if ( empty( $sponsorships ) ) {
            wp_die( esc_html__( 'No sponsorships found for ZIP generation.', 'battle-shield-sponsorship' ) );
        }

        $tmp_dir = trailingslashit( sys_get_temp_dir() ) . 'bss-patches-' . $campaign_id . '-' . time();
        wp_mkdir_p( $tmp_dir );

        $pdf_files = [];

        foreach ( $sponsorships as $s ) {
            foreach ( $this->build_patches( $s ) as $patch ) {
                $base = 'patch-' . sanitize_file_name( $patch['shield_name'] ?: (string) $s->id );
                $name = $base . '.pdf';
                $n    = 2;
                while ( isset( $pdf_files[ $name ] ) ) {
                    $name = $base . '-' . $n++ . '.pdf';
                }
                $pdf_files[ $name ] = $tmp_dir . '/' . $name;
                $this->write_pdf_to_file( [ $patch ], $pdf_files[ $name ] );
            }
        }

        $zip_path = $tmp_dir . '.zip';
        $zip      = new \ZipArchive();
        $zip->open( $zip_path, \ZipArchive::CREATE );
        foreach ( $pdf_files as $name => $path ) {
            $zip->addFile( $path, $name );
        }
        $zip->close();

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="patches-campaign-' . $campaign_id . '.zip"' );
        header( 'Content-Length: ' . (string) filesize( $zip_path ) );
        readfile( $zip_path );

        foreach ( $pdf_files as $path ) {
            @unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
        @unlink( $zip_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        @rmdir( $tmp_dir );   // phpcs:ignore WordPress.PHP.NoSilencedErrors
    }

    // ── Data assembly ─────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> One entry per shield item. */
    private function build_patches( object $sponsorship ): array {
        $shield_svc = new ShieldService();
        $service    = new SponsorshipService();
        $items      = $service->get_items( (int) $sponsorship->id );

        $logo_path = '';
        if ( ! empty( $sponsorship->logo_attachment_id ) ) {
            $file = get_attached_file( (int) $sponsorship->logo_attachment_id );
            if ( $file ) {
                $logo_path = $file;
            }
        }

        $patches = [];
        foreach ( $items as $item ) {
            $shield    = $shield_svc->get_by_id( (int) $item->shield_id );
            $patches[] = [
                'shield_name'   => $shield ? (string) $shield->name : '',
                'shield_side'   => $shield ? (string) $shield->side : 'other',
                'display_name'  => (string) $sponsorship->display_name,
                'sponsor_text'  => (string) ( $sponsorship->sponsor_text ?? '' ),
                'sponsor_url'   => (string) ( $sponsorship->sponsor_url ?? '' ),
                'sponsor_phone' => (string) ( $sponsorship->sponsor_phone ?? '' ),
                'logo_path'     => $logo_path,
            ];
        }

        return $patches;
    }

    /** @return array<string, mixed> */
    private function campaign_filters( int $campaign_id, bool $complete_only ): array {
        $f = [ 'campaign_id' => $campaign_id, 'payment_status' => 'paid' ];
        if ( $complete_only ) {
            $f['artwork_status'] = 'complete';
        }
        return $f;
    }

    // ── PDF construction ──────────────────────────────────────────────────────

    private function stream_pdf( array $patches, string $filename ): void {
        $this->build_mpdf( $patches )
             ->Output( sanitize_file_name( $filename ) . '.pdf', \Mpdf\Output\Destination::DOWNLOAD );
    }

    private function write_pdf_to_file( array $patches, string $dest ): void {
        $this->build_mpdf( $patches )->Output( $dest, \Mpdf\Output\Destination::FILE );
    }

    private function build_mpdf( array $patches ): \Mpdf\Mpdf {
        $font_dir = BSS_PLUGIN_DIR . 'assets/fonts/';
        $font_cfg = $this->resolve_fonts( $font_dir );

        $tmp_dir = trailingslashit( sys_get_temp_dir() ) . 'mpdf-bss';
        wp_mkdir_p( $tmp_dir );

        $config = [
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_top'    => 10,
            'margin_bottom' => 10,
            'margin_left'   => 10,
            'margin_right'  => 10,
            'default_font'  => 'dejavusans',
            'tempDir'       => $tmp_dir,
        ];

        if ( ! empty( $font_cfg ) ) {
            $config['fontDir']  = $font_cfg['fontDir'];
            $config['fontdata'] = $font_cfg['fontdata'];
        }

        $mpdf = new \Mpdf\Mpdf( $config );
        $mpdf->SetTitle( 'Battle Shield Sponsorship Patches' );
        $mpdf->SetAuthor( (string) get_option( 'blogname', 'Battle of Evesham' ) );

        $mpdf->WriteHTML( $this->patch_css(), \Mpdf\HTMLParserMode::HEADER_CSS );

        foreach ( $patches as $i => $patch ) {
            if ( $i > 0 ) {
                $mpdf->AddPage();
            }
            $mpdf->WriteHTML( $this->render_patch_body( $patch ), \Mpdf\HTMLParserMode::HTML_BODY );
        }

        return $mpdf;
    }

    /**
     * Maps TTF files in assets/fonts/ to mPDF font families.
     * All four variants (R B I BI) are registered to prevent mPDF fallback.
     *
     * @return array<string, mixed>
     */
    private function resolve_fonts( string $font_dir ): array {
        $luminari_r = 'Luminari-Regular.ttf';
        $myriad_r   = 'MyriadPro-Regular.ttf';
        $myriad_b   = 'MyriadPro-Bold.ttf';
        $myriad_bi  = 'MyriadPro-BoldItalic.ttf';

        $has_luminari = file_exists( $font_dir . $luminari_r );
        $has_myriad   = file_exists( $font_dir . $myriad_r ) && file_exists( $font_dir . $myriad_b );

        if ( ! $has_luminari && ! $has_myriad ) {
            return [];
        }

        $fontdata = [];

        if ( $has_luminari ) {
            $fontdata['luminari'] = [
                'R'  => $luminari_r,
                'B'  => $luminari_r,
                'I'  => $luminari_r,
                'BI' => $luminari_r,
            ];
            $this->display_font = 'luminari, dejavuserif, serif';
        }

        if ( $has_myriad ) {
            $fontdata['myriadpro'] = [
                'R'  => $myriad_r,
                'B'  => $myriad_b,
                'I'  => $myriad_r,
                'BI' => file_exists( $font_dir . $myriad_bi ) ? $myriad_bi : $myriad_b,
            ];
            $this->text_font = 'myriadpro, dejavusans, sans-serif';
        }

        return [ 'fontDir' => [ $font_dir ], 'fontdata' => $fontdata ];
    }

    // ── Rendering ─────────────────────────────────────────────────────────────

    private function patch_css(): string {
        return '
body  { margin:0; padding:0; }
table { border-collapse:collapse; }
td    { padding:0; margin:0; vertical-align:top; }
img   { border:0; display:block; }
p     { margin:0; padding:0; }
';
    }

    private function render_patch_body( array $patch ): string {
        $df = $this->display_font;
        $tf = $this->text_font;

        $side_label  = self::SIDE_LABELS[ $patch['shield_side'] ] ?? '';
        $shield_name = esc_html( (string) $patch['shield_name'] );
        $name_pt     = $this->name_font_pt( mb_strlen( (string) $patch['shield_name'] ) );
        // Fit name row to actual font size; box grows to fill the freed space.
        $row_name_h  = max( 12, (int) ceil( $name_pt * 0.353 ) );
        $row_box_h   = self::PAGE_H - self::ROW_HEADER - self::ROW_GAP_1
                       - $row_name_h - self::ROW_SUPPORT - self::ROW_GAP_2 - self::ROW_FOOTER_GAP;

        $box_w_mm    = (int) round( self::PAGE_W * self::BOX_W_PCT / 100 ); // 184 mm
        $box_inner_w = $box_w_mm - ( self::BOX_PAD_H * 2 );                  // 160 mm
        $box_inner_h = $row_box_h - ( self::BOX_PAD_V * 2 );

        $layout       = $this->fit_sponsor_box( $patch, $box_inner_h );
        $sponsor_rows = $this->render_sponsor_rows( $patch, $tf, $box_inner_w, $layout );

        // Footer: fixed-position at exactly 8 mm from the physical page bottom.
        $footer = '<div style="position:fixed; bottom:0mm; left:0mm; right:0mm;'
            . ' text-align:center; font-family:' . $df . '; font-size:' . self::PT_FOOTER . 'pt;'
            . ' font-style:italic; color:' . self::RED . '; background:' . self::WHITE . '; padding:1mm 0;">'
            . 'BattleofEvesham.co.uk</div>';

        return $footer . '
<table style="width:100%; height:' . self::PAGE_H . 'mm;">

  <tr style="height:' . self::ROW_HEADER . 'mm;">
    <td align="center" valign="middle"
        style="font-family:' . $df . '; font-size:' . self::PT_ARMY . 'pt;
               font-style:italic; color:' . self::RED . ';">
      ' . esc_html( $side_label ) . '
    </td>
  </tr>

  <tr style="height:' . self::ROW_GAP_1 . 'mm;"><td></td></tr>

  <tr style="height:' . $row_name_h . 'mm;">
    <td align="center" valign="bottom"
        style="font-family:' . $tf . '; font-size:' . $name_pt . 'pt;
               font-weight:bold; color:' . self::BLUE . ';">
      ' . $shield_name . '
    </td>
  </tr>

  <tr style="height:' . self::ROW_SUPPORT . 'mm;">
    <td align="center" valign="top"
        style="font-family:' . $tf . '; font-size:' . self::PT_SUPPORT . 'pt;
               font-weight:bold; color:' . self::BLUE . '; padding-bottom:3mm;">
      is supported by:
    </td>
  </tr>

  <tr style="height:' . self::ROW_GAP_2 . 'mm;"><td></td></tr>

  <tr style="height:' . $row_box_h . 'mm;">
    <td align="center" valign="top">
      <table style="width:' . self::BOX_W_PCT . '%; height:' . $row_box_h . 'mm;
                    border:' . self::BOX_BORDER . '; border-collapse:separate; background:' . self::WHITE . ';">
        <tr>
          <td style="padding:' . self::BOX_PAD_V . 'mm ' . self::BOX_PAD_H . 'mm;
                     text-align:center; vertical-align:top;">
            <table style="width:100%; text-align:center;">
              ' . $sponsor_rows . '
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <tr style="height:' . self::ROW_FOOTER_GAP . 'mm;"><td></td></tr>

</table>';
    }

    /**
     * Rows inside the sponsor box.
     * Order: logo → display name → sponsor text (≤3 lines) → URL → phone.
     * Address is intentionally omitted (private comms data only).
     */
    private function render_sponsor_rows(
        array  $patch,
        string $tf,
        int    $logo_max_w_mm,
        array  $layout
    ): string {
        $logo_max_h_mm = $layout['logo_max_h'];
        $rows         = [];
        $has_logo     = ! empty( $patch['logo_path'] );
        $display_name = esc_html( (string) $patch['display_name'] );
        $sponsor_text = esc_html( (string) $patch['sponsor_text'] );
        $sponsor_url  = esc_html( (string) $patch['sponsor_url'] );
        $sponsor_phone = esc_html( (string) $patch['sponsor_phone'] );

        // ── Logo (or name as lead if no logo) ─────────────────────────────────
        if ( $has_logo ) {
            $rows[] = '<tr><td style="text-align:center;">'
                . '<img src="' . esc_attr( $patch['logo_path'] ) . '" '
                . 'style="max-width:' . $logo_max_w_mm . 'mm; max-height:' . $logo_max_h_mm . 'mm; margin:0 auto;" />'
                . '</td></tr>';
        } else {
            $rows[] = '<tr><td style="font-family:' . $tf . '; font-size:' . self::PT_DISPLAY_IN_BOX . 'pt;'
                . ' font-weight:bold; text-align:center;">'
                . $display_name
                . '</td></tr>';
        }

        // ── Display name (below logo when logo-led) ───────────────────────────
        if ( $has_logo && $display_name !== '' ) {
            $rows[] = '<tr><td style="font-family:' . $tf . '; font-size:' . self::PT_DISPLAY_IN_BOX . 'pt;'
                . ' font-weight:bold; text-align:center;">'
                . $display_name
                . '</td></tr>';
        }

        // ── Sponsor text — font size reduced until content fits in box ──────────
        if ( $sponsor_text !== '' ) {
            $text_pt   = $layout['text_pt'];
            $text_lines = $layout['text_lines'];
            $line_h_mm = (int) ceil( $text_pt * 0.353 * 1.0 );
            $max_h_mm  = $line_h_mm * $text_lines;
            $rows[]    = '<tr><td style="font-family:' . $tf . '; font-size:' . $text_pt . 'pt;'
                . ' line-height:1.0; padding-bottom:0; text-align:center;">'
                . $sponsor_text
                . '</td></tr>';
        }

        // ── Sponsor URL ───────────────────────────────────────────────────────
        if ( $sponsor_url !== '' ) {
            $rows[] = '<tr><td style="font-family:' . $tf . '; font-size:' . self::PT_CONTACT . 'pt;'
                . ' line-height:1.0; text-align:center;">'
                . $sponsor_url
                . '</td></tr>';
        }

        // ── Sponsor phone ─────────────────────────────────────────────────────
        if ( $sponsor_phone !== '' ) {
            $rows[] = '<tr><td style="font-family:' . $tf . '; font-size:' . self::PT_CONTACT . 'pt;'
                . ' line-height:1.0; text-align:center;">'
                . $sponsor_phone
                . '</td></tr>';
        }

        // Insert equal-height spacer rows between content elements (space-between fill).
        $gap_mm = $layout['gap_mm'] ?? 0;
        if ( $gap_mm > 0 && count( $rows ) > 1 ) {
            $spaced = [];
            $last_i = count( $rows ) - 1;
            foreach ( $rows as $i => $row ) {
                $spaced[] = $row;
                if ( $i < $last_i ) {
                    $spaced[] = '<tr><td style="height:' . $gap_mm . 'mm;"></td></tr>';
                }
            }
            $rows = $spaced;
        }

        return implode( "\n", $rows );
    }

    // ── Type scale helpers ────────────────────────────────────────────────────

    /** Dynamic size for baron/royal name in the header block (1.51× the previous scale). */
    private function name_font_pt( int $char_count ): int {
        return match ( true ) {
            $char_count > 30 => 26,
            $char_count > 22 => 32,
            $char_count > 16 => 38,
            default          => self::PT_NAME_BASE, // 44 pt
        };
    }

    /** Sponsor text size: dynamic between PT_TEXT_MIN and PT_TEXT_MAX (2× previous values). */
    private function sponsor_text_pt( int $char_count ): int {
        return match ( true ) {
            $char_count <= 50  => self::PT_TEXT_MAX, // 56 pt
            $char_count <= 100 => 52,
            default            => self::PT_TEXT_MIN, // 48 pt
        };
    }

    // ── Sponsor box layout ───────────────────────────────────────────────────────

    /**
     * Calculates the optimal sponsor-text font size (reducing until content fits),
     * the number of text lines, and the remaining height available for the logo.
     *
     * All heights in mm.  1 pt ≈ 0.353 mm; line-height 1.0; avg char width 0.45 em.
     *
     * @return array{text_pt: int, text_lines: int, logo_max_h: int, gap_mm: int}
     */
    private function fit_sponsor_box( array $patch, int $box_inner_h ): array {
        $lh       = 1.0;
        $box_w_mm = (int) round( self::PAGE_W * self::BOX_W_PCT / 100 ) - ( self::BOX_PAD_H * 2 ); // 172 mm
        $has_logo = ! empty( $patch['logo_path'] );

        // Heights of fixed-size elements (display name, URL, phone).
        // All three can wrap, so we estimate line count from character width.
        $dn_lines  = 0;
        $url_lines = 0;
        $fixed_h   = 0;
        $dn_line_h     = (int) ceil( self::PT_DISPLAY_IN_BOX * 0.353 * $lh );        // mm per line
        $dn_char_p_l   = max( 1, (int) floor( $box_w_mm / ( self::PT_DISPLAY_IN_BOX * 0.353 * 0.45 ) ) );
        $contact_line_h = (int) ceil( self::PT_CONTACT * 0.353 * $lh );
        $contact_cpl    = max( 1, (int) floor( $box_w_mm / ( self::PT_CONTACT * 0.353 * 0.45 ) ) );

        if ( (string) $patch['display_name'] !== '' ) {
            $dn_lines = max( 1, (int) ceil( mb_strlen( (string) $patch['display_name'] ) / $dn_char_p_l ) );
            $fixed_h += $dn_line_h * $dn_lines;
        }
        if ( ! empty( $patch['sponsor_url'] ) ) {
            $url_lines = max( 1, (int) ceil( mb_strlen( (string) $patch['sponsor_url'] ) / $contact_cpl ) );
            $fixed_h  += $contact_line_h * $url_lines;
        }
        if ( ! empty( $patch['sponsor_phone'] ) ) {
            $fixed_h += $contact_line_h;
        }

        $min_logo = $has_logo ? self::LOGO_MIN_H : 0;

        // Space the sponsor text can use (leaving room for a minimum-size logo).
        $text_budget = $box_inner_h - $fixed_h - $min_logo;

        $text     = (string) $patch['sponsor_text'];
        $text_pt  = $text !== '' ? $this->sponsor_text_pt( mb_strlen( $text ) ) : 0;
        $text_lines = 0;
        $text_actual_h = 0;

        if ( $text !== '' && $text_budget > 2 ) {
            for ( $pt = $text_pt; $pt >= 20; $pt -= 2 ) {
                $line_h_mm     = (int) ceil( $pt * 0.353 * $lh );
                $char_per_line = max( 1, (int) floor( $box_w_mm / ( $pt * 0.353 * 0.45 ) ) );
                $lines         = max( 1, (int) ceil( mb_strlen( $text ) / $char_per_line ) );
                $text_h        = $line_h_mm * $lines;
                if ( $text_h <= $text_budget ) {
                    $text_pt    = $pt;
                    $text_lines = $lines;
                    break;
                }
                // Absolute floor: take as many lines as fit.
                if ( $pt <= 20 ) {
                    $text_pt    = $pt;
                    $text_lines = max( 1, (int) floor( $text_budget / $line_h_mm ) );
                    break;
                }
            }
            $text_actual_h = (int) ceil( $text_pt * 0.353 * $lh ) * $text_lines;
        }

        // Logo fills what remains, clamped to safe bounds.
        $logo_avail = $box_inner_h - $fixed_h - $text_actual_h;
        $logo_max_h = $has_logo
            ? (int) max( self::LOGO_MIN_H, min( self::LOGO_MAX_H_ABS, $logo_avail ) )
            : 0;

        // Space-between: compute equal gap between each content element.
        $el_h = [];
        if ( $has_logo && $logo_max_h > 0 )       $el_h[] = $logo_max_h;
        if ( $dn_lines > 0 )                       $el_h[] = $dn_line_h * $dn_lines;
        if ( $text_actual_h > 0 )                  $el_h[] = $text_actual_h;
        if ( $url_lines > 0 )                      $el_h[] = $contact_line_h * $url_lines;
        if ( ! empty( $patch['sponsor_phone'] ) )  $el_h[] = $contact_line_h;
        $n_el   = count( $el_h );
        $gap_mm = $n_el > 1
            ? max( 0, (int) floor( ( $box_inner_h - array_sum( $el_h ) ) / ( $n_el - 1 ) ) )
            : 0;

        return [
            'text_pt'    => $text_pt,
            'text_lines' => $text_lines,
            'logo_max_h' => $logo_max_h,
            'gap_mm'     => $gap_mm,
        ];
    }
}