/* WEA Block Email Builder — sin dependencias externas */
(function () {
'use strict';

if ( ! document.getElementById('wea-canvas-inner') ) return;

var templateId = parseInt( document.getElementById('wea-tmpl-id').value, 10 ) || 0;
var savedData  = window.weaTemplateData || {};

// ── STATE ─────────────────────────────────────────────────────────────────────
var state = {
    blocks: [],
    globalStyle: {
        backgroundColor:   '#f4f4f4',
        contentBackground: '#ffffff',
        fontFamily:        'Arial, sans-serif',
    },
    selectedId:  null,
    showGlobal:  false,
};

// ── BLOCK DEFINITIONS ─────────────────────────────────────────────────────────
var BLOCK_DEFS = [
    { type: 'heading', label: 'Título',    icon: 'H',
      defaults: { content: 'Título del email',
        style: { fontSize: '24px', fontWeight: 'bold', color: '#111111', align: 'left', padding: '20px 25px 10px' } } },
    { type: 'text',    label: 'Texto',     icon: 'T',
      defaults: { content: '<p>Escribe aquí el contenido.</p>',
        style: { fontSize: '14px', color: '#333333', align: 'left', padding: '10px 25px' } } },
    { type: 'image',   label: 'Imagen',    icon: '🖼',
      defaults: { src: '', alt: '', href: '', width: '100%',
        style: { align: 'center', padding: '10px 25px' } } },
    { type: 'button',  label: 'Botón',     icon: '▶',
      defaults: { text: 'Haz click aquí', href: '',
        style: { backgroundColor: '#6366f1', color: '#ffffff', borderRadius: '6px', align: 'center', padding: '15px 25px', fontSize: '14px' } } },
    { type: 'divider', label: 'Separador', icon: '─',
      defaults: { style: { color: '#dddddd', borderWidth: '1px', padding: '10px 25px' } } },
    { type: 'spacer',  label: 'Espacio',   icon: '↕',
      defaults: { style: { height: '20px' } } },
];

// ── UTILS ─────────────────────────────────────────────────────────────────────
function uid() { return 'b' + Math.random().toString(36).slice(2, 9); }
function clone(o) { return JSON.parse(JSON.stringify(o)); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function newBlock(type) {
    var def = BLOCK_DEFS.find(function(d) { return d.type === type; });
    if (!def) return null;
    return Object.assign({ id: uid(), type: type }, clone(def.defaults));
}

// ── LOAD ──────────────────────────────────────────────────────────────────────
function loadState() {
    if (savedData.mjml_content && savedData.mjml_content.length > 10) {
        try {
            var p = JSON.parse(savedData.mjml_content);
            if (p && p.version === 1) {
                state.blocks      = p.blocks || [];
                state.globalStyle = Object.assign({}, state.globalStyle, p.globalStyle || {});
                return;
            }
        } catch(e) {}
    }
    // Default starter template
    state.blocks = [
        Object.assign(newBlock('heading'), { content: 'Hola <strong>{{name}}</strong> 👋' }),
        Object.assign(newBlock('text'),    { content: '<p>Escribe aquí el contenido de tu email.</p>' }),
        Object.assign(newBlock('button'),  { text: 'Acción principal', href: '{{cta_url}}' }),
        Object.assign(newBlock('text'),    { content: '<p>© {{year}} Tu empresa.</p>',
            style: { fontSize: '12px', color: '#999999', align: 'center', padding: '15px 25px 20px' } }),
    ];
}

function toJson() {
    return JSON.stringify({ version: 1, globalStyle: state.globalStyle, blocks: state.blocks });
}

// ── RENDER ────────────────────────────────────────────────────────────────────
function render() {
    renderCanvas();
    renderProps();
    applyGlobalStyle();
}

function applyGlobalStyle() {
    var email = document.querySelector('.wea-canvas__email');
    if (email) email.style.backgroundColor = state.globalStyle.backgroundColor;
    var inner = document.querySelector('.wea-canvas__inner');
    if (inner) {
        inner.style.backgroundColor = state.globalStyle.contentBackground;
        inner.style.fontFamily      = state.globalStyle.fontFamily;
    }
}

function renderCanvas() {
    var container = document.getElementById('wea-canvas-inner');
    if (!container) return;
    container.innerHTML = '';

    if (!state.blocks.length) {
        container.innerHTML = '<div class="wea-canvas__empty"><strong>Arrastra bloques aquí</strong><p>← Usa la barra de la izquierda para añadir contenido</p></div>';
        return;
    }

    state.blocks.forEach(function(block, idx) {
        container.appendChild(makeDropIndicator(idx));
        container.appendChild(makeBlockEl(block, idx));
    });
    container.appendChild(makeDropIndicator(state.blocks.length));
}

function makeDropIndicator(idx) {
    var el = document.createElement('div');
    el.className   = 'wea-drop-indicator';
    el.dataset.idx = idx;
    return el;
}

function makeBlockEl(block) {
    var wrap = document.createElement('div');
    wrap.className   = 'wea-canvas__block' + (state.selectedId === block.id ? ' selected' : '');
    wrap.dataset.id  = block.id;
    wrap.draggable   = true;

    // Toolbar
    var tb = document.createElement('div');
    tb.className = 'wea-block__toolbar';
    tb.innerHTML = '<button class="handle" title="Mover">⠿</button>' +
        '<button class="btn-up" title="Subir">↑</button>' +
        '<button class="btn-dn" title="Bajar">↓</button>' +
        '<button class="btn-dup" title="Duplicar">⧉</button>' +
        '<button class="btn-del" title="Eliminar" style="color:#fca5a5;">✕</button>';

    var content = document.createElement('div');
    content.className   = 'wea-canvas__block-content';
    content.innerHTML   = blockPreview(block);
    content.style.pointerEvents = 'none';

    wrap.appendChild(tb);
    wrap.appendChild(content);

    wrap.addEventListener('click', function(e) {
        if (e.target.closest('.wea-block__toolbar')) return;
        selectBlock(block.id);
    });
    tb.querySelector('.btn-del').addEventListener('click', function(e) { e.stopPropagation(); deleteBlock(block.id); });
    tb.querySelector('.btn-up').addEventListener('click',  function(e) { e.stopPropagation(); moveBlock(block.id, -1); });
    tb.querySelector('.btn-dn').addEventListener('click',  function(e) { e.stopPropagation(); moveBlock(block.id,  1); });
    tb.querySelector('.btn-dup').addEventListener('click', function(e) { e.stopPropagation(); duplicateBlock(block.id); });

    // Reorder drag
    wrap.addEventListener('dragstart', function(e) {
        e.dataTransfer.setData('reorder', block.id);
        e.dataTransfer.effectAllowed = 'move';
        setTimeout(function() { wrap.style.opacity = '.35'; }, 0);
    });
    wrap.addEventListener('dragend', function() {
        wrap.style.opacity = '';
        hideIndicators();
    });

    return wrap;
}

// ── BLOCK PREVIEW HTML ────────────────────────────────────────────────────────
function blockPreview(block) {
    var s   = block.style || {};
    var pad = s.padding || '10px 25px';
    var aln = s.align   || 'left';
    var fnt = state.globalStyle.fontFamily || 'Arial,sans-serif';

    switch (block.type) {
        case 'heading':
            return '<div style="padding:' + pad + ';font-family:' + fnt + ';font-size:' + (s.fontSize||'24px') + ';font-weight:' + (s.fontWeight||'bold') + ';color:' + (s.color||'#111') + ';text-align:' + aln + ';">' + (block.content || 'Título') + '</div>';
        case 'text':
            return '<div style="padding:' + pad + ';font-family:' + fnt + ';font-size:' + (s.fontSize||'14px') + ';color:' + (s.color||'#333') + ';text-align:' + aln + ';line-height:1.6;">' + (block.content || '') + '</div>';
        case 'image':
            if (!block.src) return '<div style="padding:' + pad + ';text-align:' + aln + ';"><div style="background:#f0f4ff;border:2px dashed #6366f1;border-radius:4px;padding:28px;text-align:center;color:#6366f1;font-size:.82rem;">🖼 Click para seleccionar imagen</div></div>';
            return '<div style="padding:' + pad + ';text-align:' + aln + ';"><img src="' + esc(block.src) + '" alt="' + esc(block.alt||'') + '" style="max-width:100%;display:inline-block;"></div>';
        case 'button':
            return '<div style="padding:' + pad + ';text-align:' + aln + ';"><span style="display:inline-block;background-color:' + (s.backgroundColor||'#6366f1') + ';color:' + (s.color||'#fff') + ';border-radius:' + (s.borderRadius||'6px') + ';padding:12px 24px;font-family:' + fnt + ';font-size:' + (s.fontSize||'14px') + ';font-weight:bold;">' + esc(block.text||'Botón') + '</span></div>';
        case 'divider':
            return '<div style="padding:' + pad + ';"><hr style="border:0;border-top:' + (s.borderWidth||'1px') + ' solid ' + (s.color||'#ddd') + ';margin:0;"></div>';
        case 'spacer':
            return '<div style="height:' + (s.height||'20px') + ';background:rgba(99,102,241,.06);display:flex;align-items:center;justify-content:center;"><span style="font-size:.65rem;color:#6366f1;">↕ ' + esc(s.height||'20px') + '</span></div>';
        default:
            return '<div style="padding:10px;color:#aaa;font-size:.8rem;">' + esc(block.type) + '</div>';
    }
}

// ── DRAG & DROP: LIBRARY → CANVAS ─────────────────────────────────────────────
function setupLibraryDrag() {
    document.querySelectorAll('.wea-library__item').forEach(function(item) {
        item.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('newblock', item.dataset.type);
            e.dataTransfer.effectAllowed = 'copy';
        });
    });
}

function setupCanvasDrop(container) {
    container.addEventListener('dragover', function(e) {
        e.preventDefault();
        var ind = closestIndicator(container, e.clientY);
        hideIndicators();
        if (ind) ind.classList.add('visible');
    });
    container.addEventListener('dragleave', function(e) {
        if (!container.contains(e.relatedTarget)) hideIndicators();
    });
    container.addEventListener('drop', function(e) {
        e.preventDefault();
        hideIndicators();
        var ind    = closestIndicator(container, e.clientY);
        var insIdx = ind ? parseInt(ind.dataset.idx) : state.blocks.length;
        var newType   = e.dataTransfer.getData('newblock');
        var reorderId = e.dataTransfer.getData('reorder');

        if (newType) {
            var b = newBlock(newType);
            if (b) { state.blocks.splice(insIdx, 0, b); state.selectedId = b.id; render(); }
        } else if (reorderId) {
            var from = state.blocks.findIndex(function(b) { return b.id === reorderId; });
            if (from === -1) return;
            var moved = state.blocks.splice(from, 1)[0];
            var to    = insIdx > from ? insIdx - 1 : insIdx;
            state.blocks.splice(to, 0, moved);
            render();
        }
    });
}

function hideIndicators() {
    document.querySelectorAll('.wea-drop-indicator').forEach(function(el) { el.classList.remove('visible'); });
}

function closestIndicator(container, y) {
    var best = null, minDist = Infinity;
    container.querySelectorAll('.wea-drop-indicator').forEach(function(el) {
        var dist = Math.abs(el.getBoundingClientRect().top - y);
        if (dist < minDist) { minDist = dist; best = el; }
    });
    return best;
}

// ── BLOCK ACTIONS ─────────────────────────────────────────────────────────────
function selectBlock(id) { state.selectedId = id; state.showGlobal = false; render(); }

function deleteBlock(id) {
    state.blocks = state.blocks.filter(function(b) { return b.id !== id; });
    if (state.selectedId === id) state.selectedId = null;
    render();
}

function moveBlock(id, dir) {
    var i = state.blocks.findIndex(function(b) { return b.id === id; });
    var j = i + dir;
    if (j < 0 || j >= state.blocks.length) return;
    var tmp = state.blocks[i]; state.blocks[i] = state.blocks[j]; state.blocks[j] = tmp;
    render();
}

function duplicateBlock(id) {
    var i = state.blocks.findIndex(function(b) { return b.id === id; });
    if (i === -1) return;
    var c = clone(state.blocks[i]); c.id = uid();
    state.blocks.splice(i + 1, 0, c);
    state.selectedId = c.id;
    render();
}

function updateBlock(id, props) {
    var block = state.blocks.find(function(b) { return b.id === id; });
    if (!block) return;
    if (props.style) { block.style = Object.assign(block.style || {}, props.style); delete props.style; }
    Object.assign(block, props);
    // Refresh just the preview
    var el = document.querySelector('.wea-canvas__block[data-id="' + id + '"] .wea-canvas__block-content');
    if (el) el.innerHTML = blockPreview(block);
}

// ── PROPERTIES PANEL ──────────────────────────────────────────────────────────
function renderProps() {
    var panel  = document.getElementById('wea-props-body');
    var header = document.getElementById('wea-props-header-title');
    if (!panel) return;

    if (state.showGlobal) {
        if (header) header.textContent = 'Estilos globales';
        renderGlobalProps(panel);
        return;
    }

    var block = state.blocks.find(function(b) { return b.id === state.selectedId; });
    if (!block) {
        if (header) header.textContent = 'Propiedades';
        panel.innerHTML = '<div class="wea-props__empty">Haz click en un bloque para editar sus propiedades</div>';
        return;
    }

    var def = BLOCK_DEFS.find(function(d) { return d.type === block.type; });
    if (header) header.textContent = def ? def.label : block.type;

    var html = '';
    var s    = block.style || {};

    if (block.type === 'heading' || block.type === 'text') {
        html += fld('Contenido (HTML)', 'textarea', 'content', block.content || '');
        html += '<div class="wea-props__sep"></div>';
        html += '<div class="wea-props__section-title">Estilo</div>';
        html += fld('Tamaño fuente', 'text', 'style.fontSize', s.fontSize || '14px', '16px');
        html += clrFld('Color texto', 'style.color', s.color || '#333333');
        if (block.type === 'heading') html += selFld('Peso', 'style.fontWeight', s.fontWeight||'bold', [['normal','Normal'],['bold','Negrita']]);
        html += alnFld(s.align || 'left');
        html += fld('Padding', 'text', 'style.padding', s.padding || '10px 25px', '10px 25px');
    } else if (block.type === 'image') {
        html += '<button class="wea-img-pick-btn" id="wea-pick-image">📁 Seleccionar de la biblioteca</button>';
        html += '<img src="' + esc(block.src||'') + '" class="wea-img-preview' + (block.src ? ' visible' : '') + '" id="wea-img-prev">';
        html += fld('URL imagen', 'url', 'src', block.src||'', 'https://...');
        html += fld('Alt text', 'text', 'alt', block.alt||'');
        html += fld('Enlace (href)', 'url', 'href', block.href||'', 'https://...');
        html += fld('Ancho', 'text', 'width', block.width||'100%', '100%');
        html += '<div class="wea-props__sep"></div>';
        html += alnFld(s.align || 'center');
        html += fld('Padding', 'text', 'style.padding', s.padding||'10px 25px');
    } else if (block.type === 'button') {
        html += fld('Texto', 'text', 'text', block.text||'');
        html += fld('URL (href)', 'url', 'href', block.href||'', '{{cta_url}}');
        html += '<div class="wea-props__sep"></div>';
        html += '<div class="wea-props__section-title">Estilo</div>';
        html += clrFld('Fondo', 'style.backgroundColor', s.backgroundColor||'#6366f1');
        html += clrFld('Color texto', 'style.color', s.color||'#ffffff');
        html += fld('Radio borde', 'text', 'style.borderRadius', s.borderRadius||'6px', '6px');
        html += fld('Tamaño fuente', 'text', 'style.fontSize', s.fontSize||'14px', '14px');
        html += alnFld(s.align || 'center');
        html += fld('Padding celda', 'text', 'style.padding', s.padding||'15px 25px');
    } else if (block.type === 'divider') {
        html += clrFld('Color', 'style.color', s.color||'#dddddd');
        html += fld('Grosor', 'text', 'style.borderWidth', s.borderWidth||'1px', '1px');
        html += fld('Padding', 'text', 'style.padding', s.padding||'10px 25px');
    } else if (block.type === 'spacer') {
        html += fld('Altura', 'text', 'style.height', s.height||'20px', '20px');
    }

    panel.innerHTML = html;
    bindPropInputs(panel, block.id);

    var pickBtn = panel.querySelector('#wea-pick-image');
    if (pickBtn) {
        pickBtn.addEventListener('click', function() {
            openMedia(function(url) {
                var srcIn = panel.querySelector('[data-field="src"]');
                if (srcIn) srcIn.value = url;
                var prev = document.getElementById('wea-img-prev');
                if (prev) { prev.src = url; prev.classList.add('visible'); }
                updateBlock(block.id, { src: url });
            });
        });
    }
}

function bindPropInputs(panel, blockId) {
    panel.querySelectorAll('[data-field]').forEach(function(inp) {
        var key = inp.dataset.field;
        inp.addEventListener('input', function() {
            if (key.startsWith('style.')) {
                var sk = key.slice(6), upd = {}; upd[sk] = inp.value;
                updateBlock(blockId, { style: upd });
            } else {
                var upd = {}; upd[key] = inp.value;
                updateBlock(blockId, upd);
            }
        });
    });
    panel.querySelectorAll('.wea-align-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            panel.querySelectorAll('.wea-align-btn').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            updateBlock(blockId, { style: { align: btn.dataset.align } });
        });
    });
}

