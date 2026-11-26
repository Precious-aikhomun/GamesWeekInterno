<?php
// Configuración general del sitio
define('SITE_TITLE', 'Game Week 2027 · CCC Madrid');
define('EVENT_START', '2027-01-18T12:00:00+01:00'); // inicio del evento (cuenta regresiva)

// Sede del evento (sección "Cómo llegar")
define('SEDE_NOMBRE', 'Centro de Formación Profesional CCC');
define('SEDE_CALLE', 'Calle de San Sotero, 3 - Planta 5');
define('SEDE_CIUDAD', '28037 Madrid, España');
define('SEDE_BUSQUEDA', 'Calle de San Sotero 3, 28037 Madrid, España'); // texto que entiende Google Maps
define('MAPA_EMBED_URL', 'https://www.google.com/maps?q=' . rawurlencode(SEDE_BUSQUEDA) . '&hl=es&z=16&output=embed');
define('MAPA_RUTA_URL', 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(SEDE_BUSQUEDA));

// Base de datos MySQL (ajustar en el servidor)
define('DB_HOST', 'localhost');
define('DB_NAME', 'gamesweek');
define('DB_USER', 'root');
define('DB_PASS', '');