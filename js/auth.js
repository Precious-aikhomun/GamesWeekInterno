// js/auth.js
(function () {
  var GW = window.GW;

  var btnOpenLogin = document.getElementById('btn-open-login');
  var userMenu = document.getElementById('user-menu');
  var userAvatarInitial = document.getElementById('user-avatar-initial');
  var userDisplayName = document.getElementById('user-display-name');
  var userDropdownNick = document.getElementById('user-dropdown-nick');
  var userDropdownFullname = document.getElementById('user-dropdown-fullname');
  var userDropdownEmail = document.getElementById('user-dropdown-email');
  var btnLogout = document.getElementById('btn-logout');

  var formLogin = document.getElementById('form-login');
  var loginError = document.getElementById('login-error');
  var loginContextMsg = document.getElementById('login-context-msg');
  var btnSubmitLogin = document.getElementById('btn-submit-login');

  var formRegister = document.getElementById('form-register');
  var registerError = document.getElementById('register-error');
  var registerContextMsg = document.getElementById('register-context-msg');
  var btnSubmitRegister = document.getElementById('btn-submit-register');
  var regFechaInput = document.getElementById('reg-fecha');

  var btnSwitchToRegister = document.getElementById('btn-switch-to-register');
  var btnSwitchToLogin = document.getElementById('btn-switch-to-login');

  if (regFechaInput) {
    regFechaInput.max = new Date().toISOString().slice(0, 10);
  }

  function updateHeaderAuth() {
    var user = GW.AppState.usuario;

    if (!user) {
      if (btnOpenLogin) btnOpenLogin.classList.remove('is-hidden');
      if (userMenu) {
        userMenu.classList.add('is-hidden');
        userMenu.classList.remove('is-open');
      }
      GW.updateInscripcionesBadge();
      return;
    }

    if (btnOpenLogin) btnOpenLogin.classList.add('is-hidden');
    if (userMenu) userMenu.classList.remove('is-hidden');

    // Priorizar nickname si el usuario ya dispone de uno
    var displayName = GW.getDisplayName(user);
    if (userDisplayName) userDisplayName.textContent = displayName;
    if (userAvatarInitial) userAvatarInitial.textContent = displayName.charAt(0).toUpperCase() || 'U';

    if (userDropdownNick) {
      if (user.nickname && String(user.nickname).trim() !== '') {
        userDropdownNick.textContent = '@' + user.nickname.trim();
        userDropdownNick.classList.remove('is-hidden');
      } else {
        userDropdownNick.textContent = '';
        userDropdownNick.classList.add('is-hidden');
      }
    }

    if (userDropdownFullname) {
      userDropdownFullname.textContent = (user.nombre + ' ' + user.apellidos).trim();
    }
    if (userDropdownEmail) {
      userDropdownEmail.textContent = user.email || '';
    }
  }

  function setContextMessage(text) {
    [loginContextMsg, registerContextMsg].forEach(function (el) {
      if (!el) return;
      if (text) {
        el.textContent = text;
        el.classList.remove('is-hidden');
      } else {
        el.textContent = '';
        el.classList.add('is-hidden');
      }
    });
  }

  function openLoginModal(contextText) {
    GW.hideAlert(loginError);
    setContextMessage(contextText || '');
    GW.openModal('modal-login');
  }

  function openRegisterModal(contextText) {
    GW.hideAlert(registerError);
    setContextMessage(contextText || '');
    GW.openModal('modal-register');
  }

  function openAuthForEnroll(actividad) {
    var msg = actividad
      ? 'Inicia sesión o regístrate para apuntarte a "' + actividad.titulo + '".'
      : 'Inicia sesión o regístrate para completar tu inscripción.';
    openLoginModal(msg);
  }

  async function handlePostAuthSuccess(usuario) {
    GW.AppState.usuario = usuario;
    updateHeaderAuth();
    GW.closeAllModals();
    setContextMessage('');

    await Promise.all([
      GW.loadActividades().catch(function () { return []; }),
      GW.loadMisInscripciones(false).catch(function () { return []; }),
    ]);

    GW.emit('gw:auth-changed', { usuario: usuario });

    if (GW.AppState.pendingActivityId) {
      var pendingId = GW.AppState.pendingActivityId;
      GW.AppState.pendingActivityId = null;
      var actividad = GW.findActividadById(pendingId);
      if (actividad && !actividad.mi_inscripcion && typeof GW.openInscripcionModal === 'function') {
        GW.openInscripcionModal(actividad);
      } else if (actividad && actividad.mi_inscripcion && typeof GW.openMisInscripciones === 'function') {
        GW.openMisInscripciones();
      }
    }
  }

  if (btnOpenLogin) {
    btnOpenLogin.addEventListener('click', function () {
      GW.AppState.pendingActivityId = null;
      openLoginModal('');
    });
  }

  if (btnSwitchToRegister) {
    btnSwitchToRegister.addEventListener('click', function () {
      var msg = loginContextMsg && !loginContextMsg.classList.contains('is-hidden')
        ? loginContextMsg.textContent
        : '';
      openRegisterModal(msg);
    });
  }

  if (btnSwitchToLogin) {
    btnSwitchToLogin.addEventListener('click', function () {
      var msg = registerContextMsg && !registerContextMsg.classList.contains('is-hidden')
        ? registerContextMsg.textContent
        : '';
      openLoginModal(msg);
    });
  }

  // Botones Ver / Ocultar contraseña
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-toggle-pass]');
    if (!toggle) return;
    var input = document.getElementById(toggle.getAttribute('data-toggle-pass'));
    if (!input) return;
    var showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    toggle.textContent = showing ? 'Ver' : 'Ocultar';
  });

  if (formLogin) {
    formLogin.addEventListener('submit', async function (e) {
      e.preventDefault();
      GW.hideAlert(loginError);

      var email = document.getElementById('login-email').value.trim();
      var password = document.getElementById('login-password').value;

      if (!email || !password) {
        GW.showAlert(loginError, 'Introduce tu correo electrónico y contraseña.', 'error');
        return;
      }

      btnSubmitLogin.disabled = true;
      btnSubmitLogin.textContent = 'Entrando...';

      try {
        var res = await GW.apiPost('api/auth.php?action=login', {
          email: email,
          password: password,
        });
        formLogin.reset();
        await handlePostAuthSuccess(res.usuario);
      } catch (err) {
        GW.showAlert(loginError, err.message, 'error');
      } finally {
        btnSubmitLogin.disabled = false;
        btnSubmitLogin.textContent = 'Entrar';
      }
    });
  }

  if (formRegister) {
    formRegister.addEventListener('submit', async function (e) {
      e.preventDefault();
      GW.hideAlert(registerError);

      var payload = {
        nombre: document.getElementById('reg-nombre').value.trim(),
        apellidos: document.getElementById('reg-apellidos').value.trim(),
        email: document.getElementById('reg-email').value.trim(),
        fecha_nacimiento: document.getElementById('reg-fecha').value.trim(),
        telefono: document.getElementById('reg-telefono').value.trim(),
        password: document.getElementById('reg-password').value,
      };
      var privacidad = document.getElementById('reg-privacidad').checked;

      if (!payload.nombre || !payload.apellidos || !payload.email || !payload.fecha_nacimiento || !payload.password) {
        GW.showAlert(registerError, 'Completa todos los campos obligatorios.', 'error');
        return;
      }
      if (!privacidad) {
        GW.showAlert(registerError, 'Debes aceptar la Política de Privacidad para registrarte.', 'error');
        return;
      }

      btnSubmitRegister.disabled = true;
      btnSubmitRegister.textContent = 'Creando cuenta...';

      try {
        var res = await GW.apiPost('api/auth.php?action=register', payload);
        formRegister.reset();
        await handlePostAuthSuccess(res.usuario);
      } catch (err) {
        GW.showAlert(registerError, err.message, 'error');
      } finally {
        btnSubmitRegister.disabled = false;
        btnSubmitRegister.textContent = 'Crear cuenta';
      }
    });
  }

  if (btnLogout) {
    btnLogout.addEventListener('click', async function () {
      try {
        await GW.apiPost('api/auth.php?action=logout', {});
      } catch (err) {
        // Limpiar estado local incluso si la sesión ya había caducado
      }
      GW.AppState.usuario = null;
      GW.AppState.misInscripciones = [];
      GW.AppState.pendingActivityId = null;
      updateHeaderAuth();
      GW.closeAllModals();
      await GW.loadActividades().catch(function () { return []; });
      GW.emit('gw:auth-changed', { usuario: null });
    });
  }

  async function initSession() {
    try {
      var res = await GW.apiGet('api/auth.php?action=status');
      GW.AppState.usuario = res.authenticated ? res.usuario : null;
      updateHeaderAuth();
      if (GW.AppState.usuario) {
        await GW.loadMisInscripciones(false).catch(function () { return []; });
      }
    } catch (err) {
      GW.AppState.usuario = null;
      updateHeaderAuth();
    }
    GW.emit('gw:auth-changed', { usuario: GW.AppState.usuario });
  }

  window.GW = Object.assign(GW, {
    updateHeaderAuth: updateHeaderAuth,
    openLoginModal: openLoginModal,
    openRegisterModal: openRegisterModal,
    openAuthForEnroll: openAuthForEnroll,
  });

  document.addEventListener('DOMContentLoaded', initSession);
})();