function fld(label, type, key, val, ph) {
    var phAttr = ph ? ' placeholder="' + esc(ph) + '"' : '';
    if (type === 'textarea') return '<div class="wea-field"><label>' + label + '</label><textarea data-field="' + key + '"' + phAttr + '>' + esc(val) + '</textarea></div>';
    return '<div class="wea-field"><label>' + label + '</label><input type="' + type + '" data-field="' + key + '" value="' + esc(val) + '"' + phAttr + '></div>';
}
function clrFld(label, key, val) {
    var hex = val && val[0] === '#' ? val : '#000000';
    return '<div class="wea-field"><label>' + label + '</label><input type="color" data-field="' + key + '" value="' + esc(hex) + '"></div>';
}
function selFld(label, key, val, opts) {
    var o = opts.map(function(op) { return '<option value="' + op[0] + '"' + (op[0]===val?' selected':'') + '>' + op[1] + '</option>'; }).join('');
    return '<div class="wea-field"><label>' + label + '</label><select data-field="' + key + '">' + o + '</select></div>';
}
function alnFld(cur) {
    var btns = [['left','←'],['center','↔'],['right','→']].map(function(a) {
        return '<button class="wea-align-btn' + (a[0]===cur?' active':'') + '" data-align="' + a[0] + '">' + a[1] + '</button>';
    }).join('');
    return '<div class="wea-field"><label>Alineación</label><div class="wea-align-btns">' + btns + '</div></div>';
}

