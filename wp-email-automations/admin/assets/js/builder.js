/* GrapesJS MJML Email Builder */
(function () {
    'use strict';

    var editor;
    var templateId  = parseInt( document.getElementById('wea-tmpl-id').value, 10 ) || 0;
    var savedData   = window.weaTemplateData || {};

    // Default starter template
    var defaultMjml = '<mjml><mj-body><mj-section><mj-column><mj-text font-size="20px" font-weight="bold" align="center">Hello {{name}}!</mj-text><mj-text>Welcome to our platform. We\'re glad to have you.</mj-text><mj-button href="{{cta_url}}">Get Started</mj-button></mj-column></mj-section></mj-body></mjml>';

    // Init GrapesJS with MJML plugin
    editor = grapesjs.init({
        container: '#gjs',
        fromElement: false,
        height: '80vh',
        storageManager: false,
        plugins: ['grapesjs-mjml'],
        pluginsOpts: {
            'grapesjs-mjml': {
                // Use local MJML compilation if available, otherwise rely on plugin defaults
            }
        },
    });

    // Load existing template data
    if ( savedData.mjml_json ) {
        try {
            var projectData = JSON.parse( savedData.mjml_json );
            editor.loadProjectData( projectData );
        } catch (e) {
            console.warn('WEA: Could not load project data, loading default template.', e);
            editor.runCommand('mjml-import', { content: defaultMjml });
        }
    } else {
        // New template — load default
        editor.runCommand('mjml-import', { content: defaultMjml });
    }

    // -------------------------------------------------------------------------
    // Save
    // -------------------------------------------------------------------------
    document.getElementById('wea-save-btn').addEventListener('click', function () {
        var name    = document.getElementById('wea-tmpl-name').value.trim();
        var subject = document.getElementById('wea-tmpl-subject').value.trim();

        if ( ! name ) {
            showStatus('Please enter a template name.', 'error');
            return;
        }

        var projectData = editor.getProjectData();
        var html        = editor.runCommand('mjml-code-viewer') || editor.getHtml();

        // Some GrapesJS-MJML versions expose the compiled HTML differently
        if ( typeof html !== 'string' || ! html ) {
            html = editor.getHtml();
        }

        var formData = new FormData();
        formData.append('action',    'wea_save_template');
        formData.append('_ajax_nonce', weaAdmin.nonce);
        formData.append('id',        templateId);
        formData.append('name',      name);
        formData.append('subject',   subject);
        formData.append('mjml_json', JSON.stringify(projectData));
        formData.append('html',      html);

        showStatus('Saving…');

        fetch(weaAdmin.ajaxUrl, { method: 'POST', body: formData })
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if ( res.success ) {
                    templateId = res.data.id;
                    document.getElementById('wea-tmpl-id').value = templateId;
                    // Update URL without reload
                    var newUrl = location.href.replace(/action=(new|edit)/, 'action=edit').replace(/&id=\d*/, '') + '&id=' + templateId;
                    history.replaceState(null, '', newUrl);
                    showStatus(weaAdmin.i18n.saved, 'success');
                } else {
                    showStatus(weaAdmin.i18n.error, 'error');
                }
            })
            .catch(function(){ showStatus(weaAdmin.i18n.error, 'error'); });
    });

    // -------------------------------------------------------------------------
    // Test email
    // -------------------------------------------------------------------------
    document.getElementById('wea-test-btn').addEventListener('click', function () {
        var to = prompt('Send test to (email address):', weaAdminEmail || '');
        if ( ! to ) return;

        var html    = editor.getHtml();
        var subject = document.getElementById('wea-tmpl-subject').value.trim() || 'Test Email';

        var formData = new FormData();
        formData.append('action',    'wea_test_email');
        formData.append('_ajax_nonce', weaAdmin.nonce);
        formData.append('to',        to);
        formData.append('subject',   subject);
        formData.append('html',      html);

        showStatus('Sending…');

        fetch(weaAdmin.ajaxUrl, { method: 'POST', body: formData })
            .then(function(r){ return r.json(); })
            .then(function(res){
                showStatus(res.success ? 'Test email sent to ' + res.data.to + '!' : (res.data || weaAdmin.i18n.error), res.success ? 'success' : 'error');
            })
            .catch(function(){ showStatus(weaAdmin.i18n.error, 'error'); });
    });

    // -------------------------------------------------------------------------
    // Status bar
    // -------------------------------------------------------------------------
    function showStatus(msg, type) {
        var el = document.getElementById('wea-builder-status');
        el.textContent = msg;
        el.className   = 'wea-builder-status wea-builder-status--' + (type || 'info');
        if ( type === 'success' ) {
            setTimeout(function(){ el.textContent = ''; el.className = 'wea-builder-status'; }, 3000);
        }
    }
})();
