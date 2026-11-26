<?php require_once __DIR__ . '/includes/config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= SITE_TITLE ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/header.css">
  <link rel="stylesheet" href="css/programa.css">
  <link rel="stylesheet" href="css/torneos.css">
  <link rel="stylesheet" href="css/ponentes.css">
  <link rel="stylesheet" href="css/como_llegar.css">
  <link rel="stylesheet" href="css/modals.css">
  <link rel="stylesheet" href="css/footer.css">
</head>
<body>
  <?php include 'includes/header.php'; ?>
  <?php include 'sections/inicio.php'; ?>
  <?php include 'sections/actividades.php'; ?>
  <?php include 'sections/programa.php'; ?>
  <?php include 'sections/ponentes.php'; ?>
  <?php include 'sections/como-llegar.php'; ?>
  <?php include 'sections/torneos.php'; ?>
  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/modals.php'; ?>

  <script src="js/header.js" defer></script>
  <script src="js/main.js" defer></script>
  <script src="js/api.js" defer></script>
  <script src="js/auth.js" defer></script>
  <script src="js/inscripcion-modal.js" defer></script>
  <script src="js/mis-inscripciones.js" defer></script>
  <script src="js/perfil.js" defer></script>
  <script src="js/programa.js" defer></script>
  <script src="js/torneos.js" defer></script>
</body>
</html>