// ── GLOBAL STYLE PANEL ────────────────────────────────────────────────────────
function renderGlobalProps(panel) {
    panel.innerHTML =
        '<div class="wea-props__section-title">Email</div>' +
        clrFld('Fondo exterior',  'g.backgroundColor',   state.globalStyle.backgroundColor) +
        clrFld('Fondo contenido', 'g.contentBackground', state.globalStyle.contentBackground) +
        selFld('Fuente', 'g.fontFamily', state.globalStyle.fontFamily, [
            ['Arial, sans-serif',           'Arial'],
            ['Georgia, serif',              'Georgia'],
            ['Verdana, sans-serif',         'Verdana'],
            ['Trebuchet MS, sans-serif',    'Trebuchet MS'],
            ['Times New Roman, serif',      'Times New Roman'],
        ]);

    panel.querySelectorAll('[data-field]').forEach(function(inp) {
        var key = inp.dataset.field;
        inp.addEventListener('input', function() {
            if (key.startsWith('g.')) {
                state.globalStyle[key.slice(2)] = inp.value;
                applyGlobalStyle();
            }
        });
    });
}

// ── WP MEDIA ─────────────────────────────────────────────────────────────────
function openMedia(cb) {
    if (typeof wp === 'undefined' || !wp.media) {
        var url = prompt('URL de la imagen:', '');
        if (url) cb(url);
        return;
    }
    if (!openMedia._frame) {
        openMedia._frame = wp.media({ title: 'Seleccionar imagen', button: { text: 'Usar esta imagen' }, multiple: false, library: { type: 'image' } });
    }
    openMedia._frame.off('select');
    openMedia._frame.on('select', function() {
        var att = openMedia._frame.state().get('selection').first().toJSON();
        cb(att.sizes && att.sizes.large ? att.sizes.large.url : att.url);
    });
    openMedia._frame.open();
}

