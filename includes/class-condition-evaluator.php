<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates a set of conditions against event data.
 *
 * Condition schema:
 * [
 *   { "field": "plan", "operator": "equals",       "value": "pro" },
 *   { "field": "amount", "operator": "greater_than", "value": "100" },
 * ]
 * All conditions must pass (AND logic). Groups with "or": true are ORed.
 */
class ConditionEvaluator {

    public static function passes( array $conditions, array $event_data ): bool {
        if ( empty( $conditions ) ) {
            return true;
        }

        foreach ( $conditions as $condition ) {
            $field    = $condition['field']    ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $expected = $condition['value']    ?? '';
            $actual   = self::resolve( $field, $event_data );

            if ( ! self::compare( $actual, $operator, $expected ) ) {
                return false;
            }
        }

        return true;
    }

    private static function resolve( string $field, array $data ): mixed {
        // Support dot notation: "user.email"
        $parts   = explode( '.', $field );
        $current = $data;
        foreach ( $parts as $part ) {
            if ( is_array( $current ) && array_key_exists( $part, $current ) ) {
                $current = $current[ $part ];
            } else {
                return null;
            }
        }
        return $current;
    }

    private static function compare( mixed $actual, string $operator, mixed $expected ): bool {
        return match ( $operator ) {
            'equals'            => (string) $actual === (string) $expected,
            'not_equals'        => (string) $actual !== (string) $expected,
            'contains'          => str_contains( (string) $actual, (string) $expected ),
            'not_contains'      => ! str_contains( (string) $actual, (string) $expected ),
            'greater_than'      => is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual > (float) $expected,
            'less_than'         => is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual < (float) $expected,
            'greater_than_equal'=> is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual >= (float) $expected,
            'less_than_equal'   => is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual <= (float) $expected,
            'is_empty'          => empty( $actual ),
            'is_not_empty'      => ! empty( $actual ),
            default             => false,
        };
    }

    public static function operators(): array {
        return [
            'equals'             => __( 'Equals',             'wp-email-automations' ),
            'not_equals'         => __( 'Not equals',         'wp-email-automations' ),
            'contains'           => __( 'Contains',           'wp-email-automations' ),
            'not_contains'       => __( 'Does not contain',   'wp-email-automations' ),
            'greater_than'       => __( 'Greater than',       'wp-email-automations' ),
            'less_than'          => __( 'Less than',          'wp-email-automations' ),
            'greater_than_equal' => __( 'Greater or equal',   'wp-email-automations' ),
            'less_than_equal'    => __( 'Less or equal',      'wp-email-automations' ),
            'is_empty'           => __( 'Is empty',           'wp-email-automations' ),
            'is_not_empty'       => __( 'Is not empty',       'wp-email-automations' ),
        ];
    }
}
