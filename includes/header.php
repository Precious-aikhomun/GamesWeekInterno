<!-- includes/header.php -->
<header class="site-header">
  <div class="header-inner">

    <a class="brand" href="#inicio">
      <img src="assets/img/logo.png" alt="GamesWeek">
      <b class="d">Game <i>Week</i></b>
    </a>

    <button class="nav-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="menu-principal">☰</button>

    <nav class="site-nav" id="menu-principal">
      <ul class="menu">
        <li class="has-sub">
          <a href="#actividades">Actividades</a>
          <button class="sub-toggle" type="button" aria-label="Mostrar subapartados" aria-expanded="false">▼</button>
          <ul class="submenu">
            <li><a href="#torneos">Torneos</a></li>
            <li><a href="#clasificacion">Clasificación</a></li>
            <li><a href="#programa" data-programa-filter="Día Indie">Día Indie</a></li>
            <li><a href="#programa" data-programa-filter="Novedades">Novedades</a></li>
          </ul>
        </li>

        <li><a href="#programa">Programa</a></li>
        <li><a href="#ponentes">Ponentes</a></li>
        <li><a href="#como-llegar">Cómo llegar</a></li>

        <li class="menu-auth" id="header-auth">
          <button type="button" class="btn-header-auth" id="btn-open-login">Iniciar sesión</button>

          <div class="user-menu is-hidden" id="user-menu">
            <button
              type="button"
              class="user-menu-btn"
              id="btn-user-menu"
              aria-expanded="false"
              aria-haspopup="true"
              aria-controls="user-dropdown"
            >
              <span class="user-avatar-dot" id="user-avatar-initial" aria-hidden="true">U</span>
              <span class="user-display-name" id="user-display-name">Usuario</span>
              <span class="user-caret" aria-hidden="true">▾</span>
            </button>

            <div class="user-dropdown" id="user-dropdown" role="menu">
              <div class="user-dropdown-meta">
                <span class="user-dropdown-nick is-hidden" id="user-dropdown-nick"></span>
                <span class="user-dropdown-fullname" id="user-dropdown-fullname"></span>
                <span class="user-dropdown-email" id="user-dropdown-email"></span>
              </div>

              <button type="button" class="user-dropdown-item" role="menuitem" data-open-modal="mis-inscripciones">
                <span>Mis inscripciones</span>
                <span class="badge-count is-hidden" id="badge-mis-inscripciones">0</span>
              </button>

              <button type="button" class="user-dropdown-item" role="menuitem" data-open-modal="perfil">
                <span>Mi perfil</span>
              </button>

              <hr class="user-dropdown-sep">

              <button type="button" class="user-dropdown-item is-danger" role="menuitem" id="btn-logout">
                <span>Cerrar sesión</span>
              </button>
            </div>
          </div>
        </li>
      </ul>
    </nav>

  </div>
</header>
