<?php
namespace WEA;
defined('ABSPATH') || exit;

$section   = sanitize_key($_GET['section'] ?? 'list');
$page_num  = max(1, (int)($_GET['paged'] ?? 1));
$search    = sanitize_text_field($_GET['s'] ?? '');
$status_f  = sanitize_key($_GET['status'] ?? '');
$tag_f     = (int)($_GET['tag_id'] ?? 0);
$all_tags  = TagManager::get_all();

if ($section === 'tags') {
    // Tags management section
    ?>
    <div class="wrap wea-wrap">
        <h1><?php esc_html_e('Gestionar Tags', 'wp-email-automations'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wea-contacts')); ?>" class="page-title-action"><?php esc_html_e('← Volver a Contactos', 'wp-email-automations'); ?></a>
        </h1>

        <div class="wea-panel">
            <h2><?php esc_html_e('Tags', 'wp-email-automations'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Nombre', 'wp-email-automations'); ?></th>
                        <th><?php esc_html_e('Color', 'wp-email-automations'); ?></th>
                        <th><?php esc_html_e('Contactos', 'wp-email-automations'); ?></th>
                        <th><?php esc_html_e('Acciones', 'wp-email-automations'); ?></th>
                    </tr>
                </thead>
                <tbody id="wea-tags-list">
                <?php if (empty($all_tags)): ?>
                    <tr><td colspan="4"><?php esc_html_e('No hay tags todavía.', 'wp-email-automations'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($all_tags as $tag): ?>
                    <tr id="tag-row-<?php echo (int)$tag['id']; ?>">
                        <td>
                            <span class="wea-tag-chip" style="background:<?php echo esc_attr($tag['color']); ?>">
                                <?php echo esc_html($tag['name']); ?>
                            </span>
                        </td>
                        <td>
                            <span style="display:inline-block;width:18px;height:18px;border-radius:3px;background:<?php echo esc_attr($tag['color']); ?>;vertical-align:middle;border:1px solid #ccc"></span>
                            <?php echo esc_html($tag['color']); ?>
                        </td>
                        <td><?php echo (int)$tag['contact_count']; ?></td>
                        <td>
                            <button class="button button-small wea-edit-tag"
                                data-id="<?php echo (int)$tag['id']; ?>"
                                data-name="<?php echo esc_attr($tag['name']); ?>"
                                data-color="<?php echo esc_attr($tag['color']); ?>">
                                <?php esc_html_e('Editar', 'wp-email-automations'); ?>
                            </button>
                            <button class="button button-small wea-delete-tag" data-id="<?php echo (int)$tag['id']; ?>" style="color:#dc3232">
                                <?php esc_html_e('Eliminar', 'wp-email-automations'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <hr>
            <h3 id="wea-tag-form-title"><?php esc_html_e('Crear nuevo tag', 'wp-email-automations'); ?></h3>
            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <div>
                    <label for="wea-tag-name" style="display:block;font-weight:600;margin-bottom:4px"><?php esc_html_e('Nombre *', 'wp-email-automations'); ?></label>
                    <input type="text" id="wea-tag-name" placeholder="<?php esc_attr_e('Ej: VIP, Newsletter…', 'wp-email-automations'); ?>" style="width:220px">
                </div>
                <div>
                    <label for="wea-tag-color" style="display:block;font-weight:600;margin-bottom:4px"><?php esc_html_e('Color', 'wp-email-automations'); ?></label>
                    <input type="color" id="wea-tag-color" value="#6366f1">
                </div>
                <input type="hidden" id="wea-tag-id" value="0">
                <button class="button button-primary" id="wea-save-tag-btn"><?php esc_html_e('Guardar tag', 'wp-email-automations'); ?></button>
                <button class="button" id="wea-cancel-tag-btn" style="display:none"><?php esc_html_e('Cancelar', 'wp-email-automations'); ?></button>
            </div>
            <p id="wea-tag-msg" style="margin-top:8px;font-weight:600"></p>
        </div>
    </div>
    <?php
} else {
    // Default: contacts list
    $result = ContactManager::get_all([
        'page'     => $page_num,
        'per_page' => 25,
        'search'   => $search,
        'status'   => $status_f,
        'tag_id'   => $tag_f,
    ]);
    $contacts   = $result['items'];
    $total      = $result['total'];
    $total_pages = $result['pages'];

    $statuses = ['subscribed', 'unsubscribed', 'bounced', 'complained', 'pending'];
    ?>
    <div class="wrap wea-wrap">
        <h1><?php esc_html_e('Contacts', 'wp-email-automations'); ?>
            <button class="page-title-action" id="wea-add-contact-btn"><?php esc_html_e('+ Añadir contacto', 'wp-email-automations'); ?></button>
            <button class="page-title-action" id="wea-sync-btn"><?php esc_html_e('Sync WP Users', 'wp-email-automations'); ?></button>
            <button class="page-title-action" id="wea-import-csv-btn"><?php esc_html_e('Importar CSV', 'wp-email-automations'); ?></button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wea-contacts&section=tags')); ?>" class="page-title-action"><?php esc_html_e('Tags', 'wp-email-automations'); ?></a>
        </h1>

        <div id="wea-notice" style="display:none;margin:8px 0"></div>

        <!-- Filters -->
        <form method="get" action="">
            <input type="hidden" name="page" value="wea-contacts">
            <div class="wea-contacts-filters">
                <input type="text" name="s" placeholder="<?php esc_attr_e('Buscar…', 'wp-email-automations'); ?>" value="<?php echo esc_attr($search); ?>">
                <select name="status">
                    <option value=""><?php esc_html_e('Todos los estados', 'wp-email-automations'); ?></option>
                    <?php foreach ($statuses as $st): ?>
                    <option value="<?php echo esc_attr($st); ?>" <?php selected($status_f, $st); ?>><?php echo esc_html(ucfirst($st)); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="tag_id">
                    <option value="0"><?php esc_html_e('Todos los tags', 'wp-email-automations'); ?></option>
                    <?php foreach ($all_tags as $tag): ?>
                    <option value="<?php echo (int)$tag['id']; ?>" <?php selected($tag_f, (int)$tag['id']); ?>><?php echo esc_html($tag['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Buscar', 'wp-email-automations'), 'secondary', '', false); ?>
            </div>
        </form>

        <!-- Contacts table -->
        <div class="wea-panel" style="padding:0;overflow:hidden">
            <table class="wp-list-table widefat fixed striped" style="border:none">
                <thead>
                    <tr>
                        <th style="width:180px"><?php esc_html_e('Nombre', 'wp-email-automations'); ?></th>
                        <th><?php esc_html_e('Email', 'wp-email-automations'); ?></th>
                        <th style="width:160px"><?php esc_html_e('Tags', 'wp-email-automations'); ?></th>
                        <th style="width:110px"><?php esc_html_e('Estado', 'wp-email-automations'); ?></th>
                        <th style="width:100px"><?php esc_html_e('Fuente', 'wp-email-automations'); ?></th>
                        <th style="width:110px"><?php esc_html_e('Fecha', 'wp-email-automations'); ?></th>
                        <th style="width:130px"><?php esc_html_e('Acciones', 'wp-email-automations'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($contacts)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:24px"><?php esc_html_e('No se encontraron contactos.', 'wp-email-automations'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($contacts as $contact):
                        $ctags = ContactManager::get_tags((int)$contact['id']);
                        $name  = trim($contact['first_name'] . ' ' . $contact['last_name']) ?: '—';
                    ?>
                    <tr id="contact-row-<?php echo (int)$contact['id']; ?>">
                        <td><?php echo esc_html($name); ?></td>
                        <td><?php echo esc_html($contact['email']); ?></td>
                        <td>
                            <?php foreach ($ctags as $ct): ?>
                            <span class="wea-tag-chip" style="background:<?php echo esc_attr($ct['color']); ?>"><?php echo esc_html($ct['name']); ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <span class="wea-badge wea-badge--<?php echo esc_attr($contact['status']); ?>">
                                <?php echo esc_html($contact['status']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($contact['source']); ?></td>
                        <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($contact['created_at']))); ?></td>
                        <td>
                            <button class="button button-small wea-edit-contact"
                                data-id="<?php echo (int)$contact['id']; ?>"
                                data-email="<?php echo esc_attr($contact['email']); ?>"
                                data-first="<?php echo esc_attr($contact['first_name']); ?>"
                                data-last="<?php echo esc_attr($contact['last_name']); ?>"
                                data-status="<?php echo esc_attr($contact['status']); ?>"
                                data-tags="<?php echo esc_attr(implode(',', array_column($ctags, 'id'))); ?>">
                                <?php esc_html_e('Editar', 'wp-email-automations'); ?>
                            </button>
                            <button class="button button-small wea-delete-contact" data-id="<?php echo (int)$contact['id']; ?>" style="color:#dc3232">
                                <?php esc_html_e('Eliminar', 'wp-email-automations'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="wea-pagination" style="margin-top:12px;display:flex;gap:4px;align-items:center">
            <span style="margin-right:8px"><?php printf(esc_html__('Mostrando %1$d–%2$d de %3$d contactos', 'wp-email-automations'), ($page_num - 1) * 25 + 1, min($page_num * 25, $total), $total); ?></span>
            <?php for ($p = 1; $p <= $total_pages; $p++):
                $url = add_query_arg(['paged' => $p, 's' => $search, 'status' => $status_f, 'tag_id' => $tag_f ?: null]);
            ?>
            <a href="<?php echo esc_url($url); ?>" class="wea-page-btn button<?php echo $p === $page_num ? ' button-primary' : ''; ?>"><?php echo (int)$p; ?></a>
            <?php endfor; ?>
        </div>
        <?php else: ?>
        <p style="color:#888"><?php printf(esc_html__('Total: %d contactos', 'wp-email-automations'), $total); ?></p>
        <?php endif; ?>
    </div>

    <!-- Contact Modal -->
    <div id="wea-contact-modal" class="wea-modal" style="display:none">
        <div class="wea-modal__box">
            <h3 id="wea-modal-title"><?php esc_html_e('Añadir contacto', 'wp-email-automations'); ?></h3>
            <input type="hidden" id="cm-id" value="0">
            <label><?php esc_html_e('Email *', 'wp-email-automations'); ?></label>
            <input type="email" id="cm-email" placeholder="correo@ejemplo.com">
            <label><?php esc_html_e('Nombre', 'wp-email-automations'); ?></label>
            <input type="text" id="cm-first" placeholder="<?php esc_attr_e('Nombre', 'wp-email-automations'); ?>">
            <label><?php esc_html_e('Apellido', 'wp-email-automations'); ?></label>
            <input type="text" id="cm-last" placeholder="<?php esc_attr_e('Apellido', 'wp-email-automations'); ?>">
            <label><?php esc_html_e('Estado', 'wp-email-automations'); ?></label>
            <select id="cm-status">
                <option value="subscribed"><?php esc_html_e('Subscribed', 'wp-email-automations'); ?></option>
                <option value="unsubscribed"><?php esc_html_e('Unsubscribed', 'wp-email-automations'); ?></option>
                <option value="bounced"><?php esc_html_e('Bounced', 'wp-email-automations'); ?></option>
                <option value="complained"><?php esc_html_e('Complained', 'wp-email-automations'); ?></option>
                <option value="pending"><?php esc_html_e('Pending', 'wp-email-automations'); ?></option>
            </select>
            <?php if (!empty($all_tags)): ?>
            <label><?php esc_html_e('Tags', 'wp-email-automations'); ?></label>
            <div class="wea-tags-grid" id="cm-tags">
                <?php foreach ($all_tags as $tag): ?>
                <span>
                    <input type="checkbox" class="wea-tag-checkbox" id="cm-tag-<?php echo (int)$tag['id']; ?>" value="<?php echo (int)$tag['id']; ?>">
                    <label class="wea-tag-label" for="cm-tag-<?php echo (int)$tag['id']; ?>" style="background:<?php echo esc_attr($tag['color']); ?>;color:#fff">
                        <?php echo esc_html($tag['name']); ?>
                    </label>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <p id="cm-error" style="color:#dc3232;display:none"></p>
            <div class="wea-modal__actions">
                <button class="button" id="cm-cancel"><?php esc_html_e('Cancelar', 'wp-email-automations'); ?></button>
                <button class="button button-primary" id="cm-save"><?php esc_html_e('Guardar', 'wp-email-automations'); ?></button>
            </div>
        </div>
    </div>

    <!-- Hidden CSV file input -->
    <input type="file" id="wea-csv-file" accept=".csv" style="display:none">

    <script>
    (function() {
        const ajax = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
        const nonce = <?php echo json_encode( wp_create_nonce( 'wea_admin' ) ); ?>;

        function notice(msg, type) {
            const el = document.getElementById('wea-notice');
            el.innerHTML = '<div class="notice notice-' + type + ' inline"><p>' + msg + '</p></div>';
            el.style.display = '';
            setTimeout(() => { el.style.display = 'none'; }, 5000);
        }

        function post(action, data, onSuccess, onError) {
            const fd = new FormData();
            fd.append('action', action);
            fd.append('_ajax_nonce', nonce);
            for (const k in data) fd.append(k, data[k]);
            fetch(ajax, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => res.success ? onSuccess(res.data) : (onError ? onError(res.data) : notice(res.data || '<?php echo esc_js(__('Error', 'wp-email-automations')); ?>', 'error')))
                .catch(() => notice('<?php echo esc_js(__('Error de red', 'wp-email-automations')); ?>', 'error'));
        }

        // ── Sync WP Users ───────────────────────────────────────
        document.getElementById('wea-sync-btn').addEventListener('click', function() {
            this.disabled = true;
            this.textContent = '<?php echo esc_js(__('Sincronizando…', 'wp-email-automations')); ?>';
            const btn = this;
            post('wea_sync_wp_users', {}, function(data) {
                notice('<?php echo esc_js(__('Sincronizados:', 'wp-email-automations')); ?> ' + data.count + ' <?php echo esc_js(__('usuarios', 'wp-email-automations')); ?>', 'success');
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js(__('Sync WP Users', 'wp-email-automations')); ?>';
            });
        });

        // ── Import CSV ──────────────────────────────────────────
        document.getElementById('wea-import-csv-btn').addEventListener('click', function() {
            document.getElementById('wea-csv-file').click();
        });
        document.getElementById('wea-csv-file').addEventListener('change', function() {
            if (!this.files[0]) return;
            const fd = new FormData();
            fd.append('action', 'wea_import_csv');
            fd.append('_ajax_nonce', nonce);
            fd.append('csv', this.files[0]);
            fetch(ajax, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        notice('<?php echo esc_js(__('Importados:', 'wp-email-automations')); ?> ' + res.data.imported + ', <?php echo esc_js(__('omitidos:', 'wp-email-automations')); ?> ' + res.data.skipped, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        notice(res.data || '<?php echo esc_js(__('Error', 'wp-email-automations')); ?>', 'error');
                    }
                });
            this.value = '';
        });

        // ── Contact Modal ───────────────────────────────────────
        const modal = document.getElementById('wea-contact-modal');

        function openModal(data) {
            document.getElementById('wea-modal-title').textContent = data.id
                ? '<?php echo esc_js(__('Editar contacto', 'wp-email-automations')); ?>'
                : '<?php echo esc_js(__('Añadir contacto', 'wp-email-automations')); ?>';
            document.getElementById('cm-id').value      = data.id || 0;
            document.getElementById('cm-email').value   = data.email || '';
            document.getElementById('cm-first').value   = data.first || '';
            document.getElementById('cm-last').value    = data.last || '';
            document.getElementById('cm-status').value  = data.status || 'subscribed';
            document.getElementById('cm-error').style.display = 'none';
            // Reset tag checkboxes
            const checked = (data.tags || '').split(',').filter(Boolean);
            document.querySelectorAll('#cm-tags .wea-tag-checkbox').forEach(cb => {
                cb.checked = checked.includes(cb.value);
            });
            modal.style.display = '';
        }

        document.getElementById('wea-add-contact-btn').addEventListener('click', () => openModal({}));
        document.getElementById('cm-cancel').addEventListener('click', () => { modal.style.display = 'none'; });

        document.querySelectorAll('.wea-edit-contact').forEach(btn => {
            btn.addEventListener('click', function() {
                openModal({
                    id:     this.dataset.id,
                    email:  this.dataset.email,
                    first:  this.dataset.first,
                    last:   this.dataset.last,
                    status: this.dataset.status,
                    tags:   this.dataset.tags,
                });
            });
        });

        document.getElementById('cm-save').addEventListener('click', function() {
            const id     = document.getElementById('cm-id').value;
            const email  = document.getElementById('cm-email').value.trim();
            const errEl  = document.getElementById('cm-error');
            if (!email) { errEl.textContent = '<?php echo esc_js(__('El email es obligatorio.', 'wp-email-automations')); ?>'; errEl.style.display = ''; return; }

            const tagIds = [...document.querySelectorAll('#cm-tags .wea-tag-checkbox:checked')].map(cb => cb.value);

            post('wea_save_contact', {
                id:         id,
                email:      email,
                first_name: document.getElementById('cm-first').value,
                last_name:  document.getElementById('cm-last').value,
                status:     document.getElementById('cm-status').value,
            }, function(data) {
                const contactId = data.id;
                // Set tags
                const fdTags = new FormData();
                fdTags.append('action', 'wea_contact_tag');
                fdTags.append('_ajax_nonce', nonce);
                fdTags.append('contact_id', contactId);
                tagIds.forEach(t => fdTags.append('tag_ids[]', t));
                fetch(ajax, { method: 'POST', body: fdTags });

                modal.style.display = 'none';
                notice('<?php echo esc_js(__('Contacto guardado.', 'wp-email-automations')); ?>', 'success');
                setTimeout(() => location.reload(), 1000);
            }, function(msg) {
                errEl.textContent = msg || '<?php echo esc_js(__('Error al guardar.', 'wp-email-automations')); ?>';
                errEl.style.display = '';
            });
        });

        // ── Delete Contact ──────────────────────────────────────
        document.querySelectorAll('.wea-delete-contact').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('<?php echo esc_js(__('¿Eliminar este contacto?', 'wp-email-automations')); ?>')) return;
                const id  = this.dataset.id;
                const row = document.getElementById('contact-row-' + id);
                post('wea_delete_contact', { id }, function() {
                    if (row) row.remove();
                    notice('<?php echo esc_js(__('Contacto eliminado.', 'wp-email-automations')); ?>', 'success');
                });
            });
        });
    })();
    </script>
    <?php
}
// Tags section JS (always loaded for the tags page)
if ($section === 'tags'):
?>
<script>
(function() {
    const ajax = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    const nonce = <?php echo json_encode( wp_create_nonce( 'wea_admin' ) ); ?>;

    function post(action, data, onSuccess) {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('_ajax_nonce', nonce);
        for (const k in data) fd.append(k, data[k]);
        fetch(ajax, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) onSuccess(res.data);
                else document.getElementById('wea-tag-msg').textContent = res.data || 'Error';
            });
    }

    document.getElementById('wea-save-tag-btn').addEventListener('click', function() {
        const id    = document.getElementById('wea-tag-id').value;
        const name  = document.getElementById('wea-tag-name').value.trim();
        const color = document.getElementById('wea-tag-color').value;
        if (!name) { document.getElementById('wea-tag-msg').textContent = '<?php echo esc_js(__('El nombre es obligatorio.', 'wp-email-automations')); ?>'; return; }

        post('wea_save_tag', { id, name, color }, function(data) {
            document.getElementById('wea-tag-msg').textContent = '<?php echo esc_js(__('Tag guardado.', 'wp-email-automations')); ?>';
            setTimeout(() => location.reload(), 800);
        });
    });

    document.getElementById('wea-cancel-tag-btn').addEventListener('click', function() {
        document.getElementById('wea-tag-id').value = '0';
        document.getElementById('wea-tag-name').value = '';
        document.getElementById('wea-tag-color').value = '#6366f1';
        document.getElementById('wea-tag-form-title').textContent = '<?php echo esc_js(__('Crear nuevo tag', 'wp-email-automations')); ?>';
        this.style.display = 'none';
    });

    document.querySelectorAll('.wea-edit-tag').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('wea-tag-id').value = this.dataset.id;
            document.getElementById('wea-tag-name').value = this.dataset.name;
            document.getElementById('wea-tag-color').value = this.dataset.color;
            document.getElementById('wea-tag-form-title').textContent = '<?php echo esc_js(__('Editar tag', 'wp-email-automations')); ?>';
            document.getElementById('wea-cancel-tag-btn').style.display = '';
        });
    });

    document.querySelectorAll('.wea-delete-tag').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('<?php echo esc_js(__('¿Eliminar este tag?', 'wp-email-automations')); ?>')) return;
            const id  = this.dataset.id;
            const row = document.getElementById('tag-row-' + id);
            post('wea_delete_tag', { id }, function() {
                if (row) row.remove();
            });
        });
    });
})();
</script>
<?php endif; ?>
