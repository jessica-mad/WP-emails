<?php
defined( 'ABSPATH' ) || exit;

$action   = $_GET['action'] ?? 'list';
$id       = absint( $_GET['id'] ?? 0 );
$template = ( $action === 'edit' && $id ) ? WEA\TemplateManager::get( $id ) : null;

if ( $action === 'list' ) :
    $templates = WEA\TemplateManager::get_all();
?>
<div class="wrap wea-wrap">
    <h1>
        <?php esc_html_e( 'Email Templates', 'wp-email-automations' ); ?>
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wea-templates', 'action' => 'new' ], admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Add New', 'wp-email-automations' ); ?>
        </a>
    </h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template saved.', 'wp-email-automations' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template deleted.', 'wp-email-automations' ); ?></p></div>
    <?php endif; ?>

    <?php if ( empty( $templates ) ) : ?>
        <p><?php esc_html_e( 'No templates yet. Create your first email template!', 'wp-email-automations' ); ?></p>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Name', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'Subject', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'Last Updated', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'wp-email-automations' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $templates as $tmpl ) : ?>
            <tr>
                <td><strong><?php echo esc_html( $tmpl['name'] ); ?></strong></td>
                <td><?php echo esc_html( $tmpl['subject'] ); ?></td>
                <td><?php echo esc_html( $tmpl['updated_at'] ); ?></td>
                <td>
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wea-templates', 'action' => 'edit', 'id' => $tmpl['id'] ], admin_url( 'admin.php' ) ) ); ?>">
                        <?php esc_html_e( 'Edit', 'wp-email-automations' ); ?>
                    </a> |
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm(weaAdmin.i18n.confirmDelete)">
                        <?php wp_nonce_field( 'wea_delete_template_' . $tmpl['id'] ); ?>
                        <input type="hidden" name="action" value="wea_delete_template">
                        <input type="hidden" name="id" value="<?php echo esc_attr( $tmpl['id'] ); ?>">
                        <button type="submit" class="button-link wea-delete-link"><?php esc_html_e( 'Delete', 'wp-email-automations' ); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php else : // builder ?>
<div class="wea-builder-page">

    <!-- ── Barra superior ── -->
    <div class="wea-builder-topbar">
        <div class="wea-builder-topbar-left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wea-templates' ) ); ?>" class="wea-back-btn">← <?php esc_html_e( 'Plantillas', 'wp-email-automations' ); ?></a>
            <input type="text" id="wea-tmpl-name"    placeholder="<?php esc_attr_e( 'Nombre de la plantilla', 'wp-email-automations' ); ?>"   value="<?php echo esc_attr( $template['name']    ?? '' ); ?>" class="wea-topbar-input">
            <input type="text" id="wea-tmpl-subject" placeholder="<?php esc_attr_e( 'Asunto del email (admite {{variables}})', 'wp-email-automations' ); ?>" value="<?php echo esc_attr( $template['subject'] ?? '' ); ?>" class="wea-topbar-input wea-topbar-input--wide">
        </div>
        <div class="wea-builder-topbar-center" id="wea-editor-topbar"></div>
        <div class="wea-builder-topbar-right">
            <div id="wea-builder-status" class="wea-builder-status"></div>
            <button class="wea-btn wea-btn--secondary" id="wea-test-btn">📧 <?php esc_html_e( 'Prueba', 'wp-email-automations' ); ?></button>
            <button class="wea-btn wea-btn--primary"   id="wea-save-btn">💾 <?php esc_html_e( 'Guardar', 'wp-email-automations' ); ?></button>
        </div>
        <input type="hidden" id="wea-tmpl-id" value="<?php echo esc_attr( $template['id'] ?? 0 ); ?>">
    </div>

    <!-- ── Layout principal: sidebar izquierdo + canvas + sidebar derecho ── -->
    <div class="wea-builder-body">

        <!-- Sidebar izquierdo: bloques + capas -->
        <div class="wea-builder-sidebar wea-builder-sidebar--left">
            <div class="wea-tabs">
                <button class="wea-tab-btn active" data-tab="blocks">🧩 <?php esc_html_e( 'Bloques', 'wp-email-automations' ); ?></button>
                <button class="wea-tab-btn"        data-tab="layers">📐 <?php esc_html_e( 'Capas',   'wp-email-automations' ); ?></button>
            </div>
            <div id="wea-blocks-panel" class="wea-tab-panel"></div>
            <div id="wea-layers-panel" class="wea-tab-panel" style="display:none"></div>
        </div>

        <!-- Canvas -->
        <div class="wea-builder-canvas">
            <div id="gjs"></div>
        </div>

        <!-- Sidebar derecho: propiedades + estilos -->
        <div class="wea-builder-sidebar wea-builder-sidebar--right">
            <div class="wea-tabs">
                <button class="wea-tab-btn active" data-tab="traits">⚙️ <?php esc_html_e( 'Propiedades', 'wp-email-automations' ); ?></button>
                <button class="wea-tab-btn"        data-tab="styles">🎨 <?php esc_html_e( 'Estilos',      'wp-email-automations' ); ?></button>
            </div>
            <div id="wea-traits-panel" class="wea-tab-panel"></div>
            <div id="wea-styles-panel" class="wea-tab-panel" style="display:none"></div>
        </div>

    </div><!-- .wea-builder-body -->
</div><!-- .wea-builder-page -->

<script>
var weaTemplateData = <?php echo wp_json_encode( [
    'mjml_json' => $template['mjml_json'] ?? null,
    'html'      => $template['html']      ?? null,
] ); ?>;
</script>
<?php endif; ?>
