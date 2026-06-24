<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

class BlockRenderer {

    // Global style defaults
    private const GLOBAL_DEFAULTS = [
        'backgroundColor'   => '#f4f4f4',
        'contentBackground' => '#ffffff',
        'fontFamily'        => 'Arial, sans-serif',
    ];

    // Block-level style defaults
    private const BLOCK_DEFAULTS = [
        'heading' => [
            'fontSize'   => '24px',
            'fontWeight' => 'bold',
            'color'      => '#111111',
            'align'      => 'left',
            'padding'    => '20px 25px 10px',
        ],
        'text' => [
            'fontSize' => '14px',
            'color'    => '#333333',
            'align'    => 'left',
            'padding'  => '10px 25px',
        ],
        'image' => [
            'align'   => 'center',
            'padding' => '10px 25px',
            'width'   => '100%',
        ],
        'button' => [
            'backgroundColor' => '#6366f1',
            'color'           => '#ffffff',
            'borderRadius'    => '6px',
            'align'           => 'center',
            'padding'         => '15px 25px',
            'fontSize'        => '14px',
        ],
        'divider' => [
            'color'       => '#dddddd',
            'borderWidth' => '1px',
            'padding'     => '10px 25px',
        ],
        'spacer' => [
            'height' => '20px',
        ],
    ];

    /**
     * Convert block JSON (version:1) or MJML string to HTML.
     */
    public static function to_html( string $json, array $context = [] ): string {
        if ( empty( trim( $json ) ) ) {
            return '';
        }

        // Try parsing as our block JSON format
        $data = null;
        try {
            $decoded = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
            if ( is_array( $decoded ) && isset( $decoded['version'] ) && $decoded['version'] === 1 ) {
                $data = $decoded;
            }
        } catch ( \JsonException $e ) {
            // Not JSON — fall through to MJML
        }

        if ( $data !== null ) {
            return self::render_blocks( $data, $context );
        }

        // Fallback: compile as MJML
        return EmailSender::compile_mjml( $json ) ?: $json;
    }

    /**
     * Render a parsed block data array to HTML.
     */
    private static function render_blocks( array $data, array $context ): string {
        $gs = array_merge( self::GLOBAL_DEFAULTS, $data['globalStyle'] ?? [] );

        $bg         = esc_attr( $gs['backgroundColor'] );
        $content_bg = esc_attr( $gs['contentBackground'] );
        $font       = esc_attr( $gs['fontFamily'] );

        $blocks_html = '';
        foreach ( $data['blocks'] ?? [] as $block ) {
            $blocks_html .= self::render_block( $block, $gs, $context );
        }

        return '<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { margin:0; padding:0; background-color:' . $bg . '; }
    img { border:0; height:auto; display:block; max-width:100%; }
    table { border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; }
    a { color:inherit; }
  </style>
</head>
<body style="margin:0;padding:0;background-color:' . $bg . ';">
  <table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td align="center" style="padding:20px 10px;">
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background-color:' . $content_bg . ';font-family:' . $font . ';">
          ' . $blocks_html . '
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }

    /**
     * Render a single block to a <tr> element.
     */
    private static function render_block( array $block, array $gs, array $context ): string {
        $type     = $block['type'] ?? '';
        $defaults = self::BLOCK_DEFAULTS[ $type ] ?? [];
        $style    = array_merge( $defaults, $block['style'] ?? [] );
        $font     = esc_attr( $gs['fontFamily'] );

        switch ( $type ) {
            case 'heading':
            case 'text':
                $padding     = esc_attr( $style['padding'] ?? '10px 25px' );
                $font_size   = esc_attr( $style['fontSize'] ?? '14px' );
                $font_weight = $type === 'heading' ? esc_attr( $style['fontWeight'] ?? 'bold' ) : 'normal';
                $color       = esc_attr( $style['color'] ?? '#333333' );
                $align       = esc_attr( $style['align'] ?? 'left' );
                $line_height = $type === 'text' ? 'line-height:1.6;' : '';
                $content     = self::apply_context( $block['content'] ?? '', $context );
                $content     = wp_kses_post( $content );

                return '<tr><td style="padding:' . $padding . ';font-family:' . $font . ';font-size:' . $font_size . ';font-weight:' . $font_weight . ';color:' . $color . ';text-align:' . $align . ';' . $line_height . '">' . $content . '</td></tr>' . "\n";

            case 'image':
                $padding = esc_attr( $style['padding'] ?? '10px 25px' );
                $align   = esc_attr( $style['align'] ?? 'center' );
                $src     = esc_url( self::apply_context( $block['src'] ?? '', $context ) );
                $alt     = esc_attr( $block['alt'] ?? '' );
                $width   = esc_attr( $block['width'] ?? '100%' );
                $href    = self::apply_context( $block['href'] ?? '', $context );

                $img_tag = '<img src="' . $src . '" alt="' . $alt . '" width="' . $width . '" style="display:block;max-width:100%;border:0;">';

                if ( ! empty( $href ) ) {
                    $img_tag = '<a href="' . esc_url( $href ) . '" style="display:block;">' . $img_tag . '</a>';
                }

                return '<tr><td style="padding:' . $padding . ';text-align:' . $align . ';">' . $img_tag . '</td></tr>' . "\n";

            case 'button':
                $padding       = esc_attr( $style['padding'] ?? '15px 25px' );
                $align         = esc_attr( $style['align'] ?? 'center' );
                $bg_color      = esc_attr( $style['backgroundColor'] ?? '#6366f1' );
                $color         = esc_attr( $style['color'] ?? '#ffffff' );
                $border_radius = esc_attr( $style['borderRadius'] ?? '6px' );
                $font_size     = esc_attr( $style['fontSize'] ?? '14px' );
                $text          = esc_html( $block['text'] ?? 'Click aquí' );
                $href          = esc_url( self::apply_context( $block['href'] ?? '#', $context ) );

                return '<tr><td style="padding:' . $padding . ';text-align:' . $align . ';">
    <table cellpadding="0" cellspacing="0" border="0" style="display:inline-table;">
      <tr>
        <td align="center" bgcolor="' . $bg_color . '" style="border-radius:' . $border_radius . ';">
          <a href="' . $href . '" style="display:inline-block;background-color:' . $bg_color . ';color:' . $color . ';border-radius:' . $border_radius . ';padding:12px 24px;font-family:' . $font . ';font-size:' . $font_size . ';font-weight:bold;text-decoration:none;mso-padding-alt:0;">' . $text . '</a>
        </td>
      </tr>
    </table>
  </td></tr>' . "\n";

            case 'divider':
                $padding      = esc_attr( $style['padding'] ?? '10px 25px' );
                $color        = esc_attr( $style['color'] ?? '#dddddd' );
                $border_width = esc_attr( $style['borderWidth'] ?? '1px' );

                return '<tr><td style="padding:' . $padding . ';"><hr style="border:0;border-top:' . $border_width . ' solid ' . $color . ';margin:0;"></td></tr>' . "\n";

            case 'spacer':
                $height = esc_attr( $style['height'] ?? '20px' );

                return '<tr><td style="height:' . $height . ';line-height:' . $height . ';font-size:1px;">&nbsp;</td></tr>' . "\n";

            default:
                return '';
        }
    }

    /**
     * Replace {{variable}} placeholders in a string with context values.
     */
    private static function apply_context( string $text, array $context ): string {
        if ( empty( $context ) || empty( $text ) ) {
            return $text;
        }
        foreach ( $context as $key => $value ) {
            $text = str_replace( '{{' . $key . '}}', (string) $value, $text );
        }
        return $text;
    }
}
