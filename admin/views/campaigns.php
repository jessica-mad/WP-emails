<?php
namespace WEA;
defined('ABSPATH') || exit;

$action = sanitize_key($_GET['action'] ?? 'list');
$campaign_id = absint($_GET['id'] ?? 0);

// ─── EDIT / NEW VIEW ────────────────────────────────────────────────────────
if (in_array($action, ['edit', 'new'], true)) {
    $campaign  = $campaign_id ? CampaignManager::get($campaign_id) : null;
    $templates = TemplateManager::get_all();
    $all_tags  = TagManager::get_all();

    $default_from_name  = get_option('blogname', '');
    $default_from_email = get_option('admin_email', '');

    $name         = $campaign['name']          ?? '';
    $subject      = $campaign['subject']       ?? '';
    $from_name    = $campaign['from_name']     ?: $default_from_name;
    $from_email   = $campaign['from_email']    ?: $default_from_email;
    $template_id  = (int) ($campaign['template_id'] ?? 0);
    $filter_tags  = $campaign['filter_tags']   ?? '';
    $filter_status= $campaign['filter_status'] ?? 'subscribed';
    $status       = $campaign['status']        ?? 'draft';

    $selected_tags = array_filter(array_map('intval', explode(',', $filter_tags)));
    ?>
    <div class="wrap wea-wrap">
        <h1>
            <?php echo $campaign_id ? esc_html__('Editar Campaña', 'wp-email-automations') : esc_html__('Nueva Campaña', 'wp-email-automations'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wea-campaigns')); ?>" class="page-title-action">
                ← <?php esc_html_e('Volver a Campañas', 'wp-email-automations'); ?>
            </a>
        </h1>

        <?php if (!empty($_GET['saved'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Campaña guardada.', 'wp-email-automations'); ?></p></div>
        <?php endif; ?>

        <div class="wea-panel" style="max-width:800px">
            <form id="wea-campaign-form">
                <input type="hidden" id="campaign-id" value="<?php echo $campaign_id; ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="campaign-name"><?php esc_html_e('Nombre de la campaña', 'wp-email-automations'); ?></label></th>
                        <td><input type="text" id="campaign-name" class="regular-text" value="<?php echo esc_attr($name); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="campaign-subject"><?php esc_html_e('Asunto', 'wp-email-automations'); ?></label></th>
                        <td><input type="text" id="campaign-subject" class="regular-text" value="<?php echo esc_attr($subject); ?>" required></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Remitente', 'wp-email-automations'); ?></th>
                        <td>
                            <input type="text" id="campaign-from-name" class="regular-text" placeholder="<?php esc_attr_e('Nombre', 'wp-email-automations'); ?>" value="<?php echo esc_attr($from_name); ?>" style="margin-bottom:6px">
                            <br>
                            <input type="email" id="campaign-from-email" class="regular-text" placeholder="<?php esc_attr_e('Email', 'wp-email-automations'); ?>" value="<?php echo esc_attr($from_email); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="campaign-template"><?php esc_html_e('Plantilla', 'wp-email-automations'); ?></label></th>
                        <td>
                            <?php if (empty($templates)): ?>
                                <p class="description">
                                    <?php esc_html_e('No hay plantillas guardadas.', 'wp-email-automations'); ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=wea-templates&action=new')); ?>"><?php esc_html_e('Crear una plantilla', 'wp-email-automations'); ?></a>
                                </p>
                            <?php else: ?>
                                <select id="campaign-template" style="min-width:300px">
                                    <option value=""><?php esc_html_e('— Seleccionar plantilla —', 'wp-email-automations'); ?></option>
                                    <?php foreach ($templates as $tpl): ?>
                                        <option value="<?php echo (int) $tpl['id']; ?>"
                                            data-subject="<?php echo esc_attr($tpl['subject']); ?>"
                                            <?php selected($template_id, (int) $tpl['id']); ?>>
                                            <?php echo esc_html($tpl['name']); ?>
                                            <?php if ($tpl['subject']): ?>
                                                — <?php echo esc_html($tpl['subject']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('El HTML final se compilará al enviar.', 'wp-email-automations'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Estado de contacto', 'wp-email-automations'); ?></th>
                        <td>
                            <select id="campaign-filter-status">
                                <option value="subscribed" <?php selected($filter_status, 'subscribed'); ?>><?php esc_html_e('Suscritos', 'wp-email-automations'); ?></option>
                                <option value="unsubscribed" <?php selected($filter_status, 'unsubscribed'); ?>><?php esc_html_e('Desuscritos', 'wp-email-automations'); ?></option>
                                <option value="pending" <?php selected($filter_status, 'pending'); ?>><?php esc_html_e('Pendientes', 'wp-email-automations'); ?></option>
                                <option value="bounced" <?php selected($filter_status, 'bounced'); ?>><?php esc_html_e('Rebotados', 'wp-email-automations'); ?></option>
                                <option value="complained" <?php selected($filter_status, 'complained'); ?>><?php esc_html_e('Reclamaciones', 'wp-email-automations'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <?php if (!empty($all_tags)): ?>
                    <tr>
                        <th><?php esc_html_e('Filtrar por tags', 'wp-email-automations'); ?></th>
                        <td>
                            <div style="max-height:160px;overflow-y:auto;border:1px solid #ddd;padding:8px;border-radius:4px;background:#fff">
                                <?php foreach ($all_tags as $tag): ?>
                                    <label style="display:block;margin-bottom:4px">
                                        <input type="checkbox" class="wea-tag-filter" value="<?php echo (int) $tag['id']; ?>"
                                            <?php checked(in_array((int)$tag['id'], $selected_tags, true)); ?>>
                                        <span class="wea-tag-chip" style="background:<?php echo esc_attr($tag['color']); ?>"><?php echo esc_html($tag['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php esc_html_e('Sin selección = todos los contactos del estado elegido.', 'wp-email-automations'); ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php esc_html_e('Previsualizar destinatarios', 'wp-email-automations'); ?></th>
                        <td>
                            <button type="button" id="wea-preview-recipients" class="button"><?php esc_html_e('Ver destinatarios', 'wp-email-automations'); ?></button>
                            <span id="wea-recipients-preview" style="margin-left:12px;color:#555"></span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Envío', 'wp-email-automations'); ?></th>
                        <td>
                            <label><input type="radio" name="send_mode" value="now" checked> <?php esc_html_e('Enviar ahora', 'wp-email-automations'); ?></label>
                            &nbsp;&nbsp;
                            <label><input type="radio" name="send_mode" value="schedule"> <?php esc_html_e('Programar', 'wp-email-automations'); ?></label>
                            <div id="wea-schedule-picker" style="display:none;margin-top:8px">
                                <input type="datetime-local" id="campaign-scheduled-at">
                            </div>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="button" id="wea-save-draft" class="button button-secondary"><?php esc_html_e('Guardar borrador', 'wp-email-automations'); ?></button>
                    <?php if (!$campaign_id || $status === 'draft'): ?>
                        <button type="button" id="wea-send-now" class="button button-primary"><?php esc_html_e('Enviar ahora', 'wp-email-automations'); ?></button>
                        <button type="button" id="wea-schedule-send" class="button button-primary" style="display:none"><?php esc_html_e('Programar envío', 'wp-email-automations'); ?></button>
                    <?php endif; ?>
                    <span id="wea-campaign-msg" style="margin-left:12px"></span>
                </p>
            </form>
        </div>
    </div>

    <script>
    (function() {
        const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        const nonce   = <?php echo wp_json_encode(wp_create_nonce('wea_admin')); ?>;

        // Template picker: auto-fill subject if empty
        const tmplSelect = document.getElementById('campaign-template');
        if (tmplSelect) {
            tmplSelect.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                const subj = document.getElementById('campaign-subject');
                if (opt && opt.dataset.subject && !subj.value) {
                    subj.value = opt.dataset.subject;
                }
            });
        }

        // Schedule toggle
        document.querySelectorAll('input[name="send_mode"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const picker = document.getElementById('wea-schedule-picker');
                const btnNow = document.getElementById('wea-send-now');
                const btnSch = document.getElementById('wea-schedule-send');
                if (this.value === 'schedule') {
                    picker && (picker.style.display = 'block');
                    btnNow  && (btnNow.style.display = 'none');
                    btnSch  && (btnSch.style.display = '');
                } else {
                    picker && (picker.style.display = 'none');
                    btnNow  && (btnNow.style.display = '');
                    btnSch  && (btnSch.style.display = 'none');
                }
            });
        });

        function getFilterTags() {
            return Array.from(document.querySelectorAll('.wea-tag-filter:checked')).map(el => el.value).join(',');
        }

        function getFormData() {
            return {
                id:            document.getElementById('campaign-id').value,
                name:          document.getElementById('campaign-name').value,
                subject:       document.getElementById('campaign-subject').value,
                from_name:     document.getElementById('campaign-from-name').value,
                from_email:    document.getElementById('campaign-from-email').value,
                template_id:   tmplSelect ? tmplSelect.value : '',
                filter_tags:   getFilterTags(),
                filter_status: document.getElementById('campaign-filter-status').value,
                status:        'draft',
            };
        }

        function showMsg(msg, isError) {
            const el = document.getElementById('wea-campaign-msg');
            el.textContent = msg;
            el.style.color = isError ? '#c00' : '#0a6';
        }

        async function ajaxPost(data) {
            const fd = new FormData();
            Object.entries(data).forEach(([k, v]) => fd.append(k, v));
            fd.append('nonce', nonce);
            const r = await fetch(ajaxUrl, { method: 'POST', body: fd });
            return r.json();
        }

        // Save draft
        document.getElementById('wea-save-draft').addEventListener('click', async function() {
            const data = getFormData();
            data.action = 'wea_save_campaign';
            const res = await ajaxPost(data);
            if (res.success) {
                const newId = res.data.id;
                document.getElementById('campaign-id').value = newId;
                showMsg('<?php esc_html_e('Borrador guardado.', 'wp-email-automations'); ?>');
                if (!window.location.search.includes('id=')) {
                    history.replaceState(null, '', '?page=wea-campaigns&action=edit&id=' + newId + '&saved=1');
                }
            } else {
                showMsg(res.data || '<?php esc_html_e('Error al guardar.', 'wp-email-automations'); ?>', true);
            }
        });

        // Send now
        const btnSendNow = document.getElementById('wea-send-now');
        if (btnSendNow) {
            btnSendNow.addEventListener('click', async function() {
                const data = getFormData();
                // First save
                data.action = 'wea_save_campaign';
                const saveRes = await ajaxPost(data);
                if (!saveRes.success) { showMsg(saveRes.data || 'Error', true); return; }
                const id = saveRes.data.id;
                document.getElementById('campaign-id').value = id;
                // Then dispatch
                const dispRes = await ajaxPost({ action: 'wea_send_campaign', id, nonce });
                if (dispRes.success) {
                    showMsg('<?php esc_html_e('Campaña enviando...', 'wp-email-automations'); ?>');
                    setTimeout(() => { window.location = '?page=wea-campaigns'; }, 1500);
                } else {
                    showMsg(dispRes.data || 'Error', true);
                }
            });
        }

        // Schedule send
        const btnSchedule = document.getElementById('wea-schedule-send');
        if (btnSchedule) {
            btnSchedule.addEventListener('click', async function() {
                const scheduledAt = document.getElementById('campaign-scheduled-at').value;
                if (!scheduledAt) { showMsg('<?php esc_html_e('Selecciona fecha y hora.', 'wp-email-automations'); ?>', true); return; }
                const data = getFormData();
                data.action = 'wea_save_campaign';
                const saveRes = await ajaxPost(data);
                if (!saveRes.success) { showMsg(saveRes.data || 'Error', true); return; }
                const id = saveRes.data.id;
                document.getElementById('campaign-id').value = id;
                const schRes = await ajaxPost({ action: 'wea_schedule_campaign', id, scheduled_at: scheduledAt, nonce });
                if (schRes.success) {
                    showMsg('<?php esc_html_e('Campaña programada.', 'wp-email-automations'); ?>');
                    setTimeout(() => { window.location = '?page=wea-campaigns'; }, 1500);
                } else {
                    showMsg(schRes.data || 'Error', true);
                }
            });
        }

        // Preview recipients
        document.getElementById('wea-preview-recipients').addEventListener('click', async function() {
            const preview = document.getElementById('wea-recipients-preview');
            preview.textContent = '<?php esc_html_e('Cargando...', 'wp-email-automations'); ?>';
            const res = await ajaxPost({
                action:        'wea_preview_recipients',
                nonce,
                filter_tags:   getFilterTags(),
                filter_status: document.getElementById('campaign-filter-status').value,
            });
            if (res.success) {
                const d = res.data;
                preview.textContent = d.count + ' <?php esc_html_e('destinatarios', 'wp-email-automations'); ?>'
                    + (d.sample.length ? ' (' + d.sample.join(', ') + (d.count > 5 ? '...' : '') + ')' : '');
            } else {
                preview.textContent = '<?php esc_html_e('Error', 'wp-email-automations'); ?>';
            }
        });
    })();
    </script>
    <?php
    return;
}

// ─── LIST VIEW ───────────────────────────────────────────────────────────────
$page_num  = max(1, (int)($_GET['paged'] ?? 1));
$status_f  = sanitize_key($_GET['status'] ?? '');
$data      = CampaignManager::get_all(['page' => $page_num, 'per_page' => 20, 'status' => $status_f]);
$campaigns = $data['items'];
$total     = $data['total'];
$pages     = $data['pages'];

$status_labels = [
    'draft'     => ['label' => __('Borrador',   'wp-email-automations'), 'color' => '#888'],
    'scheduled' => ['label' => __('Programada', 'wp-email-automations'), 'color' => '#0073aa'],
    'sending'   => ['label' => __('Enviando',   'wp-email-automations'), 'color' => '#d63638'],
    'sent'      => ['label' => __('Enviada',    'wp-email-automations'), 'color' => '#00a32a'],
    'cancelled' => ['label' => __('Cancelada',  'wp-email-automations'), 'color' => '#888'],
];
?>
<div class="wrap wea-wrap">
    <h1>
        <?php esc_html_e('Campañas', 'wp-email-automations'); ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wea-campaigns&action=new')); ?>" class="page-title-action">
            + <?php esc_html_e('Nueva campaña', 'wp-email-automations'); ?>
        </a>
    </h1>

    <?php if (!empty($_GET['sent'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Campaña despachada.', 'wp-email-automations'); ?></p></div>
    <?php endif; ?>

    <!-- Status filter tabs -->
    <ul class="subsubsub">
        <li><a href="<?php echo esc_url(admin_url('admin.php?page=wea-campaigns')); ?>" <?php echo !$status_f ? 'class="current"' : ''; ?>><?php esc_html_e('Todas', 'wp-email-automations'); ?></a> |</li>
        <?php foreach ($status_labels as $s => $sl): ?>
            <li><a href="<?php echo esc_url(admin_url('admin.php?page=wea-campaigns&status=' . $s)); ?>" <?php echo $status_f === $s ? 'class="current"' : ''; ?>><?php echo esc_html($sl['label']); ?></a>
            <?php echo $s !== 'cancelled' ? ' |' : ''; ?></li>
        <?php endforeach; ?>
    </ul>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:22%"><?php esc_html_e('Nombre', 'wp-email-automations'); ?></th>
                <th style="width:25%"><?php esc_html_e('Asunto', 'wp-email-automations'); ?></th>
                <th style="width:10%"><?php esc_html_e('Estado', 'wp-email-automations'); ?></th>
                <th style="width:8%"><?php esc_html_e('Dest.', 'wp-email-automations'); ?></th>
                <th style="width:7%"><?php esc_html_e('Enviados', 'wp-email-automations'); ?></th>
                <th style="width:7%"><?php esc_html_e('Aperturas', 'wp-email-automations'); ?></th>
                <th style="width:7%"><?php esc_html_e('Clics', 'wp-email-automations'); ?></th>
                <th style="width:14%"><?php esc_html_e('Fecha', 'wp-email-automations'); ?></th>
                <th style="width:10%"><?php esc_html_e('Acciones', 'wp-email-automations'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($campaigns)): ?>
            <tr><td colspan="9"><?php esc_html_e('No hay campañas todavía.', 'wp-email-automations'); ?></td></tr>
        <?php else: ?>
            <?php foreach ($campaigns as $c):
                $cid    = (int) $c['id'];
                $sl     = $status_labels[$c['status']] ?? ['label' => $c['status'], 'color' => '#888'];
                $date   = $c['sent_at'] ?: $c['scheduled_at'] ?: $c['created_at'];
                $opens_pct  = $c['total_sent'] > 0 ? round($c['total_opens']  / $c['total_sent'] * 100) : 0;
                $clicks_pct = $c['total_sent'] > 0 ? round($c['total_clicks'] / $c['total_sent'] * 100) : 0;
            ?>
            <tr id="campaign-row-<?php echo $cid; ?>">
                <td><strong><?php echo esc_html($c['name']); ?></strong></td>
                <td><?php echo esc_html($c['subject']); ?></td>
                <td>
                    <span style="display:inline-block;padding:2px 8px;border-radius:12px;background:<?php echo esc_attr($sl['color']); ?>;color:#fff;font-size:11px;font-weight:600">
                        <?php echo esc_html($sl['label']); ?>
                    </span>
                </td>
                <td><?php echo (int) $c['total_recipients']; ?></td>
                <td><?php echo (int) $c['total_sent']; ?></td>
                <td><?php echo (int) $c['total_opens']; ?><?php echo $c['total_sent'] > 0 ? ' <small>(' . $opens_pct . '%)</small>' : ''; ?></td>
                <td><?php echo (int) $c['total_clicks']; ?><?php echo $c['total_sent'] > 0 ? ' <small>(' . $clicks_pct . '%)</small>' : ''; ?></td>
                <td><?php echo esc_html($date ? wp_date('d/m/Y H:i', strtotime($date)) : '—'); ?></td>
                <td>
                    <?php if (in_array($c['status'], ['draft', 'scheduled'], true)): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wea-campaigns&action=edit&id=' . $cid)); ?>" class="button button-small"><?php esc_html_e('Editar', 'wp-email-automations'); ?></a>
                    <?php endif; ?>
                    <?php if ($c['status'] === 'draft'): ?>
                        <button class="button button-small wea-send-now" data-id="<?php echo $cid; ?>"><?php esc_html_e('Enviar', 'wp-email-automations'); ?></button>
                        <button class="button button-small button-link-delete wea-delete-campaign" data-id="<?php echo $cid; ?>"><?php esc_html_e('Eliminar', 'wp-email-automations'); ?></button>
                    <?php endif; ?>
                    <?php if (in_array($c['status'], ['scheduled', 'sending'], true)): ?>
                        <button class="button button-small wea-cancel-campaign" data-id="<?php echo $cid; ?>"><?php esc_html_e('Cancelar', 'wp-email-automations'); ?></button>
                    <?php endif; ?>
                    <button class="button button-small wea-view-stats" data-id="<?php echo $cid; ?>" data-name="<?php echo esc_attr($c['name']); ?>"><?php esc_html_e('Stats', 'wp-email-automations'); ?></button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php echo paginate_links([
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'current'   => $page_num,
                    'total'     => $pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ]); ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Stats Modal -->
<div id="wea-stats-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;width:90%;max-width:900px;max-height:85vh;overflow:auto;padding:24px;position:relative">
        <button id="wea-close-modal" style="position:absolute;top:12px;right:16px;border:none;background:none;font-size:20px;cursor:pointer">&times;</button>
        <h2 id="wea-modal-title"></h2>
        <div id="wea-modal-aggregate" style="margin-bottom:16px;display:flex;gap:24px"></div>
        <div id="wea-modal-body"></div>
    </div>
</div>

<script>
(function() {
    const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    const nonce   = <?php echo wp_json_encode(wp_create_nonce('wea_admin')); ?>;

    async function ajaxPost(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        fd.append('nonce', nonce);
        const r = await fetch(ajaxUrl, { method: 'POST', body: fd });
        return r.json();
    }

    // Send now
    document.querySelectorAll('.wea-send-now').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            if (!confirm('<?php esc_html_e('¿Enviar esta campaña ahora?', 'wp-email-automations'); ?>')) return;
            const id = this.dataset.id;
            const res = await ajaxPost({ action: 'wea_send_campaign', id });
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || '<?php esc_html_e('Error al enviar.', 'wp-email-automations'); ?>');
            }
        });
    });

    // Cancel
    document.querySelectorAll('.wea-cancel-campaign').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            if (!confirm('<?php esc_html_e('¿Cancelar esta campaña?', 'wp-email-automations'); ?>')) return;
            const id = this.dataset.id;
            const res = await ajaxPost({ action: 'wea_cancel_campaign', id });
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || '<?php esc_html_e('Error.', 'wp-email-automations'); ?>');
            }
        });
    });

    // Delete
    document.querySelectorAll('.wea-delete-campaign').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            if (!confirm('<?php esc_html_e('¿Eliminar esta campaña?', 'wp-email-automations'); ?>')) return;
            const id = this.dataset.id;
            const res = await ajaxPost({ action: 'wea_delete_campaign', id });
            if (res.success) {
                document.getElementById('campaign-row-' + id)?.remove();
            } else {
                alert(res.data || '<?php esc_html_e('Error.', 'wp-email-automations'); ?>');
            }
        });
    });

    // Stats modal
    const modal     = document.getElementById('wea-stats-modal');
    const modalTitle= document.getElementById('wea-modal-title');
    const modalAgg  = document.getElementById('wea-modal-aggregate');
    const modalBody = document.getElementById('wea-modal-body');

    document.getElementById('wea-close-modal').addEventListener('click', function() {
        modal.style.display = 'none';
    });
    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.style.display = 'none';
    });

    document.querySelectorAll('.wea-view-stats').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const id   = this.dataset.id;
            const name = this.dataset.name;
            modalTitle.textContent = name;
            modalAgg.innerHTML  = '<em><?php esc_html_e('Cargando...', 'wp-email-automations'); ?></em>';
            modalBody.innerHTML = '';
            modal.style.display = 'flex';

            const res = await ajaxPost({ action: 'wea_campaign_stats', id });
            if (!res.success) {
                modalAgg.innerHTML = '<span style="color:#c00">' + (res.data || 'Error') + '</span>';
                return;
            }

            const stats = res.data.stats;
            const totalSent   = stats.length;
            const totalOpens  = stats.filter(s => s.open_count > 0).length;
            const totalClicks = stats.filter(s => s.click_count > 0).length;
            const pct = n => totalSent > 0 ? Math.round(n / totalSent * 100) + '%' : '—';

            modalAgg.innerHTML =
                '<div style="text-align:center;padding:12px 20px;background:#f0f0f0;border-radius:6px">' +
                    '<div style="font-size:22px;font-weight:700">' + totalSent + '</div>' +
                    '<div style="font-size:12px;color:#555"><?php esc_html_e('Enviados', 'wp-email-automations'); ?></div>' +
                '</div>' +
                '<div style="text-align:center;padding:12px 20px;background:#f0f0f0;border-radius:6px">' +
                    '<div style="font-size:22px;font-weight:700">' + totalOpens + ' <small style="font-size:13px;color:#555">(' + pct(totalOpens) + ')</small></div>' +
                    '<div style="font-size:12px;color:#555"><?php esc_html_e('Aperturas únicas', 'wp-email-automations'); ?></div>' +
                '</div>' +
                '<div style="text-align:center;padding:12px 20px;background:#f0f0f0;border-radius:6px">' +
                    '<div style="font-size:22px;font-weight:700">' + totalClicks + ' <small style="font-size:13px;color:#555">(' + pct(totalClicks) + ')</small></div>' +
                    '<div style="font-size:12px;color:#555"><?php esc_html_e('Clics únicos', 'wp-email-automations'); ?></div>' +
                '</div>';

            if (!stats.length) {
                modalBody.innerHTML = '<p><?php esc_html_e('Sin datos todavía.', 'wp-email-automations'); ?></p>';
                return;
            }

            let rows = '';
            stats.forEach(function(s) {
                const clickLinks = s.clicks.map(function(cl) {
                    return '<a href="' + cl.url + '" target="_blank" rel="noopener noreferrer" style="display:block;word-break:break-all;font-size:11px">' + cl.url + '</a>' +
                           '<small style="color:#888">' + cl.clicked_at + '</small>';
                }).join('');

                rows += '<tr>' +
                    '<td>' + (s.name || '—') + '</td>' +
                    '<td>' + s.email + '</td>' +
                    '<td>' + (s.sent_at || '—') + '</td>' +
                    '<td>' + s.open_count + (s.first_opened_at ? '<br><small>' + s.first_opened_at + '</small>' : '') + '</td>' +
                    '<td>' + s.click_count + (s.first_clicked_at ? '<br><small>' + s.first_clicked_at + '</small>' : '') + '</td>' +
                    '<td>' + (clickLinks || '—') + '</td>' +
                '</tr>';
            });

            modalBody.innerHTML =
                '<table class="wp-list-table widefat fixed striped" style="font-size:13px">' +
                '<thead><tr>' +
                    '<th><?php esc_html_e('Nombre', 'wp-email-automations'); ?></th>' +
                    '<th><?php esc_html_e('Email', 'wp-email-automations'); ?></th>' +
                    '<th><?php esc_html_e('Enviado', 'wp-email-automations'); ?></th>' +
                    '<th><?php esc_html_e('Aperturas', 'wp-email-automations'); ?></th>' +
                    '<th><?php esc_html_e('Clics', 'wp-email-automations'); ?></th>' +
                    '<th><?php esc_html_e('URLs clickadas', 'wp-email-automations'); ?></th>' +
                '</tr></thead><tbody>' + rows + '</tbody></table>';
        });
    });
})();
</script>
