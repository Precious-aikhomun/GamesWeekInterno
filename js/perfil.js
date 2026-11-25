// js/perfil.js
(function () {
  var GW = window.GW;

  var formPerfil = document.getElementById('form-perfil');
  var perfilFeedback = document.getElementById('perfil-feedback');
  var btnSubmitPerfil = document.getElementById('btn-submit-perfil');
  var perfilFechaInput = document.getElementById('perfil-fecha');

  var dangerZone = document.getElementById('perfil-danger-zone');
  var formDeleteAccount = document.getElementById('form-delete-account');
  var deleteError = document.getElementById('delete-account-error');
  var btnDeleteAccount = document.getElementById('btn-delete-account');

  if (perfilFechaInput) {
    perfilFechaInput.max = new Date().toISOString().slice(0, 10);
  }

  function populatePerfilForm() {
    var user = GW.AppState.usuario;
    if (!user) return;

    document.getElementById('perfil-nickname').value = user.nickname || '';
    document.getElementById('perfil-nombre').value = user.nombre || '';
    document.getElementById('perfil-apellidos').value = user.apellidos || '';
    document.getElementById('perfil-email').value = user.email || '';
    document.getElementById('perfil-fecha').value = user.fecha_nacimiento || '';
    document.getElementById('perfil-telefono').value = user.telefono || '';
    document.getElementById('perfil-pass-actual').value = '';
    document.getElementById('perfil-pass-nueva').value = '';
    document.getElementById('delete-account-password').value = '';
  }

  function openPerfilModal() {
    if (!GW.AppState.usuario) {
      GW.openLoginModal('Inicia sesión para consultar o editar tu perfil.');
      return;
    }

    GW.hideAlert(perfilFeedback);
    GW.hideAlert(deleteError);
    if (dangerZone) dangerZone.open = false;
    populatePerfilForm();
    GW.openModal('modal-perfil');
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-open-modal="perfil"]');
    if (trigger) {
      openPerfilModal();
    }
  });

  if (formPerfil) {
    formPerfil.addEventListener('submit', async function (e) {
      e.preventDefault();
      GW.hideAlert(perfilFeedback);

      var payload = {
        nickname: document.getElementById('perfil-nickname').value.trim(),
        nombre: document.getElementById('perfil-nombre').value.trim(),
        apellidos: document.getElementById('perfil-apellidos').value.trim(),
        email: document.getElementById('perfil-email').value.trim(),
        fecha_nacimiento: document.getElementById('perfil-fecha').value.trim(),
        telefono: document.getElementById('perfil-telefono').value.trim(),
        password_actual: document.getElementById('perfil-pass-actual').value,
        password_nueva: document.getElementById('perfil-pass-nueva').value,
      };

      btnSubmitPerfil.disabled = true;
      btnSubmitPerfil.textContent = 'Guardando...';

      try {
        var res = await GW.apiPost('api/auth.php?action=update_profile', payload);
        GW.AppState.usuario = res.usuario;
        GW.updateHeaderAuth();
        populatePerfilForm();
        GW.showAlert(perfilFeedback, res.mensaje, 'success');
        await GW.loadActividades().catch(function () { return []; });
        GW.emit('gw:auth-changed', { usuario: res.usuario });
      } catch (err) {
        GW.showAlert(perfilFeedback, err.message, 'error');
      } finally {
        btnSubmitPerfil.disabled = false;
        btnSubmitPerfil.textContent = 'Guardar cambios';
      }
    });
  }

  if (formDeleteAccount) {
    formDeleteAccount.addEventListener('submit', async function (e) {
      e.preventDefault();
      GW.hideAlert(deleteError);

      var password = document.getElementById('delete-account-password').value;
      if (!password) {
        GW.showAlert(deleteError, 'Introduce tu contraseña actual para confirmar.', 'error');
        return;
      }

      btnDeleteAccount.disabled = true;
      btnDeleteAccount.textContent = 'Eliminando...';

      try {
        var res = await GW.apiPost('api/auth.php?action=delete_account', { password: password });
        GW.AppState.usuario = null;
        GW.AppState.misInscripciones = [];
        GW.AppState.pendingActivityId = null;
        GW.updateHeaderAuth();
        GW.closeAllModals();

        var progFeedback = document.getElementById('programa-feedback');
        if (progFeedback) {
          progFeedback.classList.remove('is-hidden', 'is-error');
          progFeedback.classList.add('is-success');
          progFeedback.textContent = res.mensaje;
        }

        await GW.loadActividades().catch(function () { return []; });
        GW.emit('gw:auth-changed', { usuario: null });
      } catch (err) {
        GW.showAlert(deleteError, err.message, 'error');
      } finally {
        btnDeleteAccount.disabled = false;
        btnDeleteAccount.textContent = 'Eliminar cuenta';
      }
    });
  }

  window.GW = Object.assign(GW, {
    openPerfilModal: openPerfilModal,
  });
})();
