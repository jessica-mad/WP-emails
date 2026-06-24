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
    <div class="wea-builder-topbar">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wea-templates' ) ); ?>" class="wea-back-btn">← <?php esc_html_e( 'Plantillas', 'wp-email-automations' ); ?></a>
        <input type="text" id="wea-tmpl-name" placeholder="<?php esc_attr_e( 'Nombre de la plantilla', 'wp-email-automations' ); ?>" value="<?php echo esc_attr( $template['name'] ?? '' ); ?>" class="wea-topbar-input">
        <input type="text" id="wea-tmpl-subject" placeholder="<?php esc_attr_e( 'Asunto — admite {{variables}}', 'wp-email-automations' ); ?>" value="<?php echo esc_attr( $template['subject'] ?? '' ); ?>" class="wea-topbar-input wea-topbar-input--wide">
        <div style="flex:1"></div>
        <button class="wea-btn wea-btn--secondary" id="wea-global-settings-btn">⚙ <?php esc_html_e( 'Estilos', 'wp-email-automations' ); ?></button>
        <span id="wea-builder-status" class="wea-builder-status"></span>
        <button class="wea-btn wea-btn--secondary" id="wea-test-btn">📧 <?php esc_html_e( 'Prueba', 'wp-email-automations' ); ?></button>
        <button class="wea-btn wea-btn--primary" id="wea-save-btn">💾 <?php esc_html_e( 'Guardar', 'wp-email-automations' ); ?></button>
        <input type="hidden" id="wea-tmpl-id" value="<?php echo esc_attr( $template['id'] ?? 0 ); ?>">
    </div>

    <div class="wea-editor">
        <!-- Block library -->
        <div class="wea-editor__library">
            <div class="wea-library__title">Bloques</div>
            <div class="wea-library__item" draggable="true" data-type="heading"><span class="wea-library__item__icon">H</span> Título</div>
            <div class="wea-library__item" draggable="true" data-type="text"><span class="wea-library__item__icon">T</span> Texto</div>
            <div class="wea-library__item" draggable="true" data-type="image"><span class="wea-library__item__icon">🖼</span> Imagen</div>
            <div class="wea-library__item" draggable="true" data-type="button"><span class="wea-library__item__icon">▶</span> Botón</div>
            <div class="wea-library__sep"></div>
            <div class="wea-library__item" draggable="true" data-type="divider"><span class="wea-library__item__icon">─</span> Separador</div>
            <div class="wea-library__item" draggable="true" data-type="spacer"><span class="wea-library__item__icon">↕</span> Espacio</div>
        </div>

        <!-- Canvas -->
        <div class="wea-editor__canvas">
            <div class="wea-canvas__email">
                <div class="wea-canvas__inner" id="wea-canvas-inner"></div>
            </div>
        </div>

        <!-- Props panel -->
        <div class="wea-editor__props">
            <div class="wea-props__header">
                <span id="wea-props-header-title">Propiedades</span>
            </div>
            <div class="wea-props__body" id="wea-props-body">
                <div class="wea-props__empty">Haz click en un bloque para editar sus propiedades</div>
            </div>
        </div>
    </div>
</div>

<script>
var weaTemplateData = <?php echo wp_json_encode( [
    'mjml_content' => $template['mjml_content'] ?? null,
] ); ?>;
</script>
<?php endif; ?>