// ── SAVE ──────────────────────────────────────────────────────────────────────
document.getElementById('wea-save-btn').addEventListener('click', function() {
    var name    = document.getElementById('wea-tmpl-name').value.trim();
    var subject = document.getElementById('wea-tmpl-subject').value.trim();
    if (!name) { showStatus('Pon un nombre a la plantilla antes de guardar.', 'error'); document.getElementById('wea-tmpl-name').focus(); return; }
    var fd = new FormData();
    fd.append('action',       'wea_save_template');
    fd.append('_ajax_nonce',  weaAdmin.nonce);
    fd.append('id',           templateId);
    fd.append('name',         name);
    fd.append('subject',      subject);
    fd.append('mjml_content', toJson());
    fd.append('html',         '');
    showStatus('Guardando…', 'info');
    fetch(weaAdmin.ajaxUrl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                templateId = res.data.id;
                document.getElementById('wea-tmpl-id').value = templateId;
                var u = new URL(location.href);
                u.searchParams.set('action', 'edit');
                u.searchParams.set('id', templateId);
                history.replaceState(null, '', u.toString());
                showStatus('✓ Guardado', 'success');
            } else {
                showStatus('Error al guardar.', 'error');
            }
        })
        .catch(function() { showStatus('Error de red.', 'error'); });
});

