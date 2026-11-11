'use strict';
/*
 * app.js — interfaz de LifeFlow.
 * Habla con api.php (JSON). Todo texto que viene de la base se inserta con textContent
 * (nunca innerHTML), así un título como "<script>" se muestra como texto y no se ejecuta.
 * Los permisos reales los decide el servidor; acá solo se muestran u ocultan opciones.
 */

const S = {user: null, csrf: '', data: null, fecha: null, mostrarSemana: null};
const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
const DIAS_L = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
const EST_H = {pendiente: 'Pendiente', completada: 'Completada', cancelada: 'Cancelada',
  postergada_con_justificacion: 'Postergada con justificación', postergada_sin_justificacion: 'Postergada sin justificación'};
const EST_T = {pendiente: 'Pendiente', completada: 'Completada', cancelada: 'Cancelada', postergada: 'Postergada'};
const PRIO = {baja: 'Baja', media: 'Media', alta: 'Alta'};

/* ---------- utilidades ---------- */
const $ = id => document.getElementById(id);
function el(tag, attrs, ...hijos) {
  const e = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs || {})) {
    if (v === undefined || v === null || v === false) continue;
    if (k === 'class') e.className = v;
    else if (k === 'text') e.textContent = v;
    else if (k.startsWith('on')) e.addEventListener(k.slice(2), v);
    else if (k === 'style') e.style.cssText = v;
    else e.setAttribute(k, v === true ? '' : v);
  }
  for (const h of hijos.flat()) if (h !== null && h !== undefined && h !== false) e.append(h.nodeType ? h : document.createTextNode(String(h)));
  return e;
}
const pad = n => String(n).padStart(2, '0');
const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const aFecha = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
const sumar = (s, n) => { const d = aFecha(s); d.setDate(d.getDate() + n); return iso(d); };
const larga = s => aFecha(s).toLocaleDateString('es-AR', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'});
const corta = s => aFecha(s).toLocaleDateString('es-AR', {weekday: 'short', day: 'numeric', month: 'short'});
const dmy = s => s.split('-').reverse().join('/');
const limiteTxt = v => { if (!v) return 'Sin fecha'; const [d, t] = v.split(' '); return dmy(d) + (t && t.slice(0, 5) !== '00:00' ? ' ' + t.slice(0, 5) : ''); };
const horas = a => a.inicio ? `${a.inicio} a ${a.fin}` + (a.cruza ? ' (pasa la medianoche)' : '') : 'Sin hora';

function aplicarTema(t) {
  document.documentElement.dataset.tema = t;
  localStorage.setItem('lf-tema', t);
}
aplicarTema(localStorage.getItem('lf-tema') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro'));
const botonTema = () => el('button', {class: 'sec mini', type: 'button', text: 'Cambiar tema',
  onclick: () => aplicarTema(document.documentElement.dataset.tema === 'oscuro' ? 'claro' : 'oscuro')});

async function api(action, body, query) {
  let r;
  try {
    r = await fetch(`api.php?action=${action}${query || ''}`, {
      method: body === undefined ? 'GET' : 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: body === undefined ? {} : {'Content-Type': 'application/json', 'X-CSRF-Token': S.csrf},
      ...(body === undefined ? {} : {body: JSON.stringify(body)})
    });
  } catch { throw new Error('No se pudo contactar al servidor. Revisá que Apache esté iniciado.'); }
  let d;
  try { d = await r.json(); } catch { throw new Error('Abrí la página desde Apache/PHP (http://localhost/...), no como archivo.'); }
  if (d.csrf) S.csrf = d.csrf;
  if (!r.ok) { const e = new Error(d.error || 'No se pudo completar la acción.'); e.status = r.status; e.detalle = d.detalle; throw e; }
  return d;
}

/* Muestra un error: si la sesión venció vuelve al login. */
function mostrarError(e, destino) {
  if (e.status === 401 && S.user) { document.querySelectorAll('dialog[open]').forEach(d => d.close()); S.user = null; S.data = null; vistaAuth('login', 'Tu sesión venció. Volvé a ingresar.'); return; }
  const t = e.message + (e.detalle ? ` (${e.detalle})` : '');
  if (destino) { destino.textContent = t; destino.classList.add('error'); } else alert(t);
}

/* ---------- Select2 (si está disponible) ---------- */
function conSelect2(select, padre, alCambiar) {
  if (window.jQuery && jQuery.fn.select2) {
    const $s = jQuery(select);
    $s.select2({width: '100%', dropdownParent: padre ? jQuery(padre) : undefined});
    if (alCambiar) $s.on('change', () => alCambiar(select.value));
  } else if (alCambiar) select.addEventListener('change', () => alCambiar(select.value));
}

/* ---------- formularios en ventana ---------- */
/* campos: {n, l, t: text|textarea|date|time|color|select|dias|check|password, req, max, opts:[[valor,etiqueta]], s2, ayuda, ro} */
function formulario(titulo, campos, valores, guardar, textoBoton = 'Guardar') {
  return new Promise(resolve => {
    const dlg = el('dialog');
    const err = el('p', {class: 'msg', role: 'alert'});
    const entradas = {};
    const form = el('form', {novalidate: false});
    form.append(el('h2', {text: titulo}));
    for (const c of campos) {
      let inp;
      const v = valores?.[c.n];
      const id = 'f_' + c.n;
      if (c.t === 'textarea') inp = el('textarea', {id, maxlength: c.max});
      else if (c.t === 'select') {
        inp = el('select', {id}, c.opts.map(([val, lab]) => el('option', {value: val ?? '', text: lab})));
      } else if (c.t === 'dias') {
        inp = el('div', {class: 'dias-sel', id}, DIAS.map((d, i) => el('label', {},
          el('input', {type: 'checkbox', value: i + 1, checked: (v || []).includes(i + 1)}), DIAS_L[i] + ' ' + d.slice(0, 3))));
      } else if (c.t === 'check') inp = el('input', {type: 'checkbox', id});
      else inp = el('input', {id, type: c.t || 'text', maxlength: c.max, readonly: c.ro, autocomplete: c.t === 'password' ? 'new-password' : 'off'});
      if (c.req && c.t !== 'dias') inp.required = true;
      if (c.t === 'check') inp.checked = !!v;
      else if (c.t !== 'dias') inp.value = v ?? c.def ?? '';
      entradas[c.n] = inp;
      if (c.t === 'check') form.append(el('label', {for: id, style: 'display:flex;gap:8px;align-items:center'}, inp, c.l));
      else form.append(el('label', {for: id, text: c.l}), inp);
      if (c.ayuda) form.append(el('p', {class: 'muted', text: c.ayuda}));
    }
    const ok = el('button', {type: 'submit', text: textoBoton});
    const no = el('button', {type: 'button', class: 'sec', text: 'Cancelar', onclick: () => dlg.close()});
    form.append(err, el('div', {class: 'acciones'}, no, ok));
    form.addEventListener('submit', async ev => {
      ev.preventDefault();
      const out = {};
      for (const c of campos) {
        const i = entradas[c.n];
        if (c.t === 'dias') out[c.n] = [...i.querySelectorAll('input:checked')].map(x => Number(x.value));
        else if (c.t === 'check') out[c.n] = i.checked;
        else out[c.n] = i.value;
      }
      ok.disabled = true; err.textContent = ''; err.classList.remove('error');
      try { await guardar(out); resolve(true); dlg.close(); }
      catch (e) { mostrarError(e, err); }
      finally { ok.disabled = false; }
    });
    dlg.addEventListener('close', () => { dlg.remove(); resolve(false); });
    dlg.append(form);
    document.body.append(dlg);
    dlg.showModal();
    for (const c of campos) if (c.t === 'select' && c.s2) conSelect2(entradas[c.n], dlg);
    (form.querySelector('input:not([type=checkbox]),select,textarea') || ok).focus();
  });
}

async function confirmar(texto, accion) {
  if (!confirm(texto)) return;
  try { await accion(); await refrescar(); } catch (e) { mostrarError(e); }
}

/* ---------- arranque y navegación ---------- */
const RUTAS = {hoy: 'Hoy', semana: 'Semana', tareas: 'Tareas', habitos: 'Hábitos', rutinas: 'Rutinas',
  categorias: 'Categorías', cuenta: 'Mi cuenta', admin: 'Administración'};

function ruta() {
  const h = location.hash.replace(/^#\/?/, '');
  const [r, id] = h.split('/');
  return {r: r || 'hoy', id};
}
function ir(r) { if (location.hash === '#/' + r) refrescar(); else location.hash = '#/' + r; }

async function refrescar() {
  const {r, id} = ruta();
  if (r === 'estadisticas') { ir('cuenta'); return; } // las estadísticas ahora están en Mi cuenta
  if (r === 'perfiles' || r === 'perfil') return vistaPublica(r, id);
  if (!S.user) return vistaAuth('login');
  try {
    S.data = await api('data');
  } catch (e) { return mostrarError(e); }
  armarMarco(r);
  const vista = $('vista');
  vista.replaceChildren(el('p', {class: 'muted', text: 'Cargando…'}));
  try {
    const nodo = await ({hoy: pgHoy, semana: pgSemana, tareas: pgTareas, habitos: pgHabitos, rutinas: pgRutinas,
      categorias: pgCategorias, cuenta: pgCuenta, admin: pgAdmin}[r] || pgHoy)();
    vista.replaceChildren(nodo);
  } catch (e) { vista.replaceChildren(el('p', {class: 'msg error', text: e.message})); mostrarError(e, vista.firstChild); }
}

function armarMarco(actual) {
  const items = Object.entries(RUTAS).filter(([k]) => k !== 'admin' || S.user.rol === 'Administrador');
  $('app').replaceChildren(
    el('header', {class: 'topbar'},
      el('div', {class: 'brand'}, 'LifeFlow'),
      el('nav', {'aria-label': 'Secciones'}, items.map(([k, t]) =>
        el('a', {href: '#/' + k, 'aria-current': k === actual ? 'page' : false, text: t}))),
      el('div', {class: 'cuenta'}, el('span', {text: `${S.user.alias} · ${S.user.rol}`}), botonTema(),
        el('button', {class: 'sec mini', type: 'button', text: 'Cerrar sesión', onclick: salir}))),
    el('main', {id: 'vista'}));
}

async function salir() {
  try { await api('logout', {}); } catch {}
  S.user = null; S.data = null; vistaAuth('login', 'Sesión cerrada.');
  try { await api('session'); } catch {}
}

/* ---------- visitante: login, registro y perfiles públicos ---------- */
function vistaAuth(modo, mensaje) {
  const msg = el('p', {class: 'msg', role: 'status', 'aria-live': 'polite', text: mensaje || ''});
  const alias = el('input', {id: 'a_alias', maxlength: 50, autocomplete: 'nickname'});
  const email = el('input', {id: 'a_email', type: 'email', maxlength: 190, required: true, autocomplete: 'username', placeholder: 'tu@correo.com'});
  const clave = el('input', {id: 'a_clave', type: 'password', required: true, autocomplete: modo === 'login' ? 'current-password' : 'new-password'});
  const boton = el('button', {type: 'submit', style: 'width:100%;margin-top:22px', text: modo === 'login' ? 'Ingresar' : 'Crear cuenta'});
  const form = el('form', {},
    modo === 'registro' ? [el('label', {for: 'a_alias', text: 'Alias (se muestra en tu perfil)'}), alias] : null,
    el('label', {for: 'a_email', text: 'Correo electrónico'}), email,
    el('label', {for: 'a_clave', text: modo === 'login' ? 'Contraseña' : 'Contraseña (mínimo 8 caracteres)'}), clave,
    modo === 'registro' ? el('p', {class: 'muted', style: 'margin-top:12px', text: 'Tu perfil va a ser público (solo alias, racha y porcentaje). En Mi cuenta podés activar el candado para ocultarlo.'}) : null,
    boton);
  alias.required = true;
  form.addEventListener('submit', async ev => {
    ev.preventDefault();
    boton.disabled = true; msg.classList.remove('error'); msg.textContent = 'Verificando…';
    try {
      const d = await api(modo === 'login' ? 'login' : 'register',
        modo === 'login' ? {email: email.value.trim(), password: clave.value}
          : {alias: alias.value.trim(), email: email.value.trim(), password: clave.value});
      S.user = d.user; clave.value = '';
      location.hash = '#/hoy'; await refrescar();
    } catch (e) { msg.textContent = e.message; msg.classList.add('error'); boton.disabled = false; }
  });
  const tab = (m, t) => el('button', {type: 'button', role: 'tab', 'aria-selected': modo === m, text: t, onclick: () => vistaAuth(m)});
  $('app').replaceChildren(el('div', {class: 'auth'}, el('section', {class: 'auth-card'},
    el('div', {class: 'brand'}, 'LifeFlow'),
    el('h1', {style: 'margin-top:18px', text: modo === 'login' ? 'Ingresar' : 'Crear cuenta'}),
    el('p', {class: 'muted', text: modo === 'login' ? 'Usá tu correo y tu contraseña.' : 'Completá los datos para registrarte.'}),
    el('div', {class: 'tabs', role: 'tablist'}, tab('login', 'Ingresar'), tab('registro', 'Crear cuenta')),
    form, msg,
    el('div', {style: 'display:flex;justify-content:space-between;margin-top:18px;gap:10px;flex-wrap:wrap'},
      el('a', {href: '#/perfiles', text: 'Ver perfiles públicos'}), botonTema()))));
  email.focus();
}

async function vistaPublica(r, id) {
  const marco = (...hijos) => $('app').replaceChildren(el('div', {class: 'auth', style: 'align-items:start'},
    el('section', {class: 'auth-card', style: 'max-width:560px'},
      el('div', {class: 'brand'}, 'LifeFlow'),
      ...hijos,
      el('p', {style: 'margin-top:20px'}, el('a', {href: '#/' + (S.user ? 'hoy' : 'login'), text: S.user ? 'Volver a mi cuenta' : 'Ingresar o crear cuenta'})))));
  try {
    if (r === 'perfiles') {
      const d = await api('perfiles');
      marco(el('h1', {style: 'margin-top:20px', text: 'Perfiles públicos'}),
        el('p', {class: 'muted', text: 'Solo se muestran las cuentas que no activaron el candado.'}),
        el('div', {style: 'margin-top:14px'}, d.perfiles.length ? d.perfiles.map(p =>
          el('div', {class: 'fila'}, el('a', {href: '#/perfil/' + p.id, text: p.alias}))) : el('p', {class: 'vacio', text: 'Todavía no hay perfiles públicos.'})));
    } else {
      const d = await api('perfil', undefined, '&id=' + encodeURIComponent(id || ''));
      if (d.privado) marco(el('h1', {style: 'margin-top:20px', text: 'Esta cuenta es privada'}), el('p', {class: 'muted', text: 'Su dueño activó el candado, así que no se muestra ningún dato.'}));
      else marco(el('h1', {style: 'margin-top:20px', text: d.alias}), el('p', {class: 'muted', text: 'Miembro desde ' + dmy(d.alta)}),
        el('div', {class: 'perfil-datos'},
          dato('Racha actual', `${d.racha_actual} día${d.racha_actual === 1 ? '' : 's'}`), dato('Mejor racha', `${d.mejor_racha} día${d.mejor_racha === 1 ? '' : 's'}`),
          dato('Cumplimiento (30 días)', d.porcentaje_30 === null ? 'Sin datos' : d.porcentaje_30 + ' %'), dato('Tareas completadas', d.tareas_completadas)));
    }
  } catch (e) { marco(el('p', {class: 'msg error', text: e.message}), el('p', {}, el('a', {href: '#/perfiles', text: 'Ver perfiles públicos'}))); }
}
const dato = (t, v) => el('div', {}, el('div', {class: 'muted', text: t}), el('div', {class: 'num', style: 'font-size:1.5rem', text: String(v)}));

/* ---------- Hoy (panel diario) ---------- */
function navFecha(fecha, cambiar, paso = 1, etiqueta = 'Hoy') {
  const inp = el('input', {type: 'date', value: fecha, 'aria-label': 'Fecha', onchange: () => inp.value && cambiar(inp.value)});
  return el('div', {class: 'nav-fecha'},
    el('button', {class: 'sec mini', type: 'button', text: '←', 'aria-label': 'Anterior', onclick: () => cambiar(sumar(fecha, -paso))}),
    inp,
    el('button', {class: 'sec mini', type: 'button', text: '→', 'aria-label': 'Siguiente', onclick: () => cambiar(sumar(fecha, paso))}),
    el('button', {class: 'sec mini', type: 'button', text: etiqueta, onclick: () => cambiar(S.data.hoy)}));
}

async function pgHoy() {
  S.fecha = S.fecha || S.data.hoy;
  const d = await api('dia', undefined, '&fecha=' + S.fecha);
  const cambiar = f => { S.fecha = f; refrescar(); };
  const raiz = el('div', {});
  raiz.append(el('div', {class: 'cabecera'}, el('div', {}, el('h1', {text: d.es_hoy ? 'Hoy' : 'Panel del día'}), el('p', {class: 'muted', text: larga(d.fecha)})), navFecha(S.fecha, cambiar)));

  // Progreso y racha
  const prog = el('div', {class: 'card'});
  if (!d.total) prog.append(el('h2', {text: 'Sin hábitos en el total'}), el('p', {class: 'muted', text: 'No hay hábitos programados para este día (o todos están cancelados o postergados con justificación).'}));
  else {
    prog.append(el('div', {class: 'num', text: `${d.hechos} de ${d.total} hábitos`}),
      el('div', {class: 'barra', role: 'progressbar', 'aria-valuenow': d.porcentaje, 'aria-valuemin': 0, 'aria-valuemax': 100, 'aria-label': 'Progreso del día'},
        el('i', {style: `width:${d.porcentaje}%`}), el('b', {title: 'Meta: 70 %'})),
      el('p', {class: 'muted', text: `${d.porcentaje} %. ` + (d.cumple ? 'Ya alcanzaste el 70 % de hoy.' : `Con ${d.faltan} hábito${d.faltan === 1 ? '' : 's'} más cumplís el día (meta 70 %).`)}));
  }
  if (d.es_hoy) prog.append(el('p', {class: 'muted', style: 'margin-top:8px', text: 'El día se evalúa cuando termina: hoy todavía no suma ni corta la racha.'}));
  prog.append(el('p', {style: 'margin-top:12px'}, el('strong', {text: `Racha: ${d.racha.actual} día${d.racha.actual === 1 ? '' : 's'}`}), ` · Mejor: ${d.racha.mejor}`));
  if (d.tipo) prog.append(el('p', {class: 'muted', text: 'Este día quedó: ' + {cumple: 'cumplido', falla: 'incumplido', justificado: 'justificado', neutral: 'sin hábitos'}[d.tipo] + '.'}));

  // Justificación del día
  const just = el('div', {});
  if (d.justificacion) just.append(el('div', {class: 'justificado'}, el('strong', {text: 'Día justificado'}), el('p', {style: 'margin:4px 0 8px', text: d.justificacion}),
    el('button', {class: 'sec mini', type: 'button', text: 'Editar motivo', onclick: () => justificar(d)}), ' ',
    el('button', {class: 'peligro mini', type: 'button', text: 'Quitar', onclick: () => confirmar('¿Quitar la justificación de este día?', () => api('justificacion_eliminar', {fecha: d.fecha}))})));
  else if (!d.es_futuro) just.append(el('button', {class: 'sec mini', type: 'button', style: 'margin-top:12px', text: 'Justificar este día', onclick: () => justificar(d)}));

  // Hábitos
  const hab = el('div', {class: 'card'}, el('h2', {text: 'Hábitos del día'}));
  if (!d.habitos.length) hab.append(el('p', {class: 'vacio', text: 'No tenés hábitos programados para este día.'}));
  d.habitos.forEach(h => {
    const sel = el('select', {'aria-label': 'Estado de ' + h.nombre, style: 'width:auto;min-width:210px'},
      Object.entries(EST_H).map(([v, t]) => el('option', {value: v, text: t, selected: v === h.estado})));
    sel.addEventListener('change', () => marcar(h, d.fecha, sel.value, sel, h.estado));
    hab.append(el('div', {class: 'fila'}, el('div', {class: 'izq'}, el('span', {class: 'punto', style: `background:${h.color}`}),
      el('div', {}, el('div', {text: h.nombre, class: h.estado === 'cancelada' ? 'tachado' : ''}), h.justificacion ? el('div', {class: 'muted', text: 'Motivo: ' + h.justificacion}) : null)),
      el('div', {class: 'der'}, sel)));
  });
  raiz.append(el('div', {class: 'grid2'}, el('div', {}, prog, just), hab));

  // Tareas del día
  const todas = [...d.vencidas, ...d.tareas];
  const tar = el('div', {class: 'card'}, el('h2', {text: 'Tareas del día'}));
  if (!todas.length) tar.append(el('p', {class: 'vacio', text: 'No hay tareas con fecha límite para este día.'}));
  todas.forEach(t => tar.append(filaTarea(t, d.vencidas.includes(t))));
  // Actividades de rutina
  const act = el('div', {class: 'card'}, el('h2', {text: 'Rutinas del día'}));
  if (!d.actividades.length) act.append(el('p', {class: 'vacio', text: 'No hay actividades de rutina para este día.'}));
  d.actividades.forEach(a => act.append(filaActividad(a, d.fecha)));
  if (d.actividades.length && !S.data.excepciones) act.append(el('p', {class: 'aviso', style: 'margin-top:12px', text: 'Para cambiar o cancelar una actividad solo en un día, importá database/extra_excepciones_rutina.sql en phpMyAdmin.'}));
  raiz.append(tar, act);
  return raiz;
}

async function marcar(h, fecha, estado, sel, previo) {
  const enviar = just => api('registro_guardar', {habito_id: h.id, fecha, estado, justificacion: just}).then(refrescar);
  try {
    if (estado === 'postergada_con_justificacion') {
      const ok = await formulario('Postergar con justificación', [{n: 'justificacion', l: '¿Por qué lo postergás?', t: 'textarea', req: true, max: 500}], {}, v => enviar(v.justificacion));
      if (!ok) sel.value = previo;
    } else await enviar(null);
  } catch (e) { sel.value = previo; mostrarError(e); }
}

function justificar(d) {
  return formulario('Justificar el día', [{n: 'justificacion', l: 'Motivo (texto libre)', t: 'textarea', req: true, max: 500,
    ayuda: 'Un día justificado no corta la racha y queda marcado.'}], {justificacion: d.justificacion || ''},
  v => api('justificacion_guardar', {fecha: d.fecha, justificacion: v.justificacion})).then(ok => ok && refrescar());
}

function filaTarea(t, vencida) {
  const chk = el('input', {type: 'checkbox', checked: t.estado === 'completada', 'aria-label': 'Completar ' + t.titulo,
    onchange: () => api('tarea_estado', {id: t.id, estado: chk.checked ? 'completada' : 'pendiente'}).then(refrescar).catch(e => { chk.checked = !chk.checked; mostrarError(e); })});
  return el('div', {class: 'fila'}, el('div', {class: 'izq'}, chk, el('div', {},
    el('div', {text: t.titulo, class: ['completada', 'cancelada'].includes(t.estado) ? 'tachado' : ''}),
    el('div', {class: 'muted'}, `${t.categoria || 'Sin categoría'} · Prioridad ${PRIO[t.prioridad].toLowerCase()} · ${limiteTxt(t.fecha_limite)}`))),
  el('div', {class: 'der'}, vencida ? el('span', {class: 'pill bad', text: 'Vencida'}) : null, el('span', {class: 'pill', text: EST_T[t.estado]})));
}

function filaActividad(a, fecha) {
  const botones = [];
  if (S.data.excepciones && !a.continua) {
    botones.push(el('button', {class: 'sec mini', type: 'button', text: 'Cambiar solo este día', onclick: () => excepcion(a, fecha)}));
    if (!a.cancelada) botones.push(el('button', {class: 'sec mini', type: 'button', text: 'Cancelar solo este día', onclick: () => api('excepcion_guardar', {actividad_id: a.id, fecha, cancelada: true}).then(refrescar).catch(mostrarError)}));
    if (a.excepcion) botones.push(el('button', {class: 'sec mini', type: 'button', text: 'Volver a la rutina', onclick: () => api('excepcion_eliminar', {actividad_id: a.id, fecha}).then(refrescar).catch(mostrarError)}));
  }
  return el('div', {class: 'fila'}, el('div', {class: 'izq'}, el('span', {class: 'punto', style: `background:${a.color}`}),
    el('div', {}, el('div', {text: a.nombre, class: a.cancelada ? 'tachado' : ''}),
      el('div', {class: 'muted', text: `${a.rutina} · ` + (a.continua ? `Sigue de anoche hasta las ${a.fin}` : horas(a))}))),
  el('div', {class: 'der'}, a.cancelada ? el('span', {class: 'pill warn', text: 'Cancelada hoy'}) : null,
    a.excepcion && !a.cancelada ? el('span', {class: 'pill warn', text: 'Cambio solo hoy'}) : null, botones));
}

function excepcion(a, fecha) {
  return formulario('Cambiar solo este día', [
    {n: 'inicio', l: 'Hora de inicio', t: 'time', req: true}, {n: 'fin', l: 'Hora de fin', t: 'time', req: true,
      ayuda: 'Las demás repeticiones de la rutina no cambian.'}],
  {inicio: a.inicio || '', fin: a.fin || ''}, v => api('excepcion_guardar', {actividad_id: a.id, fecha, inicio: v.inicio, fin: v.fin})).then(ok => ok && refrescar());
}

/* ---------- Semana (calendario semanal) ---------- */
async function pgSemana() {
  S.mostrarSemana = S.mostrarSemana || S.data.hoy;
  const w = await api('semana', undefined, '&fecha=' + S.mostrarSemana);
  const cambiar = f => { S.mostrarSemana = f; refrescar(); };
  const raiz = el('div', {}, el('div', {class: 'cabecera'}, el('div', {}, el('h1', {text: 'Semana'}), el('p', {class: 'muted', text: `${corta(w.lunes)} al ${corta(w.domingo)}`})),
    navFecha(S.mostrarSemana, cambiar, 7, 'Esta semana')));
  // cuadrícula de hábitos
  const filas = new Map();
  w.dias.forEach((d, i) => d.habitos.forEach(h => { if (!filas.has(h.id)) filas.set(h.id, {h, celdas: Array(7).fill(null)}); filas.get(h.id).celdas[i] = h.estado; }));
  const cab = el('tr', {}, el('th', {scope: 'col', text: 'Hábito'}), w.dias.map(d => el('th', {scope: 'col', class: d.es_hoy ? 'hoy-col' : ''},
    el('button', {type: 'button', title: 'Ver este día', onclick: () => { S.fecha = d.fecha; ir('hoy'); }}, DIAS_L[d.dia_semana - 1], el('br'), aFecha(d.fecha).getDate()))));
  const cuerpo = [...filas.values()].map(({h, celdas}) => el('tr', {}, el('td', {}, el('span', {class: 'punto', style: `background:${h.color};margin-right:8px`}), h.nombre),
    celdas.map((e, i) => el('td', {class: w.dias[i].es_hoy ? 'hoy-col' : ''}, e === null ? '' : celda(e, w.dias[i]) ))));
  const total = el('tr', {}, el('td', {text: 'Día'}), w.dias.map(d => el('td', {class: d.es_hoy ? 'hoy-col' : ''},
    d.justificacion ? el('span', {class: 'pill warn', title: d.justificacion, text: 'Justificado'}) :
      d.total ? el('span', {class: 'muted', text: `${d.hechos}/${d.total}`}) : '')));
  const grid = el('div', {class: 'card'}, el('h2', {text: 'Hábitos de la semana'}),
    filas.size ? el('div', {class: 'tabla-scroll'}, el('table', {class: 'sem'}, el('thead', {}, cab), el('tbody', {}, cuerpo, total))) : el('p', {class: 'vacio', text: 'No hay hábitos programados esta semana.'}),
    el('div', {class: 'leyenda'}, leyenda('completada', '✓', 'Hecho'), leyenda('pendiente', '', 'Pendiente / no hecho'), leyenda('cancelada', '×', 'Cancelado'),
      leyenda('postergada_con_justificacion', '↷', 'Postergado'), leyenda('jz', '!', 'Día justificado')));
  const dias = el('div', {class: 'semana-dias'}, w.dias.map(d => el('div', {class: d.es_hoy ? 'hoy' : ''},
    el('h4', {text: corta(d.fecha)}),
    d.justificacion ? el('div', {class: 'mini-item'}, el('span', {class: 'pill warn', text: 'Justificado'}), ' ', d.justificacion) : null,
    d.tareas.map(t => el('div', {class: 'mini-item'}, `Tarea: ${t.titulo}`)),
    d.actividades.filter(a => !a.cancelada).map(a => el('div', {class: 'mini-item'}, `${a.inicio ? a.inicio + (a.continua ? ' (sigue)' : '') + ' · ' : ''}${a.nombre}`)),
    !d.justificacion && !d.tareas.length && !d.actividades.length ? el('div', {class: 'muted', text: 'Libre'}) : null)));
  raiz.append(grid, el('div', {class: 'card'}, el('h2', {text: 'Tareas y rutinas por día'}), dias));
  return raiz;
}
function celda(estado, dia) {
  const jz = !!dia.justificacion;
  const sim = {completada: '✓', cancelada: '×', postergada_con_justificacion: '↷', postergada_sin_justificacion: '↷', pendiente: ''}[estado];
  return el('span', {class: 'marca ' + (jz && estado !== 'completada' ? 'jz' : estado), title: EST_H[estado] + (jz ? ' (día justificado)' : ''), 'aria-label': EST_H[estado], text: jz && estado !== 'completada' ? '!' : sim});
}
const leyenda = (cls, sim, txt) => el('span', {}, el('span', {class: 'marca ' + cls, text: sim}), txt);

/* ---------- Tareas ---------- */
const optsCat = (con = true) => [...(con ? [['', 'Sin categoría']] : []), ...S.data.categorias.map(c => [c.id, c.nombre])];
let filtro = {cat: '', est: ''};

async function pgTareas() {
  const raiz = el('div', {});
  const lista = el('div', {});
  const selCat = el('select', {'aria-label': 'Filtrar por categoría'}, el('option', {value: '', text: 'Todas las categorías'}), S.data.categorias.map(c => el('option', {value: c.id, text: c.nombre, selected: String(c.id) === String(filtro.cat)})));
  const selEst = el('select', {'aria-label': 'Filtrar por estado', style: 'width:auto'}, el('option', {value: '', text: 'Todos los estados'}), Object.entries(EST_T).map(([v, t]) => el('option', {value: v, text: t, selected: v === filtro.est})));
  const pintar = () => {
    const hoy = S.data.hoy, grupos = {Vencidas: [], Hoy: [], Próximas: [], 'Sin fecha': [], 'Completadas y canceladas': []};
    S.data.tareas.filter(t => (!filtro.cat || String(t.categoria_id) === String(filtro.cat)) && (!filtro.est || t.estado === filtro.est)).forEach(t => {
      const f = t.fecha_limite ? t.fecha_limite.slice(0, 10) : null;
      if (['completada', 'cancelada'].includes(t.estado)) grupos['Completadas y canceladas'].push(t);
      else if (!f) grupos['Sin fecha'].push(t);
      else if (f < hoy) grupos.Vencidas.push(t); else if (f === hoy) grupos.Hoy.push(t); else grupos.Próximas.push(t);
    });
    lista.replaceChildren();
    let alguna = false;
    for (const [g, ts] of Object.entries(grupos)) {
      if (!ts.length) continue; alguna = true;
      lista.append(el('div', {class: 'card'}, el('h2', {text: `${g} (${ts.length})`}), ts.map(t => tareaCompleta(t, g === 'Vencidas'))));
    }
    if (!alguna) lista.append(el('div', {class: 'card'}, el('p', {class: 'vacio', text: 'No hay tareas para mostrar. Creá la primera con “Nueva tarea”.'})));
  };
  raiz.append(el('div', {class: 'cabecera'}, el('h1', {text: 'Tareas'}),
    el('div', {class: 'nav-fecha'}, selCat, selEst, el('button', {type: 'button', text: 'Nueva tarea', onclick: () => editarTarea()}))), lista);
  pintar();
  conSelect2(selCat, null, v => { filtro.cat = v; pintar(); });
  selEst.addEventListener('change', () => { filtro.est = selEst.value; pintar(); });
  return raiz;
}

function tareaCompleta(t, vencida) {
  const f = filaTarea(t, vencida);
  const der = f.querySelector('.der');
  der.append(el('button', {class: 'sec mini', type: 'button', text: 'Editar', onclick: () => editarTarea(t)}),
    el('button', {class: 'peligro mini', type: 'button', text: 'Eliminar', onclick: () => confirmar('¿Eliminar la tarea “' + t.titulo + '”?', () => api('tarea_eliminar', {id: t.id}))}));
  return f;
}

function editarTarea(t) {
  const v = t ? {...t, fecha: t.fecha_limite ? t.fecha_limite.slice(0, 10) : '', hora: t.fecha_limite && t.fecha_limite.slice(11, 16) !== '00:00' ? t.fecha_limite.slice(11, 16) : '', categoria_id: t.categoria_id ?? ''} : {prioridad: 'media', estado: 'pendiente', categoria_id: ''};
  return formulario(t ? 'Editar tarea' : 'Nueva tarea', [
    {n: 'titulo', l: 'Título', req: true, max: 150}, {n: 'descripcion', l: 'Descripción (opcional)', t: 'textarea', max: 2000},
    {n: 'categoria_id', l: 'Categoría', t: 'select', s2: true, opts: optsCat()},
    {n: 'fecha', l: 'Fecha límite (opcional)', t: 'date'}, {n: 'hora', l: 'Hora (opcional)', t: 'time'},
    {n: 'prioridad', l: 'Prioridad', t: 'select', opts: Object.entries(PRIO)}, {n: 'estado', l: 'Estado', t: 'select', opts: Object.entries(EST_T)}],
  v, x => api('tarea_guardar', {...x, id: t?.id, categoria_id: x.categoria_id || null})).then(ok => ok && refrescar());
}

/* ---------- Hábitos ---------- */
async function pgHabitos() {
  const raiz = el('div', {}, el('div', {class: 'cabecera'}, el('h1', {text: 'Hábitos'}), el('button', {type: 'button', text: 'Nuevo hábito', onclick: () => editarHabito()})));
  if (!S.data.habitos.length) raiz.append(el('div', {class: 'card'}, el('p', {class: 'vacio', text: 'Todavía no tenés hábitos. Creá uno y elegí los días en que lo querés hacer.'})));
  S.data.habitos.forEach(h => {
    const cat = S.data.categorias.find(c => c.id === h.categoria_id);
    raiz.append(el('div', {class: 'card'}, el('div', {class: 'fila', style: 'border:0;padding:0'},
      el('div', {class: 'izq'}, el('span', {class: 'punto', style: `background:${h.color}`}), el('div', {}, el('strong', {text: h.nombre}), h.descripcion ? el('div', {class: 'muted', text: h.descripcion}) : null,
        el('div', {class: 'muted', text: `${cat ? cat.nombre + ' · ' : ''}${h.dias.length} día${h.dias.length === 1 ? '' : 's'} por semana`}))),
      el('div', {class: 'der'}, el('div', {class: 'chips', 'aria-label': 'Días: ' + h.dias.map(d => DIAS[d - 1]).join(', ')}, DIAS_L.map((l, i) => el('span', {class: 'chip' + (h.dias.includes(i + 1) ? ' on' : ''), text: l}))),
        el('button', {class: 'sec mini', type: 'button', text: 'Editar', onclick: () => editarHabito(h)}),
        el('button', {class: 'peligro mini', type: 'button', text: 'Eliminar', onclick: () => confirmar('¿Eliminar el hábito “' + h.nombre + '”? Sus registros se conservan.', () => api('habito_eliminar', {id: h.id}))})))));
  });
  return raiz;
}
function editarHabito(h) {
  const v = h ? {...h, categoria_id: h.categoria_id ?? ''} : {color: '#1F5C3F', dias: [], categoria_id: ''};
  return formulario(h ? 'Editar hábito' : 'Nuevo hábito', [
    {n: 'nombre', l: 'Nombre', req: true, max: 100}, {n: 'descripcion', l: 'Descripción (opcional)', t: 'textarea', max: 2000},
    {n: 'dias', l: 'Días de la semana', t: 'dias'}, {n: 'categoria_id', l: 'Categoría', t: 'select', s2: true, opts: optsCat()},
    {n: 'color', l: 'Color', t: 'color'}], v, x => {
    if (!x.dias.length) throw new Error('Elegí al menos un día de la semana.');
    return api('habito_guardar', {...x, id: h?.id, categoria_id: x.categoria_id || null});
  }).then(ok => ok && refrescar());
}

/* ---------- Rutinas ---------- */
async function pgRutinas() {
  const raiz = el('div', {}, el('div', {class: 'cabecera'}, el('h1', {text: 'Rutinas'}),
    el('div', {class: 'nav-fecha'}, el('button', {class: 'sec', type: 'button', text: 'Usar una plantilla', onclick: () => plantilla()}), el('button', {type: 'button', text: 'Nueva rutina', onclick: () => editarRutina()}))));
  raiz.append(el('p', {class: 'aviso', text: 'Las plantillas son un punto de partida: al elegirlas se crea una copia tuya que podés cambiar libremente. Podés superponer actividades.'}));
  if (!S.data.rutinas.length) raiz.append(el('div', {class: 'card'}, el('p', {class: 'vacio', text: 'Todavía no tenés rutinas. Empezá con una plantilla o creá la tuya.'})));
  S.data.rutinas.forEach(r => {
    const card = el('div', {class: 'card'}, el('div', {class: 'fila', style: 'border:0;padding:0 0 8px'},
      el('div', {class: 'izq'}, el('div', {}, el('h2', {style: 'margin:0', text: r.nombre}), r.descripcion ? el('div', {class: 'muted', text: r.descripcion}) : null)),
      el('div', {class: 'der'}, el('button', {class: 'sec mini', type: 'button', text: 'Agregar actividad', onclick: () => editarActividad(r)}),
        el('button', {class: 'sec mini', type: 'button', text: 'Editar', onclick: () => editarRutina(r)}),
        el('button', {class: 'peligro mini', type: 'button', text: 'Eliminar', onclick: () => confirmar('¿Eliminar la rutina “' + r.nombre + '”?', () => api('rutina_eliminar', {id: r.id}))}))));
    if (!r.actividades.length) card.append(el('p', {class: 'vacio', text: 'Esta rutina todavía no tiene actividades.'}));
    r.actividades.forEach(a => card.append(el('div', {class: 'fila'}, el('div', {class: 'izq'}, el('span', {class: 'punto', style: `background:${a.color}`}),
      el('div', {}, el('div', {text: a.nombre}), el('div', {class: 'muted', text: `${horas(a)} · ${a.dias.map(d => DIAS_L[d - 1]).join(' ')}`}))),
    el('div', {class: 'der'}, el('button', {class: 'sec mini', type: 'button', text: 'Editar', onclick: () => editarActividad(r, a)}),
      el('button', {class: 'peligro mini', type: 'button', text: 'Eliminar', onclick: () => confirmar('¿Eliminar la actividad “' + a.nombre + '”?', () => api('actividad_eliminar', {id: a.id}))})))));
    raiz.append(card);
  });
  return raiz;
}
const editarRutina = r => formulario(r ? 'Editar rutina' : 'Nueva rutina', [{n: 'nombre', l: 'Nombre', req: true, max: 100}, {n: 'descripcion', l: 'Descripción (opcional)', t: 'textarea', max: 2000}],
  r || {}, x => api('rutina_guardar', {...x, id: r?.id})).then(ok => ok && refrescar());
const plantilla = () => formulario('Usar una plantilla', [{n: 'plantilla', l: 'Plantilla', t: 'select', opts: S.data.plantillas.map(p => [p.clave, `${p.nombre} (${p.actividades} actividades)`]), ayuda: 'Se crea una copia que podés editar sin tocar la plantilla.'}],
  {}, x => api('rutina_plantilla', x), 'Crear rutina').then(ok => ok && refrescar());
function editarActividad(r, a) {
  return formulario(a ? 'Editar actividad' : 'Nueva actividad', [
    {n: 'nombre', l: 'Nombre', req: true, max: 100}, {n: 'descripcion', l: 'Descripción (opcional)', t: 'textarea', max: 2000},
    {n: 'dias', l: 'Se repite los días', t: 'dias'},
    {n: 'inicio', l: 'Hora de inicio (opcional)', t: 'time'}, {n: 'fin', l: 'Hora de fin (opcional)', t: 'time', ayuda: 'Si el fin es menor que el inicio (por ejemplo 23:00 a 01:00), la actividad pasa la medianoche. Sin horas, queda sin horario.'},
    {n: 'color', l: 'Color', t: 'color'}], a ? {...a} : {color: '#1F5C3F', dias: []},
  x => { if (!x.dias.length) throw new Error('Elegí al menos un día de la semana.'); return api('actividad_guardar', {...x, id: a?.id, rutina_id: r.id}); }).then(ok => ok && refrescar());
}

/* ---------- Categorías ---------- */
async function pgCategorias() {
  const raiz = el('div', {}, el('div', {class: 'cabecera'}, el('h1', {text: 'Categorías'}), el('button', {type: 'button', text: 'Nueva categoría', onclick: () => editarCategoria()})));
  raiz.append(el('div', {class: 'card'}, el('h2', {text: 'Mis categorías'}), S.data.categorias.map(c => el('div', {class: 'fila'},
    el('div', {class: 'izq'}, el('span', {class: 'punto', style: `background:${c.color}`}), c.nombre, c.categoria_predeterminada_id ? el('span', {class: 'pill', text: 'Por defecto'}) : null),
    el('div', {class: 'der'}, el('button', {class: 'sec mini', type: 'button', text: 'Editar', onclick: () => editarCategoria(c)}),
      el('button', {class: 'peligro mini', type: 'button', text: 'Eliminar', onclick: () => confirmar('¿Eliminar la categoría “' + c.nombre + '”? Las tareas y hábitos que la usan quedan sin categoría.', () => api('categoria_eliminar', {id: c.id}))}))))));
  return raiz;
}
const editarCategoria = c => formulario(c ? 'Editar categoría' : 'Nueva categoría', [{n: 'nombre', l: 'Nombre', req: true, max: 60}, {n: 'color', l: 'Color', t: 'color'}],
  c || {color: '#1F5C3F'}, x => api('categoria_guardar', {...x, id: c?.id})).then(ok => ok && refrescar());

/* ---------- Mi cuenta (datos, estadísticas y perfil público) ---------- */
async function pgCuenta() {
  const u = S.user;
  const d = await api('estadisticas');
  const dias = n => `${n} día${n === 1 ? '' : 's'}`;
  const tipos = {cumple: 'cumplido', falla: 'incumplido', justificado: 'justificado', neutral: 'sin hábitos'};
  const fila = (t, v) => el('tr', {}, el('th', {scope: 'row', text: t}), el('td', {text: String(v)}));
  const editar = () => formulario('Mi cuenta', [
    {n: 'alias', l: 'Alias', req: true, max: 50},
    {n: 'candado', l: 'Cuenta candado: ocultar mi perfil público', t: 'check'}],
  {alias: u.alias, candado: u.privacidad === 'privado'}, x => api('perfil_guardar', {alias: x.alias, privacidad: x.candado ? 'privado' : 'publico'})).then(async ok => {
    if (ok) { S.user = (await api('session')).user; refrescar(); }
  });
  return el('div', {},
    el('div', {class: 'cabecera'}, el('h1', {text: 'Mi cuenta'})),
    el('div', {class: 'card'}, el('h2', {text: 'Datos'}),
      el('table', {class: 'lista'}, el('tbody', {}, fila('Alias', u.alias), fila('Correo', u.email), fila('Rol', u.rol),
        fila('Perfil', u.privacidad === 'privado' ? 'Privado (candado activado)' : 'Público'))),
      el('p', {style: 'margin-top:12px'}, el('button', {type: 'button', text: 'Editar alias y privacidad', onclick: editar}))),
    el('div', {class: 'card'}, el('h2', {text: 'Estadísticas'}),
      el('table', {class: 'lista'}, el('tbody', {}, fila('Racha actual', dias(d.racha_actual)), fila('Mejor racha', dias(d.mejor_racha)),
        fila('Cumplimiento de los últimos 30 días', d.porcentaje_30 === null ? 'Sin datos' : d.porcentaje_30 + ' %'),
        fila('Tareas completadas', d.tareas_completadas), fila('Tareas pendientes', d.tareas_pendientes), fila('Días justificados', d.dias_justificados))),
      el('h3', {text: 'Últimos días'}),
      d.ultimos.length ? el('div', {class: 'tira'}, d.ultimos.map(x => el('span', {class: x.tipo, title: `${dmy(x.fecha)}: ${tipos[x.tipo]}`,
        text: {cumple: '✓', falla: '×', justificado: '!', neutral: '–'}[x.tipo]}))) : el('p', {class: 'vacio', text: 'Todavía no hay días terminados.'}),
      el('div', {class: 'leyenda'}, el('span', {text: '✓ cumplido'}), el('span', {text: '× incumplido'}), el('span', {text: '! justificado'}), el('span', {text: '– sin hábitos'})),
      el('p', {class: 'muted', style: 'margin-top:10px', text: 'La racha y los porcentajes se calculan desde tus registros y cuentan hasta ayer: el día de hoy se evalúa cuando termina.'})),
    el('div', {class: 'card'}, el('h2', {text: 'Perfil público'}),
      el('p', {class: 'muted', text: u.privacidad === 'privado' ? 'Tenés el candado activado: nadie ve tu perfil.' : 'Cualquiera puede ver tu alias, racha, mejor racha, cumplimiento de 30 días y tareas completadas. Nunca se muestran tus hábitos, tareas ni motivos.'}),
      el('p', {style: 'margin-top:10px'}, el('a', {href: '#/perfil/' + u.id, text: 'Ver cómo se ve mi perfil'}))));
}

/* ---------- Administración ---------- */
async function pgAdmin() {
  const d = await api('admin_usuarios');
  const cuentas = el('table', {class: 'lista'}, el('thead', {}, el('tr', {}, ['Alias', 'Correo', 'Rol', 'Activa', ''].map(t => el('th', {scope: 'col', text: t})))),
    el('tbody', {}, d.usuarios.map(u => {
      const propio = u.id === S.user.id;
      const rol = el('select', {'aria-label': 'Rol de ' + u.alias, disabled: propio}, d.roles.map(r => el('option', {value: r, text: r, selected: r === u.rol})));
      const act = el('input', {type: 'checkbox', checked: !!Number(u.activo), disabled: propio, 'aria-label': 'Cuenta activa de ' + u.alias});
      return el('tr', {}, el('td', {text: u.alias}), el('td', {text: u.email}), el('td', {}, rol), el('td', {}, act),
        el('td', {}, propio ? el('span', {class: 'muted', text: 'Tu cuenta'}) : el('button', {class: 'sec mini', type: 'button', text: 'Guardar',
          onclick: () => api('admin_usuario', {id: u.id, rol: rol.value, activo: act.checked}).then(() => { alert('Cuenta actualizada.'); refrescar(); }).catch(mostrarError)})));
    })));
  const cp = el('div', {class: 'card'}, el('h2', {text: 'Categorías por defecto'}),
    el('p', {class: 'muted', text: 'Las cuentas nuevas reciben una copia. Al crear una, las cuentas existentes también la reciben. Editar o eliminar no cambia las copias ya entregadas.'}),
    S.data.catpred.map(c => el('div', {class: 'fila'}, el('div', {class: 'izq'}, el('span', {class: 'punto', style: `background:${c.color}`}), c.nombre),
      el('div', {class: 'der'}, el('button', {class: 'sec mini', type: 'button', text: 'Editar', onclick: () => editarCatPred(c)}),
        el('button', {class: 'peligro mini', type: 'button', text: 'Eliminar', onclick: () => confirmar('¿Eliminar la categoría por defecto “' + c.nombre + '”?', () => api('admin_catpred_eliminar', {id: c.id}))})))),
    el('button', {type: 'button', style: 'margin-top:12px', text: 'Nueva categoría por defecto', onclick: () => editarCatPred()}));
  return el('div', {}, el('div', {class: 'cabecera'}, el('h1', {text: 'Administración'})),
    el('p', {class: 'aviso', text: 'Como administrador gestionás cuentas y categorías por defecto. No podés ver los hábitos, tareas ni justificaciones de otras personas.'}),
    cp, el('div', {class: 'card'}, el('h2', {text: 'Cuentas'}), el('div', {class: 'tabla-scroll'}, cuentas)));
}
const editarCatPred = c => formulario(c ? 'Editar categoría por defecto' : 'Nueva categoría por defecto', [{n: 'nombre', l: 'Nombre', req: true, max: 60}, {n: 'color', l: 'Color', t: 'color'}],
  c || {color: '#1F5C3F'}, x => api('admin_catpred_guardar', {...x, id: c?.id})).then(ok => ok && refrescar());

/* ---------- inicio ---------- */
window.addEventListener('hashchange', refrescar);
(async () => {
  try {
    const d = await api('session');
    S.user = d.user;
  } catch (e) { $('app').replaceChildren(el('p', {class: 'msg error', style: 'padding:40px', text: e.message})); return; }
  const {r} = ruta();
  if (r === 'login' || r === 'registro') { S.user ? ir('hoy') : vistaAuth(r); return; }
  await refrescar();
})();
