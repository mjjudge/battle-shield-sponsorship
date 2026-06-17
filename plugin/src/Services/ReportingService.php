<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class ReportingService {

    /**
     * Summary stats for a campaign.
     *
     * @return array{total_revenue:float, shield_count:int, paid_complete:int, paid_incomplete:int, refunded:int, pending:int}
     */
    public function campaign_summary( int $campaign_id ): array {
        global $wpdb;
        $table = Schema::table_name( 'sponsorships' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT payment_status, artwork_status, SUM(total_amount) AS revenue, COUNT(*) AS cnt
             FROM {$table}
             WHERE campaign_id = %d
             GROUP BY payment_status, artwork_status",
            $campaign_id
        ) );

        $summary = [
            'total_revenue'  => 0.0,
            'shield_count'   => 0,
            'paid_complete'  => 0,
            'paid_incomplete' => 0,
            'refunded'       => 0,
            'pending'        => 0,
        ];

        foreach ( ( is_array( $rows ) ? $rows : [] ) as $row ) {
            $status         = (string) $row->payment_status;
            $artwork        = (string) $row->artwork_status;
            $cnt            = (int) $row->cnt;
            $rev            = (float) $row->revenue;

            if ( 'paid' === $status ) {
                $summary['total_revenue'] += $rev;
                $summary['shield_count']  += $cnt;
                if ( 'complete' === $artwork ) {
                    $summary['paid_complete'] += $cnt;
                } else {
                    $summary['paid_incomplete'] += $cnt;
                }
            } elseif ( 'refunded' === $status ) {
                $summary['refunded'] += $cnt;
            } elseif ( 'pending' === $status ) {
                $summary['pending'] += $cnt;
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
