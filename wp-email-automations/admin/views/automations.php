<?php
defined( 'ABSPATH' ) || exit;

$action     = $_GET['action'] ?? 'list';
$id         = absint( $_GET['id'] ?? 0 );
$automation = ( $action === 'edit' && $id ) ? WEA\Automation::get( $id ) : null;
$templates  = WEA\TemplateManager::get_all();
$triggers   = WEA\Automation::available_triggers();
$operators  = WEA\ConditionEvaluator::operators();

if ( $action === 'list' ) :
    $automations = WEA\Automation::get_all();
?>
<div class="wrap wea-wrap">
    <h1>
        <?php esc_html_e( 'Automations', 'wp-email-automations' ); ?>
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wea-automations', 'action' => 'new' ], admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Add New', 'wp-email-automations' ); ?>
        </a>
    </h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Automation saved.', 'wp-email-automations' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Automation deleted.', 'wp-email-automations' ); ?></p></div>
    <?php endif; ?>

    <?php if ( empty( $automations ) ) : ?>
        <p><?php esc_html_e( 'No automations yet. Create your first one!', 'wp-email-automations' ); ?></p>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Name', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'Trigger', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'Status', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'Last Updated', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'wp-email-automations' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $automations as $auto ) : ?>
            <tr>
                <td><strong><?php echo esc_html( $auto['name'] ); ?></strong></td>
                <td><code><?php echo esc_html( $triggers[ $auto['trigger_key'] ] ?? $auto['trigger_key'] ); ?></code></td>
                <td>
                    <span class="wea-badge wea-badge--<?php echo esc_attr( $auto['status'] ); ?>">
                        <?php echo esc_html( ucfirst( $auto['status'] ) ); ?>
                    </span>
                </td>
                <td><?php echo esc_html( $auto['updated_at'] ); ?></td>
                <td>
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wea-automations', 'action' => 'edit', 'id' => $auto['id'] ], admin_url( 'admin.php' ) ) ); ?>">
                        <?php esc_html_e( 'Edit', 'wp-email-automations' ); ?>
                    </a> |
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                        <?php wp_nonce_field( 'wea_toggle_automation_' . $auto['id'] ); ?>
                        <input type="hidden" name="action" value="wea_toggle_automation">
                        <input type="hidden" name="id" value="<?php echo esc_attr( $auto['id'] ); ?>">
                        <button type="submit" class="button-link">
                            <?php echo $auto['status'] === 'active' ? esc_html__( 'Deactivate', 'wp-email-automations' ) : esc_html__( 'Activate', 'wp-email-automations' ); ?>
                        </button>
                    </form> |
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm(weaAdmin.i18n.confirmDelete)">
                        <?php wp_nonce_field( 'wea_delete_automation_' . $auto['id'] ); ?>
                        <input type="hidden" name="action" value="wea_delete_automation">
                        <input type="hidden" name="id" value="<?php echo esc_attr( $auto['id'] ); ?>">
                        <button type="submit" class="button-link wea-delete-link"><?php esc_html_e( 'Delete', 'wp-email-automations' ); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php else : // edit / new ?>
<div class="wrap wea-wrap">
    <h1><?php echo $automation ? esc_html__( 'Edit Automation', 'wp-email-automations' ) : esc_html__( 'New Automation', 'wp-email-automations' ); ?></h1>

    <div id="wea-automation-editor" data-automation='<?php echo $automation ? esc_attr( wp_json_encode( [ 'id' => $automation['id'], 'name' => $automation['name'], 'trigger_key' => $automation['trigger_key'], 'status' => $automation['status'], 'conditions' => json_decode( $automation['conditions'], true ), 'actions' => json_decode( $automation['actions'], true ) ] ) ) : 'null'; ?>'>

    <form id="wea-automation-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'wea_save_automation' ); ?>
        <input type="hidden" name="action" value="wea_save_automation">
        <input type="hidden" name="id" value="<?php echo esc_attr( $automation['id'] ?? 0 ); ?>">
        <input type="hidden" name="conditions_json" id="conditions_json" value="">
        <input type="hidden" name="actions_json" id="actions_json" value="">

        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Name', 'wp-email-automations' ); ?></th>
                <td><input type="text" name="name" id="auto-name" class="regular-text" value="<?php echo esc_attr( $automation['name'] ?? '' ); ?>" required></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Trigger', 'wp-email-automations' ); ?></th>
                <td>
                    <select name="trigger_key" id="auto-trigger" class="regular-text">
                        <?php foreach ( $triggers as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $automation['trigger_key'] ?? '', $key ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Status', 'wp-email-automations' ); ?></th>
                <td>
                    <select name="status">
                        <option value="inactive" <?php selected( $automation['status'] ?? 'inactive', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'wp-email-automations' ); ?></option>
                        <option value="active" <?php selected( $automation['status'] ?? '', 'active' ); ?>><?php esc_html_e( 'Active', 'wp-email-automations' ); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <!-- Conditions builder -->
        <div class="wea-section">
            <h2><?php esc_html_e( 'Conditions', 'wp-email-automations' ); ?> <small><?php esc_html_e( '(all must match)', 'wp-email-automations' ); ?></small></h2>
            <div id="wea-conditions-list"></div>
            <button type="button" class="button" id="wea-add-condition"><?php esc_html_e( '+ Add Condition', 'wp-email-automations' ); ?></button>
        </div>

        <!-- Actions builder -->
        <div class="wea-section">
            <h2><?php esc_html_e( 'Actions', 'wp-email-automations' ); ?></h2>
            <div id="wea-actions-list"></div>
            <button type="button" class="button" id="wea-add-action-email"><?php esc_html_e( '+ Send Email', 'wp-email-automations' ); ?></button>
            <button type="button" class="button" id="wea-add-action-webhook"><?php esc_html_e( '+ Webhook', 'wp-email-automations' ); ?></button>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Automation', 'wp-email-automations' ); ?></button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wea-automations' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'wp-email-automations' ); ?></a>
        </p>
    </form>

    </div><!-- #wea-automation-editor -->
</div>

<script>
// Embed template list for action builder
var weaTemplates = <?php echo wp_json_encode( array_map( fn( $t ) => [ 'id' => $t['id'], 'name' => $t['name'] ], $templates ) ); ?>;
</script>
<?php endif; ?>
