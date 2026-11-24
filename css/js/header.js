// js/header.js

// 1. Buscamos los elementos de la página que vamos a usar
var botonMenu = document.querySelector('.nav-toggle');
var menu = document.getElementById('menu-principal');
var botonSub = document.querySelector('.sub-toggle');
var itemActividades = document.querySelector('.has-sub');
var enlaces = document.querySelectorAll('.menu a');

// 2. Abrir y cerrar el menú en el móvil (botón hamburguesa)
botonMenu.addEventListener('click', function () {
  menu.classList.toggle('is-open');

  if (menu.classList.contains('is-open')) {
    botonMenu.setAttribute('aria-expanded', 'true');
  } else {
    botonMenu.setAttribute('aria-expanded', 'false');
  }
});

// 3. Abrir y cerrar el submenú de Actividades (escritorio)
botonSub.addEventListener('click', function () {
  itemActividades.classList.toggle('open');

  if (itemActividades.classList.contains('open')) {
    botonSub.setAttribute('aria-expanded', 'true');
  } else {
    botonSub.setAttribute('aria-expanded', 'false');
  }
});

// 4. Cerrar el menú del móvil cuando se pulsa un enlace
for (var i = 0; i < enlaces.length; i++) {
  enlaces[i].addEventListener('click', function () {
    menu.classList.remove('is-open');
    botonMenu.setAttribute('aria-expanded', 'false');
  });
}