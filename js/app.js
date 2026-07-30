/* ============================================================
   FACTIBILIDAD DFM — app.js
   SPA con PHP/AJAX backend
   ============================================================ */
'use strict';

const API = 'php/api.php';
let currentUser   = null;
let currentSolId  = null;
let autoSaveTimer = null;
let camposDef     = [];
let solicitudesCache = [];

/* ── Utilidades ─────────────────────────────────────────────── */
async function api(action, data = {}, method = 'POST') {
  const opts = { method, headers: {} };
  if (method === 'POST') {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify({ action, ...data });
  }
  const url = method === 'GET' ? `${API}?action=${action}&${new URLSearchParams(data)}` : API;
  const res  = await fetch(method === 'GET' ? url : API, opts);
  const json = await res.json();
  return json;
}

function toast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

function showView(id) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('.sidebar nav a').forEach(a => a.classList.remove('active'));
  const v = document.getElementById('view-' + id);
  if (v) v.classList.add('active');
  const link = document.querySelector(`.sidebar nav a[data-view="${id}"]`);
  if (link) link.classList.add('active');
  closeSidebarMobile();
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
}
function fmtPct(n) { return parseFloat(n || 0).toFixed(1) + '%'; }

/* ── Auth ───────────────────────────────────────────────────── */
async function checkLogin() {
  const r = await api('me', {}, 'GET');
  if (r.ok) {
    currentUser = r.data;
    bootApp();
  } else {
    document.getElementById('login-screen').style.display = 'flex';
  }
}

async function doLogin(e) {
  e.preventDefault();
  const email = document.getElementById('login-email').value.trim();
  const pass  = document.getElementById('login-pass').value;
  const errEl = document.getElementById('login-err');
  errEl.textContent = '';

  const r = await api('login', { email, password: pass });
  if (!r.ok) { errEl.textContent = r.error; return; }
  currentUser = r.data;
  document.getElementById('login-screen').style.display = 'none';
  bootApp();
}

async function doLogout() {
  await api('logout', {});
  currentUser = null;
  document.getElementById('app').style.display = 'none';
  document.getElementById('login-screen').style.display = 'flex';
  document.getElementById('login-email').value = '';
  document.getElementById('login-pass').value = '';
}

/* ── Boot ───────────────────────────────────────────────────── */
async function bootApp() {
  document.getElementById('app').style.display = 'block';
  document.getElementById('login-screen').style.display = 'none';

  // Set user info in header
  const ini = (currentUser.nombre || 'U').charAt(0).toUpperCase();
  document.getElementById('hdr-avatar').textContent = ini;
  document.getElementById('hdr-name').textContent   = currentUser.nombre;
  document.getElementById('hdr-rol').textContent    = currentUser.rol;

  // Show/hide admin sections
  document.querySelectorAll('.admin-only').forEach(el => {
    el.style.display = currentUser.rol === 'admin' ? '' : 'none';
  });

  // Load campos definition
  const cd = await api('campos_definicion', {}, 'GET');
  if (cd.ok) camposDef = cd.data;

  showView('dashboard');
  loadDashboard();
}

/* ── Sidebar ────────────────────────────────────────────────── */
function toggleSidebar() {
  const sb = document.querySelector('.sidebar');
  const mc = document.querySelector('.main-content');
  if (window.innerWidth <= 768) {
    sb.classList.toggle('mobile-open');
  } else {
    sb.classList.toggle('collapsed');
    mc.classList.toggle('full');
  }
}
function closeSidebarMobile() {
  document.querySelector('.sidebar').classList.remove('mobile-open');
}

