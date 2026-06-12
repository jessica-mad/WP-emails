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
<div class="wrap wea-wrap wea-builder-wrap">
    <h1><?php echo $template ? esc_html__( 'Edit Template', 'wp-email-automations' ) : esc_html__( 'New Template', 'wp-email-automations' ); ?></h1>

    <div class="wea-builder-header">
        <input type="text" id="wea-tmpl-name" class="regular-text" placeholder="<?php esc_attr_e( 'Template name', 'wp-email-automations' ); ?>" value="<?php echo esc_attr( $template['name'] ?? '' ); ?>">
        <input type="text" id="wea-tmpl-subject" class="regular-text" placeholder="<?php esc_attr_e( 'Email subject — supports {{variables}}', 'wp-email-automations' ); ?>" value="<?php echo esc_attr( $template['subject'] ?? '' ); ?>">
        <button class="button button-primary" id="wea-save-btn"><?php esc_html_e( 'Save', 'wp-email-automations' ); ?></button>
        <button class="button" id="wea-test-btn"><?php esc_html_e( 'Send Test', 'wp-email-automations' ); ?></button>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wea-templates' ) ); ?>" class="button"><?php esc_html_e( '← Back', 'wp-email-automations' ); ?></a>
        <input type="hidden" id="wea-tmpl-id" value="<?php echo esc_attr( $template['id'] ?? 0 ); ?>">
    </div>

    <div id="wea-builder-status" class="wea-builder-status"></div>

    <!-- GrapesJS mounts here -->
    <div id="gjs" style="height:80vh;border:1px solid #ddd;background:#fff;"></div>

    <script>
        var weaTemplateData = <?php echo wp_json_encode( [
            'mjml_json' => $template['mjml_json'] ?? null,
            'html'      => $template['html']      ?? null,
        ] ); ?>;
    </script>
</div>

<style>
/* Builder full-width override */
#wpcontent { padding-left: 0 !important; }
.wea-builder-wrap { margin: 0; max-width: 100%; }
</style>
<?php endif; ?>
