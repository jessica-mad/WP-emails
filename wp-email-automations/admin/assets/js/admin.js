/* WP Email Automations — Admin JS */
(function ($) {
    'use strict';

    // -------------------------------------------------------------------------
    // Automation builder
    // -------------------------------------------------------------------------
    var conditions = [];
    var actions    = [];

    var $condList   = $('#wea-conditions-list');
    var $actionList = $('#wea-actions-list');

    if ( $condList.length ) {
        // Load existing data from data attribute
        var rawData = $('#wea-automation-editor').data('automation');
        if ( rawData && rawData !== 'null' ) {
            conditions = rawData.conditions || [];
            actions    = rawData.actions    || [];
        }
        renderConditions();
        renderActions();
        renderTriggerVars( $('#auto-trigger').val() );
    }

    // ------ Trigger variable panel ------
    $('#auto-trigger').on('change', function () {
        renderTriggerVars( $(this).val() );
    });

    function renderTriggerVars( triggerKey ) {
        var fields   = (window.weaTriggerFields || {})[ triggerKey ];
        var $panel   = $('#wea-trigger-vars');
        var $list    = $('#wea-vars-list');
        $list.empty();

        if ( ! fields || ! Object.keys(fields).length ) {
            $panel.hide();
            return;
        }

        Object.entries(fields).forEach(function([key, desc]){
            var chip = $('<span class="wea-var-chip" title="' + esc(desc) + '">{{' + esc(key) + '}}</span>');
            chip.on('click', function(){
                copyToClipboard( '{{' + key + '}}' );
                var orig = chip.text();
                chip.text('¡Copiado!');
                setTimeout(function(){ chip.text(orig); }, 1200);
            });
            $list.append(chip).append(' ');
        });

        $panel.show();
    }

    function copyToClipboard( text ) {
        if ( navigator.clipboard ) {
            navigator.clipboard.writeText( text );
        } else {
            var el = document.createElement('textarea');
            el.value = text;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
    }

    // ------ Conditions ------

    $('#wea-add-condition').on('click', function () {
        conditions.push({ field: '', operator: 'equals', value: '' });
        renderConditions();
    });

    function renderConditions() {
        $condList.empty();
        if ( ! conditions.length ) {
            $condList.append('<p class="description">' + weaAdmin.i18n.noConditions || 'No conditions — automation always runs.' + '</p>');
            return;
        }
        conditions.forEach(function (cond, idx) {
            var opOptions = Object.entries(weaAdmin.operators).map(function([k,v]){
                return '<option value="' + esc(k) + '"' + (cond.operator === k ? ' selected' : '') + '>' + esc(v) + '</option>';
            }).join('');

            var row = $([
                '<div class="wea-condition-row" data-idx="' + idx + '">',
                '  <input type="text" class="wea-cond-field" placeholder="field (e.g. email, plan, user.country)" value="' + esc(cond.field) + '">',
                '  <select class="wea-cond-operator">' + opOptions + '</select>',
                '  <input type="text" class="wea-cond-value" placeholder="value" value="' + esc(cond.value) + '">',
                '  <button type="button" class="button wea-remove-condition">✕</button>',
                '</div>',
            ].join(''));
            $condList.append(row);
        });

        $condList.find('.wea-cond-field').on('input', function(){
            var idx = $(this).closest('[data-idx]').data('idx');
            conditions[idx].field = $(this).val();
            updateHiddenFields();
        });
        $condList.find('.wea-cond-operator').on('change', function(){
            var idx = $(this).closest('[data-idx]').data('idx');
            conditions[idx].operator = $(this).val();
            updateHiddenFields();
        });
        $condList.find('.wea-cond-value').on('input', function(){
            var idx = $(this).closest('[data-idx]').data('idx');
            conditions[idx].value = $(this).val();
            updateHiddenFields();
        });
        $condList.find('.wea-remove-condition').on('click', function(){
            var idx = $(this).closest('[data-idx]').data('idx');
            conditions.splice(idx, 1);
            renderConditions();
        });

        updateHiddenFields();
    }

    // ------ Actions ------

    $('#wea-add-action-email').on('click', function () {
        actions.push({ type: 'send_email', template_id: 0, to: '{{email}}', subject: '', delay_minutes: 0 });
        renderActions();
    });

    $('#wea-add-action-webhook').on('click', function () {
        actions.push({ type: 'webhook', url: '', method: 'POST', delay_minutes: 0 });
        renderActions();
    });

    function renderActions() {
        $actionList.empty();
        if ( ! actions.length ) {
            $actionList.append('<p class="description">No actions defined.</p>');
            return;
        }
        actions.forEach(function (action, idx) {
            var inner = '';
            if ( action.type === 'send_email' ) {
                var tmplOptions = (weaTemplates || []).map(function(t){
                    return '<option value="' + t.id + '"' + (action.template_id == t.id ? ' selected' : '') + '>' + esc(t.name) + '</option>';
                }).join('');
                inner = [
                    '<strong>Send Email</strong>',
                    '<label>Template <select class="wea-act-template"><option value="0">— select —</option>' + tmplOptions + '</select></label>',
                    '<label>To <input type="text" class="wea-act-to" value="' + esc(action.to) + '" placeholder="{{email}}"></label>',
                    '<label>Subject override <input type="text" class="wea-act-subject" value="' + esc(action.subject) + '" placeholder="Leave empty to use template subject"></label>',
                    '<label>Delay <input type="number" class="wea-act-delay" value="' + (action.delay_minutes || 0) + '" min="0" style="width:70px"> minutes</label>',
                ].join(' ');
            } else if ( action.type === 'webhook' ) {
                inner = [
                    '<strong>Webhook</strong>',
                    '<label>URL <input type="url" class="wea-act-url" value="' + esc(action.url) + '" placeholder="https://..." style="width:300px"></label>',
                    '<label>Method <select class="wea-act-method"><option' + (action.method==='POST'?' selected':'') + '>POST</option><option' + (action.method==='GET'?' selected':'') + '>GET</option></select></label>',
                    '<label>Delay <input type="number" class="wea-act-delay" value="' + (action.delay_minutes || 0) + '" min="0" style="width:70px"> minutes</label>',
                ].join(' ');
            }

            var row = $('<div class="wea-action-row" data-idx="' + idx + '"><div class="wea-action-inner">' + inner + '</div><button type="button" class="button wea-remove-action">✕</button></div>');
            $actionList.append(row);
        });

        // Bind changes
        $actionList.find('.wea-act-template').on('change', function(){
            var idx = $(this).closest('[data-idx]').data('idx');
            actions[idx].template_id = parseInt($(this).val(), 10);
            updateHiddenFields();
        });
        $actionList.find('.wea-act-to').on('input', function(){
            var idx = $(this).closest('[data-idx]').data('idx');
            actions[idx].to = $(this).val();
            updateHiddenFields();
        });
        $actionList.find('.wea-act-subject').on('input', function(){
            var idx = $(this).closest('[data-idx]').data('idx');
            actions[idx].subject = $(this).val();
            updateHiddenFields();
        });
        $actionList.find('.wea-act-url').on('input', function(){
            var idx = $(this).closest('[data-idx]').data('idx');
            actions[idx].url = $(this).val();
            updateHiddenFields();
        });
        $actionList.find('.wea-act-method').on('change', function(){
            var idx = $(this).closest('[data-idx]').data('idx');
            actions[idx].method = $(this).val();
            updateHiddenFields();
        });
        $actionList.find('.wea-act-delay').on('input', function(){
            var idx = $(this).closest('[data-idx]').data('idx');
            actions[idx].delay_minutes = parseInt($(this).val(), 10) || 0;
            updateHiddenFields();
        });
        $actionList.find('.wea-remove-action').on('click', function(){
            var idx = $(this).closest('[data-idx]').data('idx');
            actions.splice(idx, 1);
            renderActions();
        });

        updateHiddenFields();
    }

    function updateHiddenFields() {
        $('#conditions_json').val(JSON.stringify(conditions));
        $('#actions_json').val(JSON.stringify(actions));
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------
    function esc(str) {
        if ( str === null || str === undefined ) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

})(jQuery);