// ── TEST EMAIL ────────────────────────────────────────────────────────────────
document.getElementById('wea-test-btn').addEventListener('click', function() {
    var to = prompt('Enviar email de prueba a:', '');
    if (!to || !to.includes('@')) return;
    var subject = document.getElementById('wea-tmpl-subject').value.trim() || 'Email de prueba';
    var fd = new FormData();
    fd.append('action',      'wea_test_email');
    fd.append('_ajax_nonce', weaAdmin.nonce);
    fd.append('to',          to);
    fd.append('subject',     subject);
    fd.append('html',        toJson());
    showStatus('Enviando…', 'info');
    fetch(weaAdmin.ajaxUrl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) { showStatus(res.success ? '✓ Email enviado a ' + res.data.to : 'Error: ' + (res.data||'wp_mail falló'), res.success ? 'success' : 'error'); })
        .catch(function() { showStatus('Error de red.', 'error'); });
});

// ── STATUS ────────────────────────────────────────────────────────────────────
function showStatus(msg, type) {
    var el = document.getElementById('wea-builder-status');
    if (!el) return;
    el.textContent = msg;
    el.className   = 'wea-builder-status wea-builder-status--' + (type||'info');
    if (type === 'success') setTimeout(function() { el.textContent = ''; el.className = 'wea-builder-status'; }, 3000);
}

// ── GLOBAL SETTINGS TOGGLE ────────────────────────────────────────────────────
var gBtn = document.getElementById('wea-global-settings-btn');
if (gBtn) {
    gBtn.addEventListener('click', function() {
        state.showGlobal = !state.showGlobal;
        if (state.showGlobal) state.selectedId = null;
        renderProps();
    });
}

// ── INIT ──────────────────────────────────────────────────────────────────────
loadState();
render();
setupLibraryDrag();
// setupCanvasDrop se registra UNA sola vez aquí; si estuviera dentro de
// renderCanvas() se acumularían listeners en cada re-render → N drops por evento.
setupCanvasDrop( document.getElementById('wea-canvas-inner') );

})();
