// js/header.js
(function () {
  var botonMenu = document.querySelector('.nav-toggle');
  var menu = document.getElementById('menu-principal');
  var botonSub = document.querySelector('.sub-toggle');
  var itemActividades = document.querySelector('.has-sub');
  var userMenu = document.getElementById('user-menu');
  var btnUserMenu = document.getElementById('btn-user-menu');

  function setMobileMenu(isOpen) {
    if (!menu || !botonMenu) return;
    menu.classList.toggle('is-open', isOpen);
    botonMenu.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    botonMenu.setAttribute('aria-label', isOpen ? 'Cerrar menú' : 'Abrir menú');
  }

  function setUserDropdown(isOpen) {
    if (!userMenu || !btnUserMenu) return;
    userMenu.classList.toggle('is-open', isOpen);
    btnUserMenu.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }

  if (botonMenu && menu) {
    botonMenu.addEventListener('click', function () {
      setMobileMenu(!menu.classList.contains('is-open'));
    });

    menu.addEventListener('click', function (e) {
      var target = e.target;
      if (target && target.tagName === 'A') {
        setMobileMenu(false);
      }
    });
  }

  if (botonSub && itemActividades) {
    botonSub.addEventListener('click', function () {
      var opened = itemActividades.classList.toggle('open');
      botonSub.setAttribute('aria-expanded', opened ? 'true' : 'false');
    });
  }

  if (btnUserMenu && userMenu) {
    btnUserMenu.addEventListener('click', function (e) {
      e.stopPropagation();
      setUserDropdown(!userMenu.classList.contains('is-open'));
    });

    userMenu.addEventListener('click', function (e) {
      var item = e.target.closest('.user-dropdown-item');
      if (item) {
        setUserDropdown(false);
        setMobileMenu(false);
      }
    });
  }

  document.addEventListener('click', function (e) {
    if (userMenu && userMenu.classList.contains('is-open') && !userMenu.contains(e.target)) {
      setUserDropdown(false);
    }
    if (itemActividades && itemActividades.classList.contains('open') && !itemActividades.contains(e.target)) {
      itemActividades.classList.remove('open');
      if (botonSub) botonSub.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      setUserDropdown(false);
      setMobileMenu(false);
    }
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth >= 900) {
      setMobileMenu(false);
    }
  });
})();
