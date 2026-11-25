// js/torneos.js
(function () {
  var GW = window.GW;

  var torneosGrid = document.getElementById('torneos-grid');
  var clasifTabs = document.getElementById('clasificacion-tabs');
  var clasifContent = document.getElementById('clasificacion-content');

  var selectedTorneoId = null;

  function renderTorneosCards(torneos) {
    if (!torneosGrid) return;

    if (torneos.length === 0) {
      torneosGrid.innerHTML = '<div class="programa-empty">No hay torneos disponibles todavía.</div>';
      return;
    }

    torneosGrid.innerHTML = torneos.map(function (act) {
      var info = act.torneo || {};
      var juego = info.videojuego || {};
      var miInsc = act.mi_inscripcion;
      var salaTxt = act.sala ? act.sala.nombre : 'Por confirmar';

      var capacityTxt = act.aforo_maximo === null
        ? 'Sin límite'
        : (act.aforo_completo
          ? 'Completo (' + act.lista_espera_count + ' en espera)'
          : act.plazas_disponibles + ' / ' + act.aforo_maximo + ' plazas');

      var btnHtml = '';
      if (act.finalizada) {
        btnHtml = '<button type="button" class="btn-apuntarme" disabled>Finalizado</button>';
      } else if (miInsc) {
        var cls = miInsc.estado === 'lista_espera' ? 'is-waitlist' : 'is-enrolled';
        var lbl = miInsc.estado === 'lista_espera' ? 'En espera' : '✓ Apuntado';
        btnHtml = '<button type="button" class="btn-apuntarme ' + cls + '" data-enroll-id="'
          + GW.escapeHtml(act.id) + '">' + lbl + '</button>';
      } else {
        btnHtml = '<button type="button" class="btn-apuntarme" data-enroll-id="'
          + GW.escapeHtml(act.id) + '">' + (act.aforo_completo ? 'Lista de espera' : 'Apuntarme') + '</button>';
      }

      return (
        '<article class="torneo-card ' + (miInsc ? 'is-enrolled' : '') + '">'
        + '<div>'
        + '  <div class="torneo-game-line">'
        + '    <span class="torneo-game-name">' + GW.escapeHtml(juego.nombre || 'Torneo')
        + (juego.plataforma ? ' · ' + GW.escapeHtml(juego.plataforma) : '') + '</span>'
        + (juego.pegi ? '<span class="badge-tag is-pegi">PEGI ' + GW.escapeHtml(juego.pegi) + '</span>' : '')
        + '  </div>'
        + '  <h3>' + GW.escapeHtml(act.titulo) + '</h3>'
        + '  <p class="activity-desc">' + GW.escapeHtml(act.descripcion) + '</p>'
        + '  <ul class="torneo-details">'
        + '    <li><strong>Fecha:</strong> ' + GW.escapeHtml(GW.formatDateTimeHuman(act.fecha_inicio))
        + ' – ' + GW.escapeHtml(GW.formatTime(act.fecha_fin)) + '</li>'
        + '    <li><strong>Sala:</strong> ' + GW.escapeHtml(salaTxt) + '</li>'
        + (info.formato ? '<li><strong>Formato:</strong> ' + GW.escapeHtml(info.formato) + '</li>' : '')
        + (info.premios ? '<li><strong>Premios:</strong> ' + GW.escapeHtml(info.premios) + '</li>' : '')
        + '  </ul>'
        + '</div>'
        + '<div class="torneo-card-footer">'
        + '  <span class="capacity-text ' + (act.aforo_completo ? 'is-full' : '') + '">' + GW.escapeHtml(capacityTxt) + '</span>'
        + btnHtml
        + '</div>'
        + '</article>'
      );
    }).join('');
  }

  function renderClasificacion(torneos) {
    if (!clasifTabs || !clasifContent) return;

    if (torneos.length === 0) {
      clasifTabs.innerHTML = '';
      clasifContent.innerHTML = '<div class="programa-empty">Sin cuadros publicados.</div>';
      return;
    }

    var existeSeleccionado = torneos.some(function (t) { return t.id === selectedTorneoId; });
    if (!existeSeleccionado) {
      selectedTorneoId = torneos[0].id;
    }

    clasifTabs.innerHTML = torneos.map(function (t) {
      var label = (t.torneo && t.torneo.videojuego && t.torneo.videojuego.nombre) || t.titulo;
      var active = t.id === selectedTorneoId ? 'is-active' : '';
      return '<button type="button" class="pill-btn ' + active + '" data-torneo-tab="'
        + GW.escapeHtml(t.id) + '">' + GW.escapeHtml(label) + '</button>';
    }).join('');

    var torneoActivo = torneos.find(function (t) { return t.id === selectedTorneoId; });
    var partidas = (torneoActivo && torneoActivo.torneo && Array.isArray(torneoActivo.torneo.partidas))
      ? torneoActivo.torneo.partidas
      : [];

    if (partidas.length === 0) {
      clasifContent.innerHTML =
        '<div class="programa-empty">Cuadro pendiente de sorteo. Las eliminatorias aparecerán al comenzar el torneo.</div>';
      return;
    }

    var rondasOrder = [];
    var porRonda = {};
    partidas.forEach(function (p) {
      if (!porRonda[p.ronda]) {
        porRonda[p.ronda] = [];
        rondasOrder.push(p.ronda);
      }
      porRonda[p.ronda].push(p);
    });

    clasifContent.innerHTML =
      '<div class="bracket-grid">'
      + rondasOrder.map(function (ronda) {
        var matchesHtml = porRonda[ronda].map(function (m) {
          var p1Name = m.participante_1 || 'Por determinar';
          var p2Name = m.participante_2 || 'Por determinar';
          var p1Win = m.ganador_inscripcion_id && m.ganador_inscripcion_id === m.inscripcion_1_id;
          var p2Win = m.ganador_inscripcion_id && m.ganador_inscripcion_id === m.inscripcion_2_id;
          var score1 = m.estado_partida === 'pendiente' && !m.participante_1 ? '-' : m.puntos_1;
          var score2 = m.estado_partida === 'pendiente' && !m.participante_2 ? '-' : m.puntos_2;

          return (
            '<div class="match-box ' + (m.estado_partida === 'en_juego' ? 'is-live' : '') + '">'
            + '<div class="match-player ' + (p1Win ? 'is-winner' : '') + (!m.participante_1 ? ' is-empty' : '') + '">'
            + '  <span>' + GW.escapeHtml(p1Name) + '</span>'
            + '  <span class="match-score">' + GW.escapeHtml(score1) + '</span>'
            + '</div>'
            + '<div class="match-player ' + (p2Win ? 'is-winner' : '') + (!m.participante_2 ? ' is-empty' : '') + '">'
            + '  <span>' + GW.escapeHtml(p2Name) + '</span>'
            + '  <span class="match-score">' + GW.escapeHtml(score2) + '</span>'
            + '</div>'
            + '</div>'
          );
        }).join('');

        return (
          '<div class="bracket-round">'
          + '<h4>' + GW.escapeHtml(ronda) + '</h4>'
          + matchesHtml
          + '</div>'
        );
      }).join('')
      + '</div>';
  }

  function renderAll() {
    var torneos = GW.AppState.actividades.filter(function (a) {
      return a.tipo === 'torneo';
    });
    renderTorneosCards(torneos);
    renderClasificacion(torneos);
  }

  if (torneosGrid) {
    torneosGrid.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-enroll-id]');
      if (!btn || btn.disabled) return;
      GW.requestEnroll(btn.getAttribute('data-enroll-id'));
    });
  }

  if (clasifTabs) {
    clasifTabs.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-torneo-tab]');
      if (!btn) return;
      selectedTorneoId = Number(btn.getAttribute('data-torneo-tab'));
      renderAll();
    });
  }

  document.addEventListener('gw:activities-updated', renderAll);
})();
