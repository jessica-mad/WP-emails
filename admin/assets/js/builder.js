/* GrapesJS MJML Email Builder — WP Email Automations */
(function () {
    'use strict';

    if ( typeof grapesjs === 'undefined' ) {
        document.getElementById('gjs').innerHTML = '<p style="padding:20px;color:red">Error: GrapesJS no cargó. Comprueba la conexión a internet y recarga.</p>';
        return;
    }

    var templateId = parseInt( document.getElementById('wea-tmpl-id').value, 10 ) || 0;
    var savedData  = window.weaTemplateData || {};

    var defaultMjml = [
        '<mjml>',
        '  <mj-head>',
        '    <mj-attributes>',
        '      <mj-all font-family="Arial, sans-serif" />',
        '      <mj-text font-size="14px" color="#333333" line-height="1.6" />',
        '    </mj-attributes>',
        '  </mj-head>',
        '  <mj-body background-color="#f4f4f4">',
        '    <mj-section background-color="#ffffff" padding="30px 20px">',
        '      <mj-column>',
        '        <mj-text font-size="24px" font-weight="bold" color="#111111">Hola {{name}} 👋</mj-text>',
        '        <mj-text>Escribe aquí el contenido de tu email.</mj-text>',
        '        <mj-button background-color="#6366f1" color="#ffffff" href="{{cta_url}}" border-radius="6px">Acción principal</mj-button>',
        '      </mj-column>',
        '    </mj-section>',
        '    <mj-section background-color="#f4f4f4" padding="20px">',
        '      <mj-column>',
        '        <mj-text font-size="12px" color="#999999" align="center">© {{year}} Tu empresa. Todos los derechos reservados.</mj-text>',
        '      </mj-column>',
        '    </mj-section>',
        '  </mj-body>',
        '</mjml>',
    ].join('\n');

    // -------------------------------------------------------------------------
    // Init GrapesJS — dejamos que el plugin MJML gestione sus paneles
    // -------------------------------------------------------------------------
    var editor = grapesjs.init({
        container:      '#gjs',
        fromElement:    false,
        storageManager: false,
        plugins:        ['grapesjs-mjml'],
        pluginsOpts: {
            'grapesjs-mjml': {}
        },
        height: 'calc(100vh - 110px)',
        width:  'auto',
    });

    // -------------------------------------------------------------------------
    // Cargar contenido inicial
    // -------------------------------------------------------------------------
    editor.on('load', function () {
        if ( savedData.mjml_json && savedData.mjml_json !== 'null' ) {
            try {
                var data = JSON.parse( savedData.mjml_json );
                if ( data && typeof data === 'object' && ( data.pages || data.styles || data.components ) ) {
                    editor.loadProjectData( data );
                    return;
                }
            } catch (e) {}
        }
        // Plantilla por defecto
        editor.runCommand('mjml-import', { content: defaultMjml });
    });

    // -------------------------------------------------------------------------
    // Guardar
    // -------------------------------------------------------------------------
    document.getElementById('wea-save-btn').addEventListener('click', function () {
        var name    = document.getElementById('wea-tmpl-name').value.trim();
        var subject = document.getElementById('wea-tmpl-subject').value.trim();

        if ( ! name ) {
            showStatus('Pon un nombre a la plantilla antes de guardar.', 'error');
            document.getElementById('wea-tmpl-name').focus();
            return;
        }

        var projectData = editor.getProjectData();
        var html        = editor.getHtml() || '';

        var fd = new FormData();
        fd.append('action',      'wea_save_template');
        fd.append('_ajax_nonce', weaAdmin.nonce);
        fd.append('id',          templateId);
        fd.append('name',        name);
        fd.append('subject',     subject);
        fd.append('mjml_json',   JSON.stringify(projectData));
        fd.append('html',        html);

        showStatus('Guardando…', 'info');

        fetch(weaAdmin.ajaxUrl, { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if ( res.success ) {
                    templateId = res.data.id;
                    document.getElementById('wea-tmpl-id').value = templateId;
                    var url = new URL(location.href);
                    url.searchParams.set('action', 'edit');
                    url.searchParams.set('id', templateId);
                    history.replaceState(null, '', url.toString());
                    showStatus('✓ Guardado', 'success');
                } else {
                    showStatus('Error al guardar.', 'error');
                }
            })
            .catch(function(){ showStatus('Error de red.', 'error'); });
    });

    // -------------------------------------------------------------------------
    // Enviar test
    // -------------------------------------------------------------------------
    document.getElementById('wea-test-btn').addEventListener('click', function () {
        var to = prompt('Enviar email de prueba a:', '');
        if ( ! to || ! to.includes('@') ) return;

        var subject = document.getElementById('wea-tmpl-subject').value.trim() || 'Email de prueba';
        var html    = editor.getHtml() || '';

        var fd = new FormData();
        fd.append('action',      'wea_test_email');
        fd.append('_ajax_nonce', weaAdmin.nonce);
        fd.append('to',          to);
        fd.append('subject',     subject);
        fd.append('html',        html);

        showStatus('Enviando…', 'info');

        fetch(weaAdmin.ajaxUrl, { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(res){
                showStatus(
                    res.success ? '✓ Email enviado a ' + res.data.to : 'Error: ' + (res.data || 'wp_mail falló'),
                    res.success ? 'success' : 'error'
                );
            })
            .catch(function(){ showStatus('Error de red.', 'error'); });
    });

    function showStatus(msg, type) {
        var el = document.getElementById('wea-builder-status');
        if (!el) return;
        el.textContent = msg;
        el.className   = 'wea-builder-status wea-builder-status--' + (type || 'info');
        if (type === 'success') {
            setTimeout(function(){ el.textContent = ''; el.className = 'wea-builder-status'; }, 3000);
        }
    }
})();
