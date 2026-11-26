// js/api.js
(function () {
  var AppState = {
    usuario: null,
    actividades: [],
    misInscripciones: [],
    pendingActivityId: null,
  };

  function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  async function requestJson(url, options) {
    var resp = await fetch(url, Object.assign({ credentials: 'same-origin' }, options || {}));
    var data = null;
    try {
      data = await resp.json();
    } catch (err) {
      data = { success: false, error: 'Respuesta inválida del servidor.' };
    }

    if (!resp.ok || !data || data.success === false) {
      var error = new Error((data && data.error) || 'Ha ocurrido un error inesperado.');
      error.status = resp.status;
      error.payload = data || {};
      throw error;
    }

    return data;
  }

  function apiGet(url) {
    return requestJson(url, {
      method: 'GET',
      headers: { Accept: 'application/json' },
    });
  }

  function apiPost(url, body) {
    return requestJson(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(body || {}),
    });
  }

  function emit(name, detail) {
    document.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
  }

  function openModal(modalId) {
    closeAllModals();
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('is-hidden');
    document.body.classList.add('modal-open');

    var firstInput = modal.querySelector('input:not([type="hidden"]):not([disabled]), button:not(.gw-modal-close)');
    if (firstInput) {
      setTimeout(function () {
        firstInput.focus();
      }, 30);
    }
  }

  function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('is-hidden');

    var anyOpen = document.querySelector('.gw-modal:not(.is-hidden)');
    if (!anyOpen) {
      document.body.classList.remove('modal-open');
    }
  }

  function closeAllModals() {
    document.querySelectorAll('.gw-modal').forEach(function (m) {
      m.classList.add('is-hidden');
    });
    document.body.classList.remove('modal-open');
  }

  function showAlert(el, message, type) {
    if (!el) return;
    el.classList.remove('is-hidden', 'is-error', 'is-success', 'is-warning');
    if (type === 'success') el.classList.add('is-success');
    else if (type === 'warning') el.classList.add('is-warning');
    else el.classList.add('is-error');
    el.textContent = message;
  }

  function hideAlert(el) {
    if (!el) return;
    el.classList.add('is-hidden');
    el.textContent = '';
  }

  function parseDateTime(sqlDateTime) {
    if (!sqlDateTime) return null;
    return new Date(String(sqlDateTime).replace(' ', 'T'));
  }

  function formatDayTab(dateKey) {
    var d = new Date(dateKey + 'T12:00:00');
    if (isNaN(d.getTime())) return dateKey;
    var label = d.toLocaleDateString('es-ES', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
    });
    return label.charAt(0).toUpperCase() + label.slice(1);
  }

  function formatDayHeading(dateKey) {
    var d = new Date(dateKey + 'T12:00:00');
    if (isNaN(d.getTime())) return dateKey;
    var label = d.toLocaleDateString('es-ES', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
    });
    return label.charAt(0).toUpperCase() + label.slice(1);
  }

  function formatTime(sqlDateTime) {
    return String(sqlDateTime || '').slice(11, 16);
  }

  function formatTimeRange(inicio, fin) {
    return formatTime(inicio) + ' – ' + formatTime(fin);
  }

  function formatDateTimeHuman(sqlDateTime) {
    var key = String(sqlDateTime || '').slice(0, 10);
    return formatDayTab(key) + ' · ' + formatTime(sqlDateTime);
  }

  function getDisplayName(usuario) {
    if (!usuario) return '';
    if (usuario.nickname && String(usuario.nickname).trim() !== '') {
      return String(usuario.nickname).trim();
    }
    return String(usuario.nombre || '').trim();
  }

  function findActividadById(id) {
    var targetId = Number(id);
    return AppState.actividades.find(function (a) {
      return Number(a.id) === targetId;
    }) || null;
  }

  async function loadActividades() {
    var data = await apiGet('api/actividades.php');
    AppState.actividades = Array.isArray(data.actividades) ? data.actividades : [];
    emit('gw:activities-updated', { actividades: AppState.actividades });
    return AppState.actividades;
  }

  async function loadMisInscripciones(incluirCanceladas) {
    if (!AppState.usuario) {
      AppState.misInscripciones = [];
      updateInscripcionesBadge();
      return [];
    }

    var query = incluirCanceladas ? '?incluir_canceladas=1' : '';
    var data = await apiGet('api/mis_inscripciones.php' + query);
    var lista = Array.isArray(data.inscripciones) ? data.inscripciones : [];
    if (!incluirCanceladas) {
      AppState.misInscripciones = lista;
      updateInscripcionesBadge();
    }
    return lista;
  }

  function updateInscripcionesBadge() {
    var badge = document.getElementById('badge-mis-inscripciones');
    if (!badge) return;
    var activas = AppState.misInscripciones.filter(function (i) {
      return i.estado !== 'cancelada';
    }).length;
    if (AppState.usuario && activas > 0) {
      badge.textContent = String(activas);
      badge.classList.remove('is-hidden');
    } else {
      badge.textContent = '0';
      badge.classList.add('is-hidden');
    }
  }

  function requestEnroll(actividadId) {
    var actividad = findActividadById(actividadId);
    if (!actividad) return;

    if (actividad.mi_inscripcion) {
      if (typeof window.GW.openMisInscripciones === 'function') {
        window.GW.openMisInscripciones();
      }
      return;
    }

    if (!AppState.usuario) {
      AppState.pendingActivityId = Number(actividad.id);
      if (typeof window.GW.openAuthForEnroll === 'function') {
        window.GW.openAuthForEnroll(actividad);
      }
      return;
    }

    if (typeof window.GW.openInscripcionModal === 'function') {
      window.GW.openInscripcionModal(actividad);
    }
  }

  // Cierre global de modales por backdrop, botón data-close-modal y Escape
  document.addEventListener('click', function (e) {
    var closer = e.target.closest('[data-close-modal]');
    if (closer) {
      var id = closer.getAttribute('data-close-modal');
      if (id === 'modal-login' || id === 'modal-register') {
        AppState.pendingActivityId = null;
      }
      closeModal(id);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var openModalEl = document.querySelector('.gw-modal:not(.is-hidden)');
      if (openModalEl) {
        if (openModalEl.id === 'modal-login' || openModalEl.id === 'modal-register') {
          AppState.pendingActivityId = null;
        }
        closeModal(openModalEl.id);
      }
    }
  });

  window.GW = Object.assign(window.GW || {}, {
    AppState: AppState,
    escapeHtml: escapeHtml,
    apiGet: apiGet,
    apiPost: apiPost,
    emit: emit,
    openModal: openModal,
    closeModal: closeModal,
    closeAllModals: closeAllModals,
    showAlert: showAlert,
    hideAlert: hideAlert,
    parseDateTime: parseDateTime,
    formatDayTab: formatDayTab,
    formatDayHeading: formatDayHeading,
    formatTime: formatTime,
    formatTimeRange: formatTimeRange,
    formatDateTimeHuman: formatDateTimeHuman,
    getDisplayName: getDisplayName,
    findActividadById: findActividadById,
    loadActividades: loadActividades,
    loadMisInscripciones: loadMisInscripciones,
    updateInscripcionesBadge: updateInscripcionesBadge,
    requestEnroll: requestEnroll,
  });
})();