/* ── Dashboard ──────────────────────────────────────────────── */
async function loadDashboard() {
  const r = await api('solicitud_lista', {}, 'GET');
  if (!r.ok) return;
  solicitudesCache = r.data;

  const stats = { total: 0, borrador: 0, enviado: 0, en_revision: 0, aprobado: 0, rechazado: 0 };
  r.data.forEach(s => { stats.total++; stats[s.estado] = (stats[s.estado] || 0) + 1; });

  document.getElementById('stat-total').textContent    = stats.total;
  document.getElementById('stat-borrador').textContent = stats.borrador || 0;
  document.getElementById('stat-enviado').textContent  = (stats.enviado || 0) + (stats.en_revision || 0);
  document.getElementById('stat-aprobado').textContent = stats.aprobado || 0;

  renderSolicitudesTable(r.data, 'dash-table-body', true);
}

function renderSolicitudesTable(data, tbodyId, limit = false) {
  const tbody = document.getElementById(tbodyId);
  if (!tbody) return;
  const rows = limit ? data.slice(0, 8) : data;
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-gray" style="padding:32px">No hay solicitudes</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map(s => `
    <tr>
      <td><strong>${s.folio}</strong></td>
      <td>${s.cliente || '—'}</td>
      <td>${s.lider_proyecto || '—'}</td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div style="flex:1;background:#e5e7eb;border-radius:99px;height:6px;min-width:60px">
            <div style="height:6px;border-radius:99px;background:${pctColor(s.porcentaje_completado)};width:${Math.min(s.porcentaje_completado,100)}%"></div>
          </div>
          <span style="font-size:12px;font-weight:600;color:${pctColor(s.porcentaje_completado)}">${fmtPct(s.porcentaje_completado)}</span>
        </div>
      </td>
      <td><span class="badge badge-${s.estado}">${estadoLabel(s.estado)}</span></td>
      <td>${fmtDate(s.creado_en)}</td>
      <td>
        <button class="btn btn-sm btn-outline" onclick="openSolicitud(${s.id})">Ver / Editar</button>
      </td>
    </tr>
  `).join('');
}

function pctColor(p) {
  p = parseFloat(p);
  if (p >= 75) return '#0e9f6e';
  if (p >= 40) return '#d97706';
  return '#e02424';
}
function estadoLabel(e) {
  const m = { borrador: 'Borrador', enviado: 'Enviado', en_revision: 'En Revisión', aprobado: 'Aprobado', rechazado: 'Rechazado' };
  return m[e] || e;
}

/* ── Lista de solicitudes ────────────────────────────────────── */
async function loadLista() {
  const r = await api('solicitud_lista', {}, 'GET');
  if (!r.ok) return;
  solicitudesCache = r.data;
  renderSolicitudesTable(r.data, 'lista-table-body');
}

function filterLista() {
  const q  = document.getElementById('search-input').value.toLowerCase();
  const st = document.getElementById('filter-estado').value;
  let data = solicitudesCache;
  if (q)  data = data.filter(s => (s.folio + s.cliente + s.lider_proyecto).toLowerCase().includes(q));
  if (st) data = data.filter(s => s.estado === st);
  renderSolicitudesTable(data, 'lista-table-body');
}

/* ── Nueva solicitud ─────────────────────────────────────────── */
async function nuevaSolicitud() {
  const r = await api('solicitud_nueva');
  if (!r.ok) { toast(r.error, 'error'); return; }
  toast('Solicitud creada: ' + r.data.folio, 'success');
  await openSolicitud(r.data.id);
}

/* ── Abrir/editar solicitud ──────────────────────────────────── */
async function openSolicitud(id) {
  currentSolId = id;
  const r = await api('solicitud_get', { id }, 'GET');
  if (!r.ok) { toast(r.error, 'error'); return; }
  const sol = r.data;
  renderFormSolicitud(sol);
  showView('solicitud');
}

function renderFormSolicitud(sol) {
  const isEnviado = sol.estado === 'enviado' && currentUser.rol !== 'admin';
  const container = document.getElementById('form-sections');

  // Header info
  document.getElementById('sol-folio').textContent  = sol.folio;
  document.getElementById('sol-estado').innerHTML   = `<span class="badge badge-${sol.estado}">${estadoLabel(sol.estado)}</span>`;
  document.getElementById('sol-creado').textContent = fmtDate(sol.creado_en);
  document.getElementById('sol-autor').textContent  = sol.creado_por_nombre;

  // Render sections
  container.innerHTML = '';

  camposDef.forEach(seccion => {
    const div = document.createElement('div');
    div.className = 'form-section';
    div.dataset.sectionId = seccion.id; // ← ID para poder referenciar la sección

    // Section weight total
    const totalPeso = seccion.campos.reduce((a, c) => a + (c.peso || 0), 0);
    const pesoLabel = totalPeso > 0 ? `<span class="sec-pct">${(totalPeso * 100).toFixed(0)}% del total</span>` : '';

    div.innerHTML = `
      <div class="form-section-header open" onclick="toggleSection(this)">
        <div class="sec-title">${seccion.icono} ${seccion.titulo}</div>
        <div style="display:flex;align-items:center;gap:8px">${pesoLabel}<span class="chevron">▼</span></div>
      </div>
      <div class="form-section-body">
        <div class="form-grid" id="sec-${seccion.id}"></div>
      </div>`;
    container.appendChild(div);

    const grid = div.querySelector(`#sec-${seccion.id}`);

    seccion.campos.forEach(campo => {
      const val   = sol.campos?.[campo.clave] ?? '';
      const req   = campo.requerido;
      const dis   = isEnviado ? 'disabled' : '';
      const pLabel = campo.peso > 0 ? ` <small style="color:#6b7280;font-weight:400">(${(campo.peso * 100).toFixed(0)}%)</small>` : '';

      let inputHtml = '';

      if (campo.tipo === 'text' || campo.tipo === 'number') {
        inputHtml = `<input type="${campo.tipo}" id="f-${campo.clave}" name="${campo.clave}"
          class="form-control" value="${escHtml(val)}" ${dis}
          onchange="scheduleAutoSave()" oninput="scheduleAutoSave()">`;

      } else if (campo.tipo === 'date') {
        const dateVal = val ? val.split('T')[0] : '';
        inputHtml = `<input type="date" id="f-${campo.clave}" name="${campo.clave}"
          class="form-control" value="${escHtml(dateVal)}" ${dis}
          onchange="scheduleAutoSave()">`;

      } else if (campo.tipo === 'textarea') {
        inputHtml = `<textarea id="f-${campo.clave}" name="${campo.clave}"
          class="form-control" ${dis} onchange="scheduleAutoSave()">${escHtml(val)}</textarea>`;

      } else if (campo.tipo === 'select') {
        const opts = (campo.opciones || []).map(o =>
          `<option value="${escHtml(o)}" ${val === o ? 'selected' : ''}>${o || '-- Selecciona --'}</option>`
        ).join('');
        inputHtml = `<select id="f-${campo.clave}" name="${campo.clave}"
          class="form-control" ${dis} onchange="scheduleAutoSave()">${opts}</select>`;

      } else if (campo.tipo === 'radio') {
        const selVals = val ? val.split(',') : [];
        inputHtml = `<div class="radio-group">` +
          (campo.opciones || []).map(o => `
            <label>
              <input type="radio" name="${campo.clave}" value="${escHtml(o)}"
                ${selVals.includes(o) ? 'checked' : ''} ${dis}
                onchange="scheduleAutoSave()">
              ${o}
            </label>`).join('') + `</div>`;

      } else if (campo.tipo === 'checkbox') {
        const selVals = val ? val.split(',') : [];
        inputHtml = `<div class="check-group">` +
          (campo.opciones || []).map(o => `
            <label>
              <input type="checkbox" name="${campo.clave}" value="${escHtml(o)}"
                ${selVals.includes(o) ? 'checked' : ''} ${dis}
                onchange="scheduleAutoSave()">
              ${o}
            </label>`).join('') + `</div>`;

      } else if (campo.tipo === 'checkbox_single') {
        inputHtml = `<div class="check-group">
          <label>
            <input type="checkbox" id="f-${campo.clave}" name="${campo.clave}" value="1"
              ${val === '1' ? 'checked' : ''} ${dis}
              onchange="scheduleAutoSave()">
            ${campo.label}
          </label></div>`;
      }

      const wrap = document.createElement('div');
      wrap.className = 'form-group';
      if (campo.tipo !== 'checkbox_single') {
        wrap.innerHTML = `
          <label class="form-label" for="f-${campo.clave}">
            ${campo.label}${pLabel}${req ? '<span class="req">*</span>' : ''}
          </label>
          ${inputHtml}`;
      } else {
        wrap.innerHTML = inputHtml;
      }
      grid.appendChild(wrap);
    });
  });

  // Update progress on load
  updateProgress(parseFloat(sol.porcentaje_completado || 0));

  // Buttons
  const isBorrador = sol.estado === 'borrador';
  document.getElementById('btn-guardar').style.display = isBorrador ? '' : 'none';
  document.getElementById('btn-enviar').style.display  = isBorrador ? '' : 'none';
  document.getElementById('admin-actions').style.display = (currentUser.rol === 'admin' && sol.estado === 'enviado') ? '' : 'none';

  // Historial
  renderHistorial(sol.historial || []);

  // ── Visibilidad condicional: sección preformados ──────────────
  setupPreformadosToggle(sol);
}

