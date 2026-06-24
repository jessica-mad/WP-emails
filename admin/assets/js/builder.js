/* GrapesJS MJML Email Builder — WP Email Automations */
(function () {
    'use strict';

    if ( typeof grapesjs === 'undefined' ) {
        document.getElementById('gjs').innerHTML = '<p style="padding:20px;color:red">Error: GrapesJS no cargó.</p>';
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
        '        <mj-text font-size="12px" color="#999999" align="center">© {{year}} Tu empresa.</mj-text>',
        '      </mj-column>',
        '    </mj-section>',
        '  </mj-body>',
        '</mjml>',
    ].join('\n');

    // grapesjs-mjml 1.x UMD: puede ser función directa o { default: fn }
    var mjmlRaw    = window['grapesjs-mjml'];
    var mjmlPlugin = typeof mjmlRaw === 'function'
        ? mjmlRaw
        : ( mjmlRaw && typeof mjmlRaw.default === 'function' ? mjmlRaw.default : null );

    var editorConfig = {
        container:      '#gjs',
        fromElement:    false,
        storageManager: false,
        height:         'calc(100vh - 96px)',
        width:          'auto',

        // ── Asset Manager: usar biblioteca de medios de WordPress ──────────────
        assetManager: {
            upload:   false,  // deshabilitar el uploader por defecto de GrapesJS
            assets:   [],
            // No abrimos el modal por defecto; usamos wp.media en su lugar
            showUrls: true,
        },
    };

    if ( mjmlPlugin ) {
        editorConfig.plugins     = [ mjmlPlugin ];
        editorConfig.pluginsOpts = {};
        editorConfig.pluginsOpts[ mjmlPlugin ] = {
            // Aseguramos que traits y styles estén activos
            columnsPadding: '0 0',
        };
    }

    var editor = grapesjs.init( editorConfig );

    // ── Interceptar apertura del Asset Manager → abrir WP Media Library ───────
    editor.on('run:open-assets', function (e) {
        // Cancelar el modal nativo de GrapesJS
        try { e.halt(); } catch(_) {}

        if ( typeof wp === 'undefined' || ! wp.media ) {
            // Fallback: prompt con URL
            var url = prompt('URL de la imagen:', 'https://');
            if ( url && url.length > 5 ) { applyImageUrl(url); }
            return;
        }

        var frame = wp.media({
            title:    'Seleccionar imagen',
            button:   { text: 'Usar esta imagen' },
            multiple: false,
            library:  { type: 'image' },
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            applyImageUrl( attachment.url );
        });

        frame.open();
    });

    function applyImageUrl(url) {
        var selected = editor.getSelected();
        if ( selected ) {
            // mj-image usa el atributo src
            if ( selected.get('type') === 'mj-image' || selected.attributes.tagName === 'mj-image' ) {
                selected.addAttributes({ src: url });
            } else {
                // intento genérico
                selected.addAttributes({ src: url });
            }
        }
        // También actualizar en el Asset Manager por si GrapesJS lo necesita internamente
        editor.AssetManager.add([ url ]);
        editor.AssetManager.render();
    }

    // ── Cargar contenido ──────────────────────────────────────────────────────
    editor.on('load', function () {
        try { editor.runCommand('open-blocks'); } catch(e) {}

        var mjmlToLoad = null;
        if ( savedData.mjml_content && savedData.mjml_content.length > 10 ) {
            mjmlToLoad = savedData.mjml_content;
        }
        editor.setComponents( mjmlToLoad || defaultMjml );

        // ── Fix cross-iframe drag & drop ──────────────────────────────────────
        // El canvas de GrapesJS vive en un <iframe>. El navegador no propaga
        // mousemove/mouseup al iframe durante un drag desde el panel de bloques.
        try {
            var frameEl  = editor.Canvas.getFrameEl();
            var frameWin = frameEl.contentWindow;
            var frameDoc = frameEl.contentDocument || frameWin.document;

            ['mousemove', 'mouseup', 'mousedown'].forEach(function (evtName) {
                document.addEventListener(evtName, function (e) {
                    try {
                        var rect = frameEl.getBoundingClientRect();
                        frameDoc.dispatchEvent( new MouseEvent(evtName, {
                            bubbles:    true,
                            cancelable: true,
                            view:       frameWin,
                            button:     e.button,
                            buttons:    e.buttons,
                            clientX:    e.clientX - rect.left,
                            clientY:    e.clientY - rect.top,
                            screenX:    e.screenX,
                            screenY:    e.screenY,
                            ctrlKey:    e.ctrlKey,
                            shiftKey:   e.shiftKey,
                            altKey:     e.altKey,
                            metaKey:    e.metaKey,
                        }) );
                    } catch(_) {}
                }, { passive: true });
            });
        } catch(e) {}
    });

    // ── Guardar ───────────────────────────────────────────────────────────────
    document.getElementById('wea-save-btn').addEventListener('click', function () {
        var name    = document.getElementById('wea-tmpl-name').value.trim();
        var subject = document.getElementById('wea-tmpl-subject').value.trim();

        if ( ! name ) {
            showStatus('Pon un nombre a la plantilla antes de guardar.', 'error');
            document.getElementById('wea-tmpl-name').focus();
            return;
        }

        var mjmlContent = editor.getHtml() || '';

        var fd = new FormData();
        fd.append('action',       'wea_save_template');
        fd.append('_ajax_nonce',  weaAdmin.nonce);
        fd.append('id',           templateId);
        fd.append('name',         name);
        fd.append('subject',      subject);
        fd.append('mjml_content', mjmlContent);
        fd.append('html',         '');

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

    // ── Enviar test ───────────────────────────────────────────────────────────
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
