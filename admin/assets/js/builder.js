/* GrapesJS MJML Email Builder — WP Email Automations */
(function () {
    'use strict';

    // ── Debug panel ──────────────────────────────────────────────
    var debugEl = document.createElement('div');
    debugEl.id  = 'wea-debug';
    debugEl.style.cssText = 'position:fixed;bottom:0;left:0;right:0;max-height:200px;overflow-y:auto;background:#0f172a;color:#94a3b8;font:12px/1.5 monospace;padding:8px 12px;z-index:999999;border-top:2px solid #6366f1;';
    document.body.appendChild(debugEl);

    function log(msg, color) {
        var line = document.createElement('div');
        line.style.color = color || '#94a3b8';
        line.textContent = '[' + new Date().toLocaleTimeString() + '] ' + msg;
        debugEl.appendChild(line);
        debugEl.scrollTop = debugEl.scrollHeight;
        console.log('[WEA Builder]', msg);
    }

    log('Script iniciado');
    log('grapesjs cargado: ' + (typeof grapesjs !== 'undefined'), typeof grapesjs !== 'undefined' ? '#4ade80' : '#f87171');
    log('window["grapesjs-mjml"]: ' + typeof window['grapesjs-mjml'], '#facc15');

    if ( typeof grapesjs === 'undefined' ) {
        log('ERROR: GrapesJS no disponible — abortando', '#f87171');
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

    // grapesjs-mjml 1.x UMD: puede ser la función directamente o { default: fn }
    var mjmlRaw    = window['grapesjs-mjml'];
    var mjmlPlugin = mjmlRaw && typeof mjmlRaw === 'function'
        ? mjmlRaw
        : ( mjmlRaw && typeof mjmlRaw.default === 'function' ? mjmlRaw.default : null );

    log('mjmlPlugin resuelto: ' + ( mjmlPlugin ? 'función ✓' : 'NULL — bloques MJML no cargarán' ), mjmlPlugin ? '#4ade80' : '#f87171');
    log('GrapesJS version: ' + ( grapesjs.version || 'desconocida' ), '#facc15');

    var editorConfig = {
        container:      '#gjs',
        fromElement:    false,
        storageManager: false,
        height:         'calc(100vh - 96px)',
        width:          'auto',
        // Renderizar canvas en el DOM principal, NO en iframe
        // Esto resuelve el bug de drag & drop entre documento e iframe
        // en GrapesJS 0.21+
        canvas: {
            styles:  [],
            scripts: [],
        },
        protectedCss: '',
    };

    if ( mjmlPlugin ) {
        editorConfig.plugins     = [ mjmlPlugin ];
        editorConfig.pluginsOpts = {};
        editorConfig.pluginsOpts[ mjmlPlugin ] = {};
    }

    var editor;
    try {
        editor = grapesjs.init( editorConfig );
        log('grapesjs.init() OK', '#4ade80');
    } catch(e) {
        log('ERROR en grapesjs.init(): ' + e.message, '#f87171');
        return;
    }

    // -------------------------------------------------------------------------
    // Cargar contenido inicial
    // -------------------------------------------------------------------------
    editor.on('load', function () {
        log('editor "load" event disparado', '#4ade80');

        var blocks = editor.BlockManager.getAll();
        log('Bloques registrados: ' + blocks.length, blocks.length ? '#4ade80' : '#f87171');

        // Inspeccionar el wrapper del canvas
        var wrapper = editor.getWrapper();
        log('Wrapper type: "' + wrapper.get('type') + '" tagName: "' + wrapper.get('tagName') + '"', '#facc15');
        log('Wrapper children: ' + wrapper.components().length, '#facc15');
        log('Wrapper droppable: ' + wrapper.get('droppable'), '#facc15');

        // Abrir bloques por defecto
        try { editor.runCommand('open-blocks'); log('open-blocks OK', '#4ade80'); } catch(e) { log('open-blocks ERR: ' + e.message, '#f87171'); }

        // Intentar cargar datos guardados
        var loadedOk = false;
        if ( savedData.mjml_json && savedData.mjml_json !== 'null' ) {
            try {
                var data = JSON.parse( savedData.mjml_json );
                log('Datos guardados parseados. Keys: ' + Object.keys(data).join(', '), '#facc15');
                if ( data && typeof data === 'object' ) {
                    if ( typeof editor.loadProjectData === 'function' ) {
                        editor.loadProjectData( data );
                    } else if ( data.components ) {
                        editor.setComponents( data.components );
                        if ( data.styles ) editor.setStyle( data.styles );
                    }
                    var childCount = editor.getWrapper().components().length;
                    log('Tras load: children = ' + childCount, childCount > 0 ? '#4ade80' : '#f87171');
                    loadedOk = childCount > 0;
                }
            } catch (e) { log('Load data ERR: ' + e.message, '#f87171'); }
        }

        // Si no hay contenido cargado, usar plantilla por defecto
        if ( ! loadedOk ) {
            log('Canvas vacío → ejecutando mjml-import con plantilla por defecto', '#facc15');
            try {
                editor.runCommand('mjml-import', { content: defaultMjml });
                var childCount2 = editor.getWrapper().components().length;
                log('mjml-import OK — children: ' + childCount2, childCount2 > 0 ? '#4ade80' : '#f87171');
            } catch(e) {
                log('mjml-import ERR: ' + e.message, '#f87171');
            }
        }

        // Log estado final del wrapper
        setTimeout(function(){
            var w = editor.getWrapper();
            log('FINAL wrapper type="' + w.get('type') + '" droppable=' + w.get('droppable') + ' children=' + w.components().length, '#60a5fa');
            w.components().each(function(c, i){
                if (i < 4) log('  child[' + i + ']: type="' + c.get('type') + '" droppable=' + c.get('droppable') + ' draggable=' + c.get('draggable'), '#60a5fa');
            });
        }, 600);
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

        // Serializar según versión de GrapesJS disponible
        var projectData;
        if ( typeof editor.getProjectData === 'function' ) {
            projectData = editor.getProjectData();
        } else {
            projectData = { components: editor.getComponents(), styles: editor.getStyle() };
        }

        var html = editor.getHtml() || '';

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

    // -------------------------------------------------------------------------
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
