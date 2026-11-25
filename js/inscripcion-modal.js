// js/inscripcion-modal.js
(function () {
  var GW = window.GW;

  var summaryEl = document.getElementById('insc-summary');
  var overlapWarningEl = document.getElementById('insc-overlap-warning');
  var formEl = document.getElementById('form-inscripcion');
  var inputActId = document.getElementById('insc-actividad-id');
  var torneoFields = document.getElementById('insc-torneo-fields');
  var inputNickname = document.getElementById('insc-nickname');
  var nicknameHint = document.getElementById('insc-nickname-hint');
  var equipoGroup = document.getElementById('insc-equipo-group');
  var inputEquipo = document.getElementById('insc-equipo');
  var errorEl = document.getElementById('insc-error');
  var successEl = document.getElementById('insc-success');
  var actionsPending = document.getElementById('insc-actions-pending');
  var actionsDone = document.getElementById('insc-actions-done');
  var btnSubmit = document.getElementById('btn-submit-inscripcion');
  var btnVerMias = document.getElementById('btn-insc-ver-mias');

  var currentActividad = null;

  function tipoLabel(tipo) {
    var map = {
      charla: 'Charla',
      taller: 'Taller',
      torneo: 'Torneo',
      actividad_libre: 'Acceso libre',
    };
    return map[tipo] || tipo;
  }

  function findOverlappingActivities(actividad) {
    var start = GW.parseDateTime(actividad.fecha_inicio);
    var end = GW.parseDateTime(actividad.fecha_fin);
    if (!start || !end) return [];

    return GW.AppState.actividades.filter(function (otra) {
      if (otra.id === actividad.id || !otra.mi_inscripcion) return false;
      var oStart = GW.parseDateTime(otra.fecha_inicio);
      var oEnd = GW.parseDateTime(otra.fecha_fin);
      if (!oStart || !oEnd) return false;
      return oStart < end && oEnd > start;
    });
  }

  function renderSummary(actividad) {
    if (!summaryEl) return;

    var badges = '';
    if (actividad.codigo_programa) {
      badges += '<span class="badge-tag">' + GW.escapeHtml(actividad.codigo_programa) + '</span>';
    }
    badges += '<span class="badge-tag ' + (actividad.tipo === 'torneo' ? 'is-torneo' : '') + '">'
      + GW.escapeHtml(tipoLabel(actividad.tipo)) + '</span>';

    if (actividad.torneo && actividad.torneo.videojuego && actividad.torneo.videojuego.pegi) {
      badges += '<span class="badge-tag is-pegi">PEGI ' + GW.escapeHtml(actividad.torneo.videojuego.pegi) + '</span>';
    }

    var salaTxt = actividad.sala
      ? actividad.sala.nombre + (actividad.sala.ubicacion ? ' (' + actividad.sala.ubicacion + ')' : '')
      : 'Por confirmar';

    var plazasHtml = '';
    if (actividad.aforo_maximo === null) {
      plazasHtml = '<strong>Plazas:</strong> Aforo ilimitado';
    } else if (actividad.aforo_completo) {
      plazasHtml = '<strong style="color: var(--yellow);">Estado:</strong> Aforo completo — entrarás en lista de espera ('
        + GW.escapeHtml(actividad.lista_espera_count) + ' en cola)';
    } else {
      plazasHtml = '<strong>Plazas disponibles:</strong> '
        + GW.escapeHtml(actividad.plazas_disponibles) + ' de ' + GW.escapeHtml(actividad.aforo_maximo);
    }

    var torneoExtra = '';
    if (actividad.torneo) {
      torneoExtra =
        '<div><strong>Juego:</strong> ' + GW.escapeHtml(actividad.torneo.videojuego.nombre)
        + ' (' + GW.escapeHtml(actividad.torneo.videojuego.plataforma) + ')</div>'
        + '<div><strong>Formato:</strong> ' + GW.escapeHtml(actividad.torneo.formato) + '</div>';
    }

    summaryEl.innerHTML =
      '<div class="activity-badges">' + badges + '</div>'
      + '<h3>' + GW.escapeHtml(actividad.titulo) + '</h3>'
      + '<div class="insc-summary-meta">'
      + '<div><strong>Horario:</strong> ' + GW.escapeHtml(GW.formatDayTab(String(actividad.fecha_inicio).slice(0, 10)))
      + ' · ' + GW.escapeHtml(GW.formatTimeRange(actividad.fecha_inicio, actividad.fecha_fin)) + '</div>'
      + '<div><strong>Sala:</strong> ' + GW.escapeHtml(salaTxt) + '</div>'
      + '<div>' + plazasHtml + '</div>'
      + torneoExtra
      + '</div>';
  }

  function openInscripcionModal(actividad) {
    if (!actividad) return;
    currentActividad = actividad;

    GW.hideAlert(errorEl);
    GW.hideAlert(successEl);
    GW.hideAlert(overlapWarningEl);
    if (successEl) successEl.innerHTML = '';

    if (inputActId) inputActId.value = String(actividad.id);
    renderSummary(actividad);

    var solapadas = findOverlappingActivities(actividad);
    if (solapadas.length > 0) {
      var nombres = solapadas.map(function (s) {
        return '"' + s.titulo + '" (' + GW.formatTimeRange(s.fecha_inicio, s.fecha_fin) + ')';
      }).join(', ');
      GW.showAlert(
        overlapWarningEl,
        'Aviso: esta actividad coincide en horario con ' + nombres + '.',
        'warning'
      );
    }

    var esTorneo = actividad.tipo === 'torneo';
    if (torneoFields) {
      torneoFields.classList.toggle('is-hidden', !esTorneo);
    }

    if (esTorneo && inputNickname) {
      var nickActual = (GW.AppState.usuario && GW.AppState.usuario.nickname) ? GW.AppState.usuario.nickname : '';
      inputNickname.value = nickActual;
      inputNickname.required = true;
      if (nicknameHint) {
        nicknameHint.textContent = nickActual
          ? 'Ya tienes guardado este Gamer Tag; puedes mantenerlo o editarlo antes de confirmar.'
          : 'Obligatorio para participar en torneos y personalizar tu perfil.';
      }
    } else if (inputNickname) {
      inputNickname.value = '';
      inputNickname.required = false;
    }

    var porEquipos = Boolean(actividad.datos_extra && actividad.datos_extra.por_equipos);
    if (equipoGroup && inputEquipo) {
      equipoGroup.classList.toggle('is-hidden', !(esTorneo && porEquipos));
      inputEquipo.value = '';
    }

    if (actionsPending) actionsPending.classList.remove('is-hidden');
    if (actionsDone) actionsDone.classList.add('is-hidden');

    if (btnSubmit) {
      btnSubmit.disabled = false;
      btnSubmit.textContent = actividad.aforo_completo ? 'Apuntarme a lista de espera' : 'Confirmar inscripción';
    }

    GW.openModal('modal-inscripcion');
  }

  if (formEl) {
    formEl.addEventListener('submit', async function (e) {
      e.preventDefault();
      if (!currentActividad) return;

      GW.hideAlert(errorEl);
      GW.hideAlert(successEl);

      var payload = {
        actividad_id: Number(currentActividad.id),
      };

      if (currentActividad.tipo === 'torneo') {
        var nick = inputNickname ? inputNickname.value.trim() : '';
        if (!nick) {
          GW.showAlert(errorEl, 'Necesitas indicar tu Nickname / Gamer Tag para apuntarte a un torneo.', 'error');
          if (inputNickname) inputNickname.focus();
          return;
        }
        payload.nickname = nick;
        if (inputEquipo && !equipoGroup.classList.contains('is-hidden')) {
          payload.nombre_equipo = inputEquipo.value.trim();
        }
      }

      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Procesando...';

      try {
        var res = await GW.apiPost('api/inscripcion.php', payload);

        if (res.nickname && GW.AppState.usuario) {
          GW.AppState.usuario.nickname = res.nickname;
          GW.updateHeaderAuth();
        }

        if (successEl) {
          successEl.classList.remove('is-hidden', 'is-error', 'is-warning');
          successEl.classList.add('is-success');
          var badgeEstado = res.estado === 'lista_espera'
            ? 'Lista de espera (#' + GW.escapeHtml(res.posicion_lista_espera || '-') + ')'
            : 'Plaza confirmada';
          successEl.innerHTML =
            '<strong>' + GW.escapeHtml(res.mensaje) + '</strong>'
            + '<div class="insc-ticket">'
            + '<span>' + badgeEstado + ' · Código de acceso:</span>'
            + '<code>' + GW.escapeHtml(res.codigo_acceso) + '</code>'
            + '</div>';
        }

        if (actionsPending) actionsPending.classList.add('is-hidden');
        if (actionsDone) actionsDone.classList.remove('is-hidden');

        await Promise.all([
          GW.loadActividades().catch(function () { return []; }),
          GW.loadMisInscripciones(false).catch(function () { return []; }),
        ]);
      } catch (err) {
        if (err.payload && err.payload.requiere_autenticacion) {
          GW.AppState.usuario = null;
          GW.AppState.pendingActivityId = Number(currentActividad.id);
          GW.updateHeaderAuth();
          GW.openAuthForEnroll(currentActividad);
          return;
        }
        GW.showAlert(errorEl, err.message, 'error');
        btnSubmit.disabled = false;
        btnSubmit.textContent = currentActividad.aforo_completo
          ? 'Apuntarme a lista de espera'
          : 'Confirmar inscripción';
      }
    });
  }

  if (btnVerMias) {
    btnVerMias.addEventListener('click', function () {
      if (typeof GW.openMisInscripciones === 'function') {
        GW.openMisInscripciones();
      }
    });
  }

  window.GW = Object.assign(GW, {
    openInscripcionModal: openInscripcionModal,
  });
})();
