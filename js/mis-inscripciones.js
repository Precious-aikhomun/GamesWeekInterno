// js/mis-inscripciones.js
(function () {
  var GW = window.GW;

  var listEl = document.getElementById('mis-inscripciones-list');
  var countEl = document.getElementById('mis-insc-count');
  var feedbackEl = document.getElementById('mis-insc-feedback');
  var toggleCanceladas = document.getElementById('toggle-canceladas');

  function estadoLabel(item) {
    if (item.estado === 'confirmada') return 'Confirmada';
    if (item.estado === 'lista_espera') {
      return item.posicion_lista_espera
        ? 'Lista de espera #' + item.posicion_lista_espera
        : 'Lista de espera';
    }
    return 'Cancelada';
  }

  function renderLista(items) {
    if (!listEl || !countEl) return;

    var activas = items.filter(function (i) { return i.estado !== 'cancelada'; }).length;
    countEl.textContent = activas === 1
      ? '1 inscripción activa'
      : activas + ' inscripciones activas';

    if (items.length === 0) {
      listEl.innerHTML =
        '<div class="programa-empty">'
        + 'Todavía no estás inscrito en ninguna actividad. Puedes apuntarte directamente desde el <strong>Programa</strong> o <strong>Torneos</strong>.'
        + '</div>';
      return;
    }

    listEl.innerHTML = items.map(function (item) {
      var cancelled = item.estado === 'cancelada';
      var salaTxt = item.sala ? item.sala.nombre : 'Por confirmar';
      var torneoInfo = item.torneo
        ? '<span><strong>Juego:</strong> ' + GW.escapeHtml(item.torneo.videojuego || '') + '</span>'
        : '';
      var equipoInfo = item.nombre_equipo
        ? '<span><strong>Equipo:</strong> ' + GW.escapeHtml(item.nombre_equipo) + '</span>'
        : '';

      var cancelBtn = '';
      if (!cancelled && !item.finalizada) {
        cancelBtn =
          '<button type="button" class="btn-danger-modal btn-cancelar-insc" data-insc-id="'
          + GW.escapeHtml(item.inscripcion_id) + '">Quitar inscripción</button>';
      } else if (item.finalizada && !cancelled) {
        cancelBtn = '<span class="capacity-text">Actividad finalizada</span>';
      }

      return (
        '<article class="mis-insc-item ' + (cancelled ? 'is-cancelled' : '') + '">'
        + '<div class="mis-insc-top">'
        + '  <div>'
        + '    <div class="activity-badges">'
        + (item.codigo_programa ? '<span class="badge-tag">' + GW.escapeHtml(item.codigo_programa) + '</span>' : '')
        + '      <span class="badge-tag">' + GW.escapeHtml(item.tipo) + '</span>'
        + '    </div>'
        + '    <h4>' + GW.escapeHtml(item.titulo) + '</h4>'
        + '  </div>'
        + '  <span class="status-pill is-' + GW.escapeHtml(item.estado) + '">' + GW.escapeHtml(estadoLabel(item)) + '</span>'
        + '</div>'
        + '<div class="mis-insc-info">'
        + '  <span><strong>Horario:</strong> ' + GW.escapeHtml(GW.formatDateTimeHuman(item.fecha_inicio))
        + ' – ' + GW.escapeHtml(GW.formatTime(item.fecha_fin)) + '</span>'
        + '  <span><strong>Sala:</strong> ' + GW.escapeHtml(salaTxt) + '</span>'
        + torneoInfo
        + equipoInfo
        + '</div>'
        + '<div class="mis-insc-bottom">'
        + '  <span>Código: <strong>' + GW.escapeHtml(item.codigo_acceso) + '</strong></span>'
        + cancelBtn
        + '</div>'
        + '</article>'
      );
    }).join('');
  }

  async function refreshMisInscripciones() {
    if (!GW.AppState.usuario) {
      GW.openLoginModal('Inicia sesión para consultar tus inscripciones.');
      return;
    }

    if (listEl) listEl.innerHTML = '<div class="programa-empty">Cargando tus inscripciones...</div>';
    var incluir = Boolean(toggleCanceladas && toggleCanceladas.checked);

    try {
      var lista = await GW.loadMisInscripciones(incluir);
      renderLista(lista);
    } catch (err) {
      if (err.payload && err.payload.requiere_autenticacion) {
        GW.AppState.usuario = null;
        GW.updateHeaderAuth();
        GW.openLoginModal('Tu sesión ha caducado. Inicia sesión de nuevo.');
        return;
      }
      GW.showAlert(feedbackEl, err.message, 'error');
    }
  }

  async function openMisInscripciones() {
    GW.hideAlert(feedbackEl);
    GW.openModal('modal-mis-inscripciones');
    await refreshMisInscripciones();
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-open-modal="mis-inscripciones"]');
    if (trigger) {
      openMisInscripciones();
    }
  });

  if (toggleCanceladas) {
    toggleCanceladas.addEventListener('change', refreshMisInscripciones);
  }

  if (listEl) {
    listEl.addEventListener('click', async function (e) {
      var btn = e.target.closest('.btn-cancelar-insc');
      if (!btn || btn.disabled) return;

      // Confirmación inline en dos clics para evitar bajas accidentales
      if (btn.dataset.confirming !== '1') {
        btn.dataset.confirming = '1';
        btn.textContent = '¿Confirmar baja? Pulsa de nuevo';
        setTimeout(function () {
          if (btn.isConnected && btn.dataset.confirming === '1' && !btn.disabled) {
            btn.dataset.confirming = '0';
            btn.textContent = 'Quitar inscripción';
          }
        }, 4000);
        return;
      }

      var inscId = Number(btn.getAttribute('data-insc-id'));
      btn.disabled = true;
      btn.textContent = 'Cancelando...';
      GW.hideAlert(feedbackEl);

      try {
        var res = await GW.apiPost('api/cancelar_inscripcion.php', { inscripcion_id: inscId });
        GW.showAlert(feedbackEl, res.mensaje, 'success');

        await Promise.all([
          refreshMisInscripciones(),
          GW.loadActividades().catch(function () { return []; }),
          GW.loadMisInscripciones(false).catch(function () { return []; }),
        ]);
      } catch (err) {
        GW.showAlert(feedbackEl, err.message, 'error');
        btn.disabled = false;
        btn.dataset.confirming = '0';
        btn.textContent = 'Quitar inscripción';
      }
    });
  }

  window.GW = Object.assign(GW, {
    openMisInscripciones: openMisInscripciones,
  });
})();
