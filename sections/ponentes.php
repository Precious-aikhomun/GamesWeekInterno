<?php
// sections/ponentes.php
require_once __DIR__ . '/../includes/db.php';

$sqlPonentes = "
    SELECT 
        p.id,
        p.nombre,
        p.apellidos,
        p.titular,
        p.empresa_o_rol,
        p.biografia,
        p.foto_url,
        GROUP_CONCAT(
            DISTINCT a.codigo_programa 
            ORDER BY a.fecha_inicio ASC 
            SEPARATOR '||'
        ) AS etiquetas
    FROM ponentes p
    LEFT JOIN actividad_ponentes ap ON p.id = ap.ponente_id
    LEFT JOIN actividades a ON ap.actividad_id = a.id
    GROUP BY p.id
    ORDER BY p.id ASC
";

$ponentes = $pdo->query($sqlPonentes)->fetchAll();
?>

<section id="ponentes" class="section-block ponentes-section">
  <div class="ponentes-container">
    <h2 class="ponentes-heading">PONENTES<span>.</span></h2>

    <div class="ponentes-grid">
      <?php foreach ($ponentes as $ponente): ?>
        <?php
          $nombreCompleto = trim($ponente['nombre'] . ' ' . $ponente['apellidos']);
          $etiquetas = !empty($ponente['etiquetas']) ? explode('||', $ponente['etiquetas']) : [];
          $iniciales = mb_strtoupper(
              mb_substr($ponente['nombre'], 0, 1) . mb_substr($ponente['apellidos'], 0, 1)
          );
        ?>
        <article class="ponente-card">
          <div class="ponente-media">
            <?php if (!empty($ponente['foto_url'])): ?>
              <img 
                src="<?= htmlspecialchars($ponente['foto_url']) ?>" 
                alt="<?= htmlspecialchars($nombreCompleto) ?>" 
                class="ponente-foto"
                loading="lazy"
              >
            <?php else: ?>
              <div class="ponente-foto ponente-placeholder" aria-hidden="true">
                <span><?= htmlspecialchars($iniciales) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <h3 class="ponente-nombre"><?= htmlspecialchars($nombreCompleto) ?></h3>
          <p class="ponente-rol"><?= htmlspecialchars($ponente['empresa_o_rol']) ?></p>

          <?php if (!empty($etiquetas)): ?>
            <div class="ponente-badges">
              <?php foreach ($etiquetas as $etiqueta): ?>
                <span class="ponente-badge"><?= htmlspecialchars($etiqueta) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>