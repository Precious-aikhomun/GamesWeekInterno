<!-- includes/modals.php -->

<!-- 1. MODAL INICIAR SESIÓN -->
<div class="gw-modal is-hidden" id="modal-login" role="dialog" aria-modal="true" aria-labelledby="title-modal-login">
  <div class="gw-modal-backdrop" data-close-modal="modal-login"></div>
  <div class="gw-modal-dialog">
    <div class="gw-modal-header">
      <h2 id="title-modal-login">Iniciar sesión</h2>
      <button type="button" class="gw-modal-close" data-close-modal="modal-login" aria-label="Cerrar ventana">&times;</button>
    </div>

    <p class="gw-modal-context is-hidden" id="login-context-msg"></p>

    <form class="gw-form" id="form-login" novalidate>
      <div class="gw-field">
        <label for="login-email">Correo electrónico</label>
        <input type="email" id="login-email" name="email" autocomplete="email" required placeholder="tu@correo.com">
      </div>

      <div class="gw-field">
        <label for="login-password">Contraseña</label>
        <div class="password-wrap">
          <input type="password" id="login-password" name="password" autocomplete="current-password" required placeholder="Tu contraseña">
          <button type="button" class="btn-toggle-pass" data-toggle-pass="login-password">Ver</button>
        </div>
      </div>

      <div class="gw-alert is-error is-hidden" id="login-error" role="alert" aria-live="polite"></div>

      <div class="gw-modal-actions">
        <button type="submit" class="btn-primary-modal" id="btn-submit-login">Entrar</button>
      </div>
    </form>

    <div class="gw-modal-footer-switch">
      ¿Todavía no tienes cuenta?
      <button type="button" class="link-btn" id="btn-switch-to-register">Regístrate aquí</button>
    </div>
  </div>
</div>

<!-- 2. MODAL REGISTRO -->
<div class="gw-modal is-hidden" id="modal-register" role="dialog" aria-modal="true" aria-labelledby="title-modal-register">
  <div class="gw-modal-backdrop" data-close-modal="modal-register"></div>
  <div class="gw-modal-dialog">
    <div class="gw-modal-header">
      <h2 id="title-modal-register">Crear cuenta</h2>
      <button type="button" class="gw-modal-close" data-close-modal="modal-register" aria-label="Cerrar ventana">&times;</button>
    </div>

    <p class="gw-modal-context is-hidden" id="register-context-msg"></p>

    <form class="gw-form" id="form-register" novalidate>
      <div class="gw-form-row">
        <div class="gw-field">
          <label for="reg-nombre">Nombre *</label>
          <input type="text" id="reg-nombre" name="nombre" maxlength="80" autocomplete="given-name" required placeholder="Ej. Lucía">
        </div>
        <div class="gw-field">
          <label for="reg-apellidos">Apellidos *</label>
          <input type="text" id="reg-apellidos" name="apellidos" maxlength="120" autocomplete="family-name" required placeholder="Ej. Gómez Ruiz">
        </div>
      </div>

      <div class="gw-field">
        <label for="reg-email">Correo electrónico *</label>
        <input type="email" id="reg-email" name="email" maxlength="150" autocomplete="email" required placeholder="tu@correo.com">
      </div>

      <div class="gw-form-row">
        <div class="gw-field">
          <label for="reg-fecha">Fecha de nacimiento *</label>
          <input type="date" id="reg-fecha" name="fecha_nacimiento" min="1900-01-01" required>
          <small>Necesaria para validar edad PEGI en torneos.</small>
        </div>
        <div class="gw-field">
          <label for="reg-telefono">Teléfono (opcional)</label>
          <input type="tel" id="reg-telefono" name="telefono" maxlength="25" autocomplete="tel" placeholder="600 000 000">
        </div>
      </div>

      <div class="gw-field">
        <label for="reg-password">Contraseña *</label>
        <div class="password-wrap">
          <input type="password" id="reg-password" name="password" minlength="6" autocomplete="new-password" required placeholder="Mínimo 6 caracteres">
          <button type="button" class="btn-toggle-pass" data-toggle-pass="reg-password">Ver</button>
        </div>
      </div>

      <label class="gw-checkbox">
        <input type="checkbox" id="reg-privacidad" required>
        <span>He leído y acepto la Política de Privacidad para gestionar mis inscripciones en Game Week 2027.</span>
      </label>

      <div class="gw-alert is-error is-hidden" id="register-error" role="alert" aria-live="polite"></div>

      <div class="gw-modal-actions">
        <button type="submit" class="btn-primary-modal" id="btn-submit-register">Crear cuenta</button>
      </div>
    </form>

    <div class="gw-modal-footer-switch">
      ¿Ya tienes una cuenta?
      <button type="button" class="link-btn" id="btn-switch-to-login">Inicia sesión</button>
    </div>
  </div>
</div>

<!-- 3. MODAL CONFIRMACIÓN DE INSCRIPCIÓN A ACTIVIDAD -->
<div class="gw-modal is-hidden" id="modal-inscripcion" role="dialog" aria-modal="true" aria-labelledby="title-modal-inscripcion">
  <div class="gw-modal-backdrop" data-close-modal="modal-inscripcion"></div>
  <div class="gw-modal-dialog">
    <div class="gw-modal-header">
      <h2 id="title-modal-inscripcion">Confirmar inscripción</h2>
      <button type="button" class="gw-modal-close" data-close-modal="modal-inscripcion" aria-label="Cerrar ventana">&times;</button>
    </div>

    <div class="insc-summary-card" id="insc-summary"></div>

    <div class="gw-alert is-warning is-hidden" id="insc-overlap-warning" role="status"></div>

    <form class="gw-form" id="form-inscripcion" novalidate>
      <input type="hidden" id="insc-actividad-id" name="actividad_id">

      <div class="is-hidden" id="insc-torneo-fields">
        <div class="gw-field">
          <label for="insc-nickname">Nickname / Gamer Tag *</label>
          <input type="text" id="insc-nickname" name="nickname" maxlength="50" placeholder="Ej. ShadowK">
          <small id="insc-nickname-hint">Obligatorio para participar en torneos y mostrarse en tu perfil.</small>
        </div>

        <div class="gw-field is-hidden" id="insc-equipo-group" style="margin-top: 12px;">
          <label for="insc-equipo">Nombre del equipo (opcional)</label>
          <input type="text" id="insc-equipo" name="nombre_equipo" maxlength="100" placeholder="Ej. CCC Legends">
          <small>Indica el nombre de tu equipo si compites con escuadra.</small>
        </div>
      </div>

      <div class="gw-alert is-error is-hidden" id="insc-error" role="alert" aria-live="polite"></div>
      <div class="gw-alert is-success is-hidden" id="insc-success" role="status" aria-live="polite"></div>

      <div class="gw-modal-actions" id="insc-actions-pending">
        <button type="button" class="btn-secondary-modal" data-close-modal="modal-inscripcion">Cancelar</button>
        <button type="submit" class="btn-primary-modal" id="btn-submit-inscripcion">Confirmar inscripción</button>
      </div>

      <div class="gw-modal-actions is-hidden" id="insc-actions-done">
        <button type="button" class="btn-secondary-modal" id="btn-insc-ver-mias">Ver mis inscripciones</button>
        <button type="button" class="btn-primary-modal" data-close-modal="modal-inscripcion">Listo</button>
      </div>
    </form>
  </div>
</div>

<!-- 4. MODAL MIS INSCRIPCIONES -->
<div class="gw-modal is-hidden" id="modal-mis-inscripciones" role="dialog" aria-modal="true" aria-labelledby="title-modal-mis-insc">
  <div class="gw-modal-backdrop" data-close-modal="modal-mis-inscripciones"></div>
  <div class="gw-modal-dialog is-wide">
    <div class="gw-modal-header">
      <h2 id="title-modal-mis-insc">Mis inscripciones</h2>
      <button type="button" class="gw-modal-close" data-close-modal="modal-mis-inscripciones" aria-label="Cerrar ventana">&times;</button>
    </div>

    <div class="mis-insc-toolbar">
      <span class="capacity-text" id="mis-insc-count">Cargando...</span>
      <label class="gw-checkbox">
        <input type="checkbox" id="toggle-canceladas">
        <span>Mostrar canceladas</span>
      </label>
    </div>

    <div class="gw-alert is-hidden" id="mis-insc-feedback" role="status" aria-live="polite" style="margin-bottom: 12px;"></div>

    <div class="mis-insc-list" id="mis-inscripciones-list"></div>
  </div>
