<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

class EmailSender {

    /**
     * Send an email using wp_mail (respects the site's SMTP configuration).
     */
    public static function send( string $to, string $subject, string $html, array $extra_headers = [] ): bool {
        $headers = array_merge(
            [ 'Content-Type: text/html; charset=UTF-8' ],
            $extra_headers
        );

        return wp_mail( $to, $subject, $html, $headers );
    }

    /**
     * Send using a saved template, replacing placeholders from $context.
     * If the stored html looks like MJML (starts with <mjml>), compiles it first.
     */
    public static function send_template( int $template_id, string $to, string $subject_override = '', array $context = [] ): bool {
        $template = TemplateManager::get( $template_id );
        if ( ! $template ) {
            return false;
        }

        $subject = $subject_override ?: TemplateManager::render( $template['subject'], $context );

        // Use compiled HTML if available; fall back to compiling from mjml_content
        $raw_html = $template['html'] ?? '';
        if ( self::is_mjml( $raw_html ) || empty( trim( $raw_html ) ) ) {
            $raw_html = self::compile_mjml( $template['mjml_content'] ?? '' ) ?: $raw_html;
        }

        $html = TemplateManager::render( $raw_html, $context );

        return self::send( $to, $subject, $html );
    }

    /**
     * Compile MJML string to HTML using the bundled Node.js script.
     * Returns empty string on failure.
     */
    public static function compile_mjml( string $mjml ): string {
        if ( empty( trim( $mjml ) ) ) {
            return '';
        }

        $node    = self::node_binary();
        $script  = WEA_PLUGIN_DIR . 'bin/mjml-compile.js';
        $bin_dir = WEA_PLUGIN_DIR . 'bin';

        if ( ! $node || ! file_exists( $script ) ) {
            return '';
        }

        // Auto-install npm deps if missing (first-run or after deploy)
        if ( ! is_dir( $bin_dir . '/node_modules/mjml' ) ) {
            $npm = str_replace( 'node', 'npm', $node );
            if ( ! @is_executable( $npm ) ) { $npm = 'npm'; }
            @shell_exec( escapeshellcmd( $npm ) . ' install --prefix ' . escapeshellarg( $bin_dir ) . ' 2>&1' );
        }

        $descriptors = [
            0 => [ 'pipe', 'r' ],
            1 => [ 'pipe', 'w' ],
            2 => [ 'pipe', 'w' ],
        ];

        $proc = proc_open( escapeshellcmd( $node ) . ' ' . escapeshellarg( $script ), $descriptors, $pipes );
        if ( ! is_resource( $proc ) ) {
            return '';
        }

        fwrite( $pipes[0], $mjml );
        fclose( $pipes[0] );

        $html  = stream_get_contents( $pipes[1] );
        fclose( $pipes[1] );
        fclose( $pipes[2] );
        proc_close( $proc );

        return $html ?: '';
    }

    /**
     * Detect if a string is MJML rather than HTML.
     */
    private static function is_mjml( string $s ): bool {
        $trimmed = ltrim( $s );
        return str_starts_with( $trimmed, '<mjml' ) || str_starts_with( $trimmed, '<mj-' );
    }

    /**
     * Find the node binary on this server.
     */
    private static function node_binary(): string {
        foreach ( [ '/opt/node22/bin/node', '/usr/local/bin/node', '/usr/bin/node', 'node' ] as $candidate ) {
            if ( @is_executable( $candidate ) || $candidate === 'node' ) {
                return $candidate;
            }
        }
        return '';
    }
}