/* ── Preformados: mostrar sección solo si hay tipo preformado ── */
function setupPreformadosToggle(sol) {
  const seccion = document.querySelector('[data-section-id="preformados"]');
  if (!seccion) return;

  // Determina si ya hay alguna opción seleccionada al cargar
  const valGuardado = sol.campos?.mat_preformado || '';
  const haySeleccion = valGuardado.trim() !== '';

  // Oculta o muestra según el valor inicial
  seccion.style.display = haySeleccion ? '' : 'none';

  // Escucha cambios en los checkboxes de mat_preformado
  document.querySelectorAll('input[name="mat_preformado"]').forEach(cb => {
    cb.addEventListener('change', () => {
      const alguno = document.querySelectorAll('input[name="mat_preformado"]:checked').length > 0;

      if (alguno) {
        seccion.style.display = '';
        // Asegura que la sección quede expandida la primera vez que aparece
        const header = seccion.querySelector('.form-section-header');
        const body   = seccion.querySelector('.form-section-body');
        if (header && !header.classList.contains('open')) {
          header.classList.add('open');
          body.classList.remove('collapsed');
        }
      } else {
        seccion.style.display = 'none';
      }
    });
  });
}

function renderHistorial(hist) {
  const el = document.getElementById('historial-body');
  if (!hist.length) { el.innerHTML = '<p class="text-gray text-sm">Sin historial aún.</p>'; return; }
  el.innerHTML = hist.map(h => `
    <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6">
      <div style="min-width:80px;font-size:12px;color:#6b7280">${fmtDate(h.fecha)}</div>
      <div>
        <span class="badge badge-${h.estado_hasta}">${estadoLabel(h.estado_hasta)}</span>
        <span style="font-size:12px;color:#4b5563;margin-left:8px">por ${h.usuario_nombre}</span>
        ${h.comentario ? `<p style="font-size:12px;color:#6b7280;margin-top:4px">${escHtml(h.comentario)}</p>` : ''}
      </div>
    </div>`).join('');
}

