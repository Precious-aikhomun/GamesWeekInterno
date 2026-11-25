// js/programa.js
(function () {
  var GW = window.GW;

  var daysContainer = document.getElementById('programa-days');
  var filtersContainer = document.getElementById('programa-filters');
  var gridContainer = document.getElementById('programa-grid');
  var feedbackEl = document.getElementById('programa-feedback');

  var selectedDay = null;
  var selectedFilter = 'all';

  function getUniqueDays(actividades) {
    var seen = {};
    var days = [];
    actividades.forEach(function (act) {
      var key = String(act.fecha_inicio || '').slice(0, 10);
      if (key && !seen[key]) {
        seen[key] = true;
        days.push(key);
      }
    });
    return days.sort();
  }

  function getRooms(actividades) {
    var map = {};
    var list = [];
    actividades.forEach(function (act) {
      if (!act.sala) return;
      if (!map[act.sala.id]) {
        map[act.sala.id] = act.sala;
        list.push(act.sala);
      }
    });
    return list;
  }

  function matchesFilter(act) {
    if (selectedFilter === 'all') return true;
    if (selectedFilter === 'charla' || selectedFilter === 'taller' || selectedFilter === 'torneo') {
      return act.tipo === selectedFilter;
    }
    return act.tematica === selectedFilter;
  }

  function renderDayTabs(days) {
    if (!daysContainer) return;

    if (!selectedDay || (selectedDay !== 'all' && days.indexOf(selectedDay) === -1)) {
      selectedDay = days.length > 0 ? days[0] : 'all';
    }

    var html = days.map(function (dayKey) {
      var active = selectedDay === dayKey ? 'is-active' : '';
      return '<button type="button" class="pill-btn ' + active + '" data-day="' + GW.escapeHtml(dayKey) + '">'
        + GW.escapeHtml(GW.formatDayTab(dayKey)) + '</button>';
    }).join('');

    html += '<button type="button" class="pill-btn ' + (selectedDay === 'all' ? 'is-active' : '')
      + '" data-day="all">Todos los días</button>';

    daysContainer.innerHTML = html;
  }

  function renderFilterButtons() {
    if (!filtersContainer) return;
    filtersContainer.querySelectorAll('[data-filter-key]').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-filter-key') === selectedFilter);
    });
  }

  function buildCardHtml(act) {
    var miInsc = act.mi_inscripcion;
    var featured = act.datos_extra && act.datos_extra.destacada ? 'is-featured' : '';
    var enrolledCls = miInsc ? 'is-enrolled' : '';

    var badges = '';
    if (act.codigo_programa) {
      badges += '<span class="badge-tag">' + GW.escapeHtml(act.codigo_programa) + '</span>';
    }
    if (act.tematica) {
      badges += '<span class="badge-tag is-track">' + GW.escapeHtml(act.tematica) + '</span>';
    }
    if (act.tipo === 'torneo') {
      badges += '<span class="badge-tag is-torneo">Torneo</span>';
    }
    if (act.torneo && act.torneo.videojuego && act.torneo.videojuego.pegi > 3) {
      badges += '<span class="badge-tag is-pegi">PEGI ' + GW.escapeHtml(act.torneo.videojuego.pegi) + '</span>';
    }

    var ponentesTxt = '';
    if (Array.isArray(act.ponentes) && act.ponentes.length > 0) {
      ponentesTxt = '<div class="activity-meta">Con '
        + act.ponentes.map(function (p) { return GW.escapeHtml(p.nombre + ' ' + p.apellidos); }).join(', ')
        + '</div>';
    }

    var capacityTxt = '';
    var capacityCls = 'capacity-text';
    if (!act.requiere_inscripcion) {
      capacityTxt = 'Acceso libre';
    } else if (act.aforo_maximo === null) {
      capacityTxt = 'Sin límite de plazas';
    } else if (act.aforo_completo) {
      capacityTxt = 'Aforo completo (' + act.lista_espera_count + ' en espera)';
      capacityCls += ' is-full';
    } else {
      capacityTxt = act.plazas_disponibles + ' / ' + act.aforo_maximo + ' plazas';
    }

    var btnHtml = '';
    if (act.finalizada) {
      btnHtml = '<button type="button" class="btn-apuntarme" disabled>Finalizada</button>';
    } else if (!act.requiere_inscripcion) {
      btnHtml = '<button type="button" class="btn-apuntarme is-free" disabled>Entrada libre</button>';
    } else if (miInsc) {
      var cls = miInsc.estado === 'lista_espera' ? 'is-waitlist' : 'is-enrolled';
      var lbl = miInsc.estado === 'lista_espera' ? 'En espera' : '✓ Apuntado';
      btnHtml = '<button type="button" class="btn-apuntarme ' + cls + '" data-enroll-id="'
        + GW.escapeHtml(act.id) + '">' + lbl + '</button>';
    } else {
      var ctaLbl = act.aforo_completo ? 'Lista de espera' : 'Apuntarme';
      btnHtml = '<button type="button" class="btn-apuntarme" data-enroll-id="'
        + GW.escapeHtml(act.id) + '">' + ctaLbl + '</button>';
    }

    return (
      '<article class="activity-card ' + featured + ' ' + enrolledCls + '">'
      + '<div>'
      + '  <div class="activity-time">' + GW.escapeHtml(GW.formatTimeRange(act.fecha_inicio, act.fecha_fin)) + '</div>'
      + '  <div class="activity-badges">' + badges + '</div>'
      + '  <h4 class="activity-title">' + GW.escapeHtml(act.titulo) + '</h4>'
      + '  <p class="activity-desc">' + GW.escapeHtml(act.descripcion) + '</p>'
      + ponentesTxt
      + '</div>'
      + '<div class="activity-footer">'
      + '  <span class="' + capacityCls + '">' + GW.escapeHtml(capacityTxt) + '</span>'
      + btnHtml
      + '</div>'
      + '</article>'
    );
  }

  function renderDaySchedule(dayKey, actsDelDia, rooms) {
    // Usar solo las salas que tienen al menos 1 actividad ese día si hay filtro activo, o todas las salas del evento
    var activeRooms = rooms.filter(function (room) {
      return actsDelDia.some(function (a) { return a.sala && a.sala.id === room.id; });
    });
    if (activeRooms.length === 0) activeRooms = rooms;

    // Agrupar franjas horarias por hora de inicio
    var timeSlots = [];
    var byTimeRoom = {};

    actsDelDia.forEach(function (act) {
      var timeKey = GW.formatTime(act.fecha_inicio);
      var roomId = act.sala ? act.sala.id : 0;
      if (!byTimeRoom[timeKey]) {
        byTimeRoom[timeKey] = {};
        timeSlots.push(timeKey);
      }
      if (!byTimeRoom[timeKey][roomId]) {
        byTimeRoom[timeKey][roomId] = [];
      }
      byTimeRoom[timeKey][roomId].push(act);
    });

    timeSlots.sort();

    var colTemplate = '76px repeat(' + activeRooms.length + ', minmax(210px, 1fr))';
    var html = '<div class="programa-day-block">'
      + '<h3 class="programa-day-heading">' + GW.escapeHtml(GW.formatDayHeading(dayKey)) + '</h3>'
      + '<div class="schedule-table-wrap">'
      + '<div class="schedule-grid" style="grid-template-columns: ' + colTemplate + ';">'
      + '<div class="schedule-time-label">Hora</div>';

    activeRooms.forEach(function (room) {
      html += '<div class="schedule-col-header">'
        + '<strong>' + GW.escapeHtml(room.nombre) + '</strong>'
        + (room.ubicacion ? '<small>' + GW.escapeHtml(room.ubicacion) + '</small>' : '')
        + '</div>';
    });

    timeSlots.forEach(function (timeKey) {
      html += '<div class="schedule-time-label">' + GW.escapeHtml(timeKey) + '</div>';
      activeRooms.forEach(function (room) {
        var acts = (byTimeRoom[timeKey] && byTimeRoom[timeKey][room.id]) || [];
        if (acts.length === 0) {
          html += '<div class="schedule-empty-cell" aria-hidden="true"></div>';
        } else {
          html += '<div style="display:flex;flex-direction:column;gap:10px;">'
            + acts.map(buildCardHtml).join('')
            + '</div>';
        }
      });
    });

    html += '</div></div></div>';
    return html;
  }

  function renderPrograma() {
    if (!gridContainer) return;

    var allActs = GW.AppState.actividades;
    var allDays = getUniqueDays(allActs);
    renderDayTabs(allDays);
    renderFilterButtons();

    var filtered = allActs.filter(matchesFilter);
    if (selectedDay !== 'all') {
      filtered = filtered.filter(function (a) {
        return String(a.fecha_inicio || '').slice(0, 10) === selectedDay;
      });
    }

    if (filtered.length === 0) {
      gridContainer.innerHTML =
        '<div class="programa-empty">No hay actividades que coincidan con el día o filtro seleccionado.</div>';
      return;
    }

    var rooms = getRooms(allActs);
    var daysToShow = getUniqueDays(filtered);
    gridContainer.innerHTML = daysToShow.map(function (dayKey) {
      var dayActs = filtered.filter(function (a) {
        return String(a.fecha_inicio || '').slice(0, 10) === dayKey;
      });
      return renderDaySchedule(dayKey, dayActs, rooms);
    }).join('');
  }

  if (daysContainer) {
    daysContainer.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-day]');
      if (!btn) return;
      selectedDay = btn.getAttribute('data-day');
      renderPrograma();
    });
  }

  if (filtersContainer) {
    filtersContainer.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-filter-key]');
      if (!btn) return;
      selectedFilter = btn.getAttribute('data-filter-key');
      renderPrograma();
    });
  }

  if (gridContainer) {
    gridContainer.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-enroll-id]');
      if (!btn || btn.disabled) return;
      GW.requestEnroll(btn.getAttribute('data-enroll-id'));
    });
  }

  // Enlaces del submenú del header (Día Indie / Novedades) filtran el programa automáticamente
  document.addEventListener('click', function (e) {
    var link = e.target.closest('[data-programa-filter]');
    if (!link) return;
    selectedFilter = link.getAttribute('data-programa-filter') || 'all';
    selectedDay = 'all';
    renderPrograma();
  });

  document.addEventListener('gw:activities-updated', renderPrograma);

  document.addEventListener('DOMContentLoaded', async function () {
    try {
      await GW.loadActividades();
    } catch (err) {
      if (gridContainer) {
        gridContainer.innerHTML =
          '<div class="programa-empty">No se ha podido cargar el programa en este momento. Comprueba la conexión con la base de datos.</div>';
      }
      if (feedbackEl) {
        GW.showAlert(feedbackEl, err.message, 'error');
      }
    }
  });
})();