</div>

<!-- 5. MODAL MI PERFIL / CUENTA -->
<div class="gw-modal is-hidden" id="modal-perfil" role="dialog" aria-modal="true" aria-labelledby="title-modal-perfil">
  <div class="gw-modal-backdrop" data-close-modal="modal-perfil"></div>
  <div class="gw-modal-dialog">
    <div class="gw-modal-header">
      <h2 id="title-modal-perfil">Mi perfil</h2>
      <button type="button" class="gw-modal-close" data-close-modal="modal-perfil" aria-label="Cerrar ventana">&times;</button>
    </div>

    <form class="gw-form" id="form-perfil" novalidate>
      <div class="gw-field">
        <label for="perfil-nickname">Nickname gamer</label>
        <input type="text" id="perfil-nickname" name="nickname" maxlength="50" placeholder="Sin nickname aún">
        <small>Si lo defines, aparecerá en el header en lugar de tu nombre.</small>
      </div>

      <div class="gw-form-row">
        <div class="gw-field">
          <label for="perfil-nombre">Nombre *</label>
          <input type="text" id="perfil-nombre" name="nombre" maxlength="80" required>
        </div>
        <div class="gw-field">
          <label for="perfil-apellidos">Apellidos *</label>
          <input type="text" id="perfil-apellidos" name="apellidos" maxlength="120" required>
        </div>
      </div>

      <div class="gw-field">
        <label for="perfil-email">Correo electrónico *</label>
        <input type="email" id="perfil-email" name="email" maxlength="150" required>
      </div>

      <div class="gw-form-row">
        <div class="gw-field">
          <label for="perfil-fecha">Fecha de nacimiento *</label>
          <input type="date" id="perfil-fecha" name="fecha_nacimiento" min="1900-01-01" required>
        </div>
        <div class="gw-field">
          <label for="perfil-telefono">Teléfono</label>
          <input type="tel" id="perfil-telefono" name="telefono" maxlength="25">
        </div>
      </div>

      <div class="gw-form-row">
        <div class="gw-field">
          <label for="perfil-pass-actual">Contraseña actual (solo si la cambias)</label>
          <input type="password" id="perfil-pass-actual" name="password_actual" autocomplete="current-password">
        </div>
        <div class="gw-field">
          <label for="perfil-pass-nueva">Nueva contraseña</label>
          <input type="password" id="perfil-pass-nueva" name="password_nueva" minlength="6" autocomplete="new-password" placeholder="Mín. 6 caracteres">
        </div>
      </div>

      <div class="gw-alert is-hidden" id="perfil-feedback" role="status" aria-live="polite"></div>

      <div class="gw-modal-actions">
        <button type="submit" class="btn-primary-modal" id="btn-submit-perfil">Guardar cambios</button>
      </div>
    </form>

    <details class="danger-zone" id="perfil-danger-zone">
      <summary>Eliminar mi cuenta definitivamente</summary>
      <form class="danger-zone-body" id="form-delete-account" novalidate>
        <p>Esta acción eliminará tu perfil y cancelará todas tus inscripciones, liberando tus plazas automáticamente.</p>
        <div class="gw-field">
          <label for="delete-account-password">Confirma con tu contraseña actual *</label>
          <input type="password" id="delete-account-password" name="password" required placeholder="Tu contraseña">
        </div>
        <div class="gw-alert is-error is-hidden" id="delete-account-error" role="alert"></div>
        <div class="gw-modal-actions">
          <button type="submit" class="btn-danger-modal" id="btn-delete-account">Eliminar cuenta</button>
        </div>
      </form>
    </details>
  </div>
</div>