function toggleSection(header) {
  header.classList.toggle('open');
  header.nextElementSibling.classList.toggle('collapsed');
}

/* ── Auto-save ───────────────────────────────────────────────── */
function scheduleAutoSave() {
  clearTimeout(autoSaveTimer);
  autoSaveTimer = setTimeout(() => guardarSolicitud(true), 1200);
}

async function guardarSolicitud(silent = false) {
  if (!currentSolId) return;
  clearTimeout(autoSaveTimer);

  const payload = {
    id: currentSolId,
    campos: collectFormValues(),
  };

  // Cabecera desde campos
  const cab = ['cliente', 'lider_proyecto', 'fecha_entrada', 'fecha_entrega_equipo',
                'fecha_estimada_cierre', 'fecha_entrega_lider', 'fecha_cierre'];
  cab.forEach(c => {
    if (payload.campos[c] !== undefined) {
      payload[c] = payload.campos[c];
      delete payload.campos[c];
    }
  });

  const r = await api('solicitud_guardar', payload);
  if (!r.ok) { if (!silent) toast(r.error, 'error'); return; }

  updateProgress(r.data.porcentaje);
  if (!silent) toast('Guardado correctamente', 'success');
  else showSavedIndicator();
}

function collectFormValues() {
  const vals = {};
  camposDef.forEach(sec => {
    sec.campos.forEach(campo => {
      if (campo.tipo === 'checkbox') {
        const checked = document.querySelectorAll(`input[name="${campo.clave}"]:checked`);
        vals[campo.clave] = Array.from(checked).map(c => c.value).join(',');
      } else if (campo.tipo === 'radio') {
        const ch = document.querySelector(`input[name="${campo.clave}"]:checked`);
        vals[campo.clave] = ch ? ch.value : '';
      } else if (campo.tipo === 'checkbox_single') {
        const el = document.querySelector(`input[name="${campo.clave}"]`);
        vals[campo.clave] = el?.checked ? '1' : '';
      } else {
        const el = document.getElementById(`f-${campo.clave}`);
        if (el) vals[campo.clave] = el.value;
      }
    });
  });
  return vals;
}

