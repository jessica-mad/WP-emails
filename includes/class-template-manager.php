<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

class TemplateManager {

    public static function get_all(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT id, name, subject, created_at, updated_at FROM {$wpdb->prefix}wea_templates ORDER BY updated_at DESC",
            ARRAY_A
        ) ?: [];
    }

    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wea_templates WHERE id = %d", $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function save( array $data ): int|false {
        global $wpdb;
        $now = current_time( 'mysql' );

        $fields = [
            'name'         => sanitize_text_field( $data['name']         ?? '' ),
            'subject'      => sanitize_text_field( $data['subject']      ?? '' ),
            'mjml_content' => $data['mjml_content']                      ?? '',
            'html'         => $data['html']                              ?? '',
        ];

        if ( ! empty( $data['id'] ) ) {
            $fields['updated_at'] = $now;
            $wpdb->update(
                "{$wpdb->prefix}wea_templates",
                $fields,
                [ 'id' => (int) $data['id'] ]
            );
            return (int) $data['id'];
        }

        $fields['created_at'] = $now;
        $fields['updated_at'] = $now;
        $wpdb->insert( "{$wpdb->prefix}wea_templates", $fields );
        return $wpdb->insert_id ?: false;
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( "{$wpdb->prefix}wea_templates", [ 'id' => $id ] );
    }

    /**
     * Replace {{variable}} placeholders with values from $context.
     */
    public static function render( string $html, array $context ): string {
        foreach ( $context as $key => $value ) {
            if ( is_scalar( $value ) ) {
                $html = str_replace( '{{' . $key . '}}', esc_html( (string) $value ), $html );
            }
        }
        return $html;
    }
}
