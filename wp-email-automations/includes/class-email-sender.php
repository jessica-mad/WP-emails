<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

class EmailSender {

    /**
     * Send an email using wp_mail (respects the site's SMTP configuration).
     *
     * @param string $to
     * @param string $subject
     * @param string $html         Fully rendered HTML body.
     * @param array  $extra_headers
     * @return bool
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
     *
     * @param int    $template_id
     * @param string $to
     * @param string $subject_override  Leave empty to use template's subject.
     * @param array  $context           Key-value pairs for {{variable}} replacement.
     * @return bool
     */
    public static function send_template( int $template_id, string $to, string $subject_override = '', array $context = [] ): bool {
        $template = TemplateManager::get( $template_id );
        if ( ! $template ) {
            return false;
        }

        $subject = $subject_override ?: TemplateManager::render( $template['subject'], $context );
        $html    = TemplateManager::render( $template['html'], $context );

        return self::send( $to, $subject, $html );
    }
}