function showSavedIndicator() {
  const el = document.getElementById('saved-badge');
  if (!el) return;
  el.style.opacity = '1';
  setTimeout(() => (el.style.opacity = '0'), 2000);
}

function updateProgress(pct) {
  pct = parseFloat(pct) || 0;
  const fill  = document.getElementById('progress-fill');
  const label = document.getElementById('progress-label');
  const tip   = document.getElementById('progress-tip');
  if (!fill) return;

  fill.style.width = Math.min(pct, 100) + '%';
  label.textContent = pct.toFixed(1) + '%';

  const cls = pct >= 75 ? 'ok' : pct >= 40 ? 'warn' : 'low';
  ['ok','warn','low'].forEach(c => { fill.classList.remove(c); label.classList.remove(c); });
  fill.classList.add(cls); label.classList.add(cls);

  if (tip) {
    if (pct >= 75) tip.textContent = '✅ Listo para enviar';
    else           tip.textContent = `⚠️ Necesitas al menos 75% para enviar. Faltan ${(75 - pct).toFixed(1)}%`;
  }

  // Enable/disable send button
  const btn = document.getElementById('btn-enviar');
  if (btn) btn.disabled = pct < 75;
}

/* ── Enviar solicitud ────────────────────────────────────────── */
async function enviarSolicitud() {
  if (!currentSolId) return;
  // Guardar primero
  await guardarSolicitud(true);

  const confirm = window.confirm('¿Estás seguro de enviar esta solicitud? No podrás editarla después del envío.');
  if (!confirm) return;

  const r = await api('solicitud_enviar', { id: currentSolId });
  if (!r.ok) { toast(r.error, 'error'); return; }

  toast('Solicitud enviada exitosamente ✅', 'success');
  const re = await api('solicitud_get', { id: currentSolId }, 'GET');
  if (re.ok) renderFormSolicitud(re.data);
}

/* ── Acciones admin ──────────────────────────────────────────── */
async function cambiarEstado(nuevoEstado) {
  const comentario = prompt('Comentario (opcional):') ?? '';
  const r = await api('solicitud_cambiar_estado', {
    id: currentSolId, estado: nuevoEstado, comentario
  });
  if (!r.ok) { toast(r.error, 'error'); return; }
  toast('Estado actualizado', 'success');
  const re = await api('solicitud_get', { id: currentSolId }, 'GET');
  if (re.ok) renderFormSolicitud(re.data);
}

