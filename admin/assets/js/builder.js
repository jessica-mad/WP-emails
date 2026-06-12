/* GrapesJS MJML Email Builder — WP Email Automations */
(function () {
    'use strict';

    var editor;
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
        '        <mj-image src="https://via.placeholder.com/600x80?text=Logo" alt="Logo" width="200px" />',
        '      </mj-column>',
        '    </mj-section>',
        '    <mj-section background-color="#ffffff" padding="20px">',
        '      <mj-column>',
        '        <mj-text font-size="24px" font-weight="bold" color="#111111">',
        '          Hola {{name}} 👋',
        '        </mj-text>',
        '        <mj-text>',
        '          Escribe aquí el contenido de tu email.',
        '        </mj-text>',
        '        <mj-button background-color="#6366f1" color="#ffffff" href="{{cta_url}}" border-radius="6px" font-size="16px">',
        '          Acción principal',
        '        </mj-button>',
        '      </mj-column>',
        '    </mj-section>',
        '    <mj-section background-color="#f4f4f4" padding="20px">',
        '      <mj-column>',
        '        <mj-text font-size="12px" color="#999999" align="center">',
        '          © {{year}} App Painting. Todos los derechos reservados.',
        '        </mj-text>',
        '      </mj-column>',
        '    </mj-section>',
        '  </mj-body>',
        '</mjml>',
    ].join('\n');

    // -------------------------------------------------------------------------
    // Init GrapesJS
    // -------------------------------------------------------------------------

    // grapesjs-mjml 0.4.x se auto-registra llamando a grapesjs.plugins.add('grapesjs-mjml', fn)
    // al cargarse el script — basta con pasar el string 'grapesjs-mjml' en plugins[].
    if ( typeof grapesjs === 'undefined' ) {
        showStatus('Error: GrapesJS no cargó. Comprueba la conexión a internet.', 'error');
        return;
    }

    editor = grapesjs.init({
        container: '#gjs',
        fromElement: false,
        storageManager: false,
        plugins: [ 'grapesjs-mjml' ],
        pluginsOpts: {
            'grapesjs-mjml': {
                columnsPadding: '0 0 0 0',
            }
        },
        // Paneles: bloques a la izquierda, estilos/propiedades a la derecha
        panels: { defaults: [] },
        blockManager: {
            appendTo: '#wea-blocks-panel',
            blocks: [],
        },
        styleManager: {
            appendTo: '#wea-styles-panel',
        },
        traitManager: {
            appendTo: '#wea-traits-panel',
        },
        layerManager: {
            appendTo: '#wea-layers-panel',
        },
        deviceManager: {
            devices: [
                { name: 'Desktop', width: '' },
                { name: 'Mobile',  width: '320px', widthMedia: '480px' },
            ]
        },
        canvas: {
            styles: [
                'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap',
            ],
        },
    });

    // -------------------------------------------------------------------------
    // Panel de bloques personalizado — botones de la barra superior
    // -------------------------------------------------------------------------
    var pm = editor.Panels;

    // Limpiar paneles por defecto del plugin y añadir los nuestros
    pm.getPanels().reset();

    pm.addPanel({
        id: 'top-bar',
        el: '#wea-editor-topbar',
        buttons: [
            {
                id:      'device-desktop',
                label:   '🖥 Desktop',
                command: 'set-device-desktop',
                active:  true,
                attributes: { title: 'Vista escritorio' },
            },
            {
                id:      'device-mobile',
                label:   '📱 Móvil',
                command: 'set-device-mobile',
                attributes: { title: 'Vista móvil' },
            },
            {
                id:      'undo',
                label:   '↩ Deshacer',
                command: 'core:undo',
                attributes: { title: 'Deshacer' },
            },
            {
                id:      'redo',
                label:   '↪ Rehacer',
                command: 'core:redo',
                attributes: { title: 'Rehacer' },
            },
            {
                id:      'show-code',
                label:   '&lt;/&gt; Código',
                command: 'mjml-code-viewer',
                attributes: { title: 'Ver/editar MJML' },
            },
            {
                id:      'clear-canvas',
                label:   '🗑 Limpiar',
                command: {
                    run: function(ed) {
                        if ( confirm('¿Limpiar el canvas y empezar de cero?') ) {
                            ed.runCommand('mjml-import', { content: defaultMjml });
                        }
                    }
                },
                attributes: { title: 'Limpiar canvas' },
            },
        ]
    });

    // Comandos de dispositivo
    editor.Commands.add('set-device-desktop', {
        run: function(ed) { ed.setDevice('Desktop'); },
    });
    editor.Commands.add('set-device-mobile', {
        run: function(ed) { ed.setDevice('Mobile'); },
    });

    // -------------------------------------------------------------------------
    // Tabs del panel izquierdo
    // -------------------------------------------------------------------------
    document.querySelectorAll('.wea-tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = btn.dataset.tab;
            document.querySelectorAll('.wea-tab-btn').forEach(function(b){ b.classList.remove('active'); });
            document.querySelectorAll('.wea-tab-panel').forEach(function(p){ p.style.display = 'none'; });
            btn.classList.add('active');
            var panel = document.getElementById('wea-' + target + '-panel');
            if (panel) panel.style.display = 'block';
        });
    });

    // -------------------------------------------------------------------------
    // Cargar contenido inicial
    // -------------------------------------------------------------------------
    editor.on('load', function() {
        if ( savedData.mjml_json && savedData.mjml_json !== 'null' ) {
            try {
                var projectData = JSON.parse( savedData.mjml_json );
                if ( projectData && typeof projectData === 'object' ) {
                    editor.loadProjectData( projectData );
                    return;
                }
            } catch (e) {
                console.warn('WEA builder: error cargando projectData, usando plantilla por defecto.', e);
            }
        }
        // Plantilla por defecto para plantillas nuevas
        setTimeout(function(){
            editor.runCommand('mjml-import', { content: defaultMjml });
        }, 100);
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

        // Obtener HTML compilado desde el canvas
        var html = '';
        try {
            // grapesjs-mjml compila a HTML; intentamos obtenerlo del wrapper
            var wrapper = editor.getWrapper();
            html = editor.getHtml() || '';
        } catch(e) {}

        var formData = new FormData();
        formData.append('action',      'wea_save_template');
        formData.append('_ajax_nonce', weaAdmin.nonce);
        formData.append('id',          templateId);
        formData.append('name',        name);
        formData.append('subject',     subject);
        formData.append('mjml_json',   JSON.stringify(projectData));
        formData.append('html',        html);

        showStatus('Guardando…', 'info');

        fetch(weaAdmin.ajaxUrl, { method: 'POST', body: formData })
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
    // Enviar email de prueba
    // -------------------------------------------------------------------------
    document.getElementById('wea-test-btn').addEventListener('click', function () {
        var to = prompt('Enviar email de prueba a:', '');
        if ( ! to || ! to.includes('@') ) return;

        var html    = editor.getHtml() || '';
        var subject = document.getElementById('wea-tmpl-subject').value.trim() || 'Email de prueba';

        var formData = new FormData();
        formData.append('action',      'wea_test_email');
        formData.append('_ajax_nonce', weaAdmin.nonce);
        formData.append('to',          to);
        formData.append('subject',     subject);
        formData.append('html',        html);

        showStatus('Enviando…', 'info');

        fetch(weaAdmin.ajaxUrl, { method: 'POST', body: formData })
            .then(function(r){ return r.json(); })
            .then(function(res){
                showStatus(
                    res.success ? '✓ Email enviado a ' + res.data.to : ('Error: ' + (res.data || 'wp_mail falló')),
                    res.success ? 'success' : 'error'
                );
            })
            .catch(function(){ showStatus('Error de red.', 'error'); });
    });

    // -------------------------------------------------------------------------
    // Barra de estado
    // -------------------------------------------------------------------------
    function showStatus(msg, type) {
        var el = document.getElementById('wea-builder-status');
        if ( ! el ) return;
        el.textContent = msg;
        el.className   = 'wea-builder-status wea-builder-status--' + (type || 'info');
        if ( type === 'success' ) {
            setTimeout(function(){ el.textContent = ''; el.className = 'wea-builder-status'; }, 3000);
        }
    }
})();
