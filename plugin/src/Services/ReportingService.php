<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class ReportingService {

    /**
     * Summary stats for a campaign.
     *
     * paid_complete / paid_incomplete count individual shield items (not sponsorship records)
     * so they are consistent with shield_counts->sponsored on the Dashboard.
     *
     * @return array{total_revenue:float, paid_count:int, shields_sponsored:int, paid_complete:int, paid_incomplete:int, artwork_complete:int, artwork_missing:int, gift_aid_count:int, refunded:int, pending:int}
     */
    public function campaign_summary( int $campaign_id ): array {
        global $wpdb;

        $s_table = Schema::table_name( 'sponsorships' );
        $i_table = Schema::table_name( 'sponsorship_items' );

        // Revenue, sponsorship counts and Gift Aid — one row per payment_status / artwork_status group.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT payment_status, artwork_status,
                    SUM(total_amount) AS revenue,
                    COUNT(*) AS sponsorship_cnt,
                    SUM(gift_aid_declared) AS gift_aid_cnt
             FROM {$s_table}
             WHERE campaign_id = %d
             GROUP BY payment_status, artwork_status",
            $campaign_id
        ) );

        // Shield-item counts per artwork status (for paid sponsorships only).
        $item_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.artwork_status, COUNT(i.id) AS item_cnt
             FROM {$s_table} s
             JOIN {$i_table} i ON i.sponsorship_id = s.id
             WHERE s.campaign_id = %d AND s.payment_status = 'paid'
             GROUP BY s.artwork_status",
            $campaign_id
        ) );

        $summary = [
            'total_revenue'    => 0.0,
            'paid_count'       => 0,
            'shields_sponsored' => 0,
            'paid_complete'    => 0,
            'paid_incomplete'  => 0,
            'artwork_complete' => 0,
            'artwork_missing'  => 0,
            'gift_aid_count'   => 0,
            'refunded'         => 0,
            'pending'          => 0,
        ];

        foreach ( ( is_array( $rows ) ? $rows : [] ) as $row ) {
            $status  = (string) $row->payment_status;
            $artwork = (string) $row->artwork_status;
            $cnt     = (int) $row->sponsorship_cnt;
            $rev     = (float) $row->revenue;

            if ( 'paid' === $status ) {
                $summary['total_revenue'] += $rev;
                $summary['paid_count']    += $cnt;
                $summary['gift_aid_count'] += (int) $row->gift_aid_cnt;
            } elseif ( 'refunded' === $status ) {
                $summary['refunded'] += $cnt;
            } elseif ( 'pending' === $status ) {
                $summary['pending'] += $cnt;
            }
        }

        // Populate shield-item counts from the items join.
        foreach ( ( is_array( $item_rows ) ? $item_rows : [] ) as $row ) {
            $item_cnt = (int) $row->item_cnt;
            $summary['shields_sponsored'] += $item_cnt;
            if ( 'complete' === (string) $row->artwork_status ) {
                $summary['paid_complete']    = $item_cnt;
                $summary['artwork_complete'] = $item_cnt;
            } else {
                $summary['paid_incomplete']  = $item_cnt;
                $summary['artwork_missing']  = $item_cnt;
            }
        }

        return $summary;
    }

    /**
     * Generate CSV rows for all paid sponsorships in a campaign.
     * Returns header row + data rows.
     *
     * @return string[][]
     */
    public function sponsorships_csv( int $campaign_id ): array {
        global $wpdb;

        $s = Schema::table_name( 'sponsorships' );
        $c = Schema::table_name( 'contacts' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.display_name, s.total_amount, s.payment_method, s.payment_status,
                    s.artwork_status, s.gift_aid_declared, s.created_at,
                    c.contact_name, c.email, c.phone, c.website_url
             FROM {$s} s
             LEFT JOIN {$c} c ON c.id = s.contact_id
             WHERE s.campaign_id = %d
             ORDER BY s.created_at ASC",
            $campaign_id
        ) );

        $header = [
            'ID', 'Sponsor display name', 'Contact name', 'Email', 'Phone',
            'Website', 'Amount (£)', 'Payment method', 'Payment status',
            'Artwork status', 'Gift Aid', 'Created',
        ];

        $csv = [ $header ];

        foreach ( ( is_array( $rows ) ? $rows : [] ) as $row ) {
            $csv[] = [
                (string) $row->id,
                (string) $row->display_name,
                (string) $row->contact_name,
                (string) $row->email,
                (string) ( $row->phone ?? '' ),
                (string) ( $row->website_url ?? '' ),
                number_format( (float) $row->total_amount, 2 ),
                (string) $row->payment_method,
                (string) $row->payment_status,
                (string) $row->artwork_status,
                (int) $row->gift_aid_declared ? 'Yes' : 'No',
                (string) $row->created_at,
            ];
        }

        return $csv;
    }

    /**
     * Generate CSV rows for opted-in contacts.
     *
     * @return string[][]
     */
    public function opted_in_contacts_csv(): array {
        $contacts = ( new ContactService() )->get_opted_in();

        $header = [ 'ID', 'Contact name', 'Display name', 'Email', 'Phone', 'Website', 'Opted in at' ];
        $csv    = [ $header ];

        foreach ( $contacts as $contact ) {
            $csv[] = [
                (string) $contact->id,
                (string) $contact->contact_name,
                (string) $contact->display_name,
                (string) $contact->email,
                (string) ( $contact->phone ?? '' ),
                (string) ( $contact->website_url ?? '' ),
                (string) ( $contact->marketing_opt_in_at ?? '' ),
            ];
        }

        return $csv;
    }

    /** Output CSV to the browser. Calls exit. */
    public static function output_csv( string $filename, array $rows ): void {
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );
        if ( false === $out ) {
            return;
        }

        foreach ( $rows as $row ) {
            fputcsv( $out, $row );
        }

        fclose( $out );
        exit;
    }
}