/* ── Usuarios ────────────────────────────────────────────────── */
async function loadUsuarios() {
  const r = await api('usuarios_lista', {}, 'GET');
  if (!r.ok) { toast(r.error, 'error'); return; }
  const tbody = document.getElementById('usuarios-table-body');
  tbody.innerHTML = r.data.map(u => `
    <tr>
      <td><strong>${escHtml(u.nombre)}</strong></td>
      <td>${escHtml(u.email)}</td>
      <td><span class="badge badge-${u.rol}">${u.rol}</span></td>
      <td>${u.departamento || '—'}</td>
      <td>
        <span style="display:inline-flex;align-items:center;gap:6px">
          <span style="width:8px;height:8px;border-radius:50%;background:${u.activo ? '#0e9f6e' : '#e02424'}"></span>
          ${u.activo ? 'Activo' : 'Inactivo'}
        </span>
      </td>
      <td>${fmtDate(u.ultimo_login)}</td>
      <td>
        <button class="btn btn-sm btn-outline" onclick="toggleUsuario(${u.id})">
          ${u.activo ? 'Desactivar' : 'Activar'}
        </button>
      </td>
    </tr>`).join('');
}

async function toggleUsuario(id) {
  const r = await api('usuario_toggle', { id });
  if (!r.ok) { toast(r.error, 'error'); return; }
  toast('Usuario actualizado', 'success');
  loadUsuarios();
}

function openUsuarioModal() {
  document.getElementById('modal-usuario').classList.add('open');
}
function closeUsuarioModal() {
  document.getElementById('modal-usuario').classList.remove('open');
  document.getElementById('form-usuario').reset();
}

async function crearUsuario(e) {
  e.preventDefault();
  const f = e.target;
  const r = await api('usuario_crear', {
    nombre:       f.nombre.value,
    email:        f.email.value,
    password:     f.password.value,
    rol:          f.rol.value,
    departamento: f.departamento.value,
  });
  if (!r.ok) { toast(r.error, 'error'); return; }
  toast('Usuario creado exitosamente', 'success');
  closeUsuarioModal();
  loadUsuarios();
}

/* ── Helpers ─────────────────────────────────────────────────── */
function escHtml(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Init ────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  // Login
  document.getElementById('login-form').addEventListener('submit', doLogin);

  // Nav links
  document.querySelectorAll('.sidebar nav a[data-view]').forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      const v = a.dataset.view;
      showView(v);
      if (v === 'dashboard') loadDashboard();
      if (v === 'lista')     loadLista();
      if (v === 'usuarios')  loadUsuarios();
    });
  });

  // Burger
  document.getElementById('burger-btn').addEventListener('click', toggleSidebar);

  // User dropdown logout
  document.getElementById('user-badge').addEventListener('click', () => {
    if (confirm('¿Cerrar sesión?')) doLogout();
  });

  // Guardar / Enviar buttons
  document.getElementById('btn-guardar').addEventListener('click', () => guardarSolicitud(false));
  document.getElementById('btn-enviar').addEventListener('click',  enviarSolicitud);

  // Admin buttons
  document.getElementById('btn-aprobar')?.addEventListener('click',   () => cambiarEstado('aprobado'));
  document.getElementById('btn-rechazar')?.addEventListener('click',  () => cambiarEstado('rechazado'));
  document.getElementById('btn-revision')?.addEventListener('click',  () => cambiarEstado('en_revision'));

  // Nueva solicitud
  document.getElementById('btn-nueva-sol')?.addEventListener('click', nuevaSolicitud);
  document.getElementById('btn-nueva-sol2')?.addEventListener('click', () => { showView('lista'); nuevaSolicitud(); });

  // Filtros lista
  document.getElementById('search-input')?.addEventListener('input', filterLista);
  document.getElementById('filter-estado')?.addEventListener('change', filterLista);

  // Usuarios modal
  document.getElementById('btn-nuevo-usuario')?.addEventListener('click', openUsuarioModal);
  document.getElementById('btn-close-modal')?.addEventListener('click',  closeUsuarioModal);
  document.getElementById('form-usuario')?.addEventListener('submit', crearUsuario);

  // Boot
  checkLogin();
});