<section id="como-llegar" class="section-block como-llegar-section">
  <div class="section-head">
    <div>
      <div class="section-eyebrow">Sede del evento · CCC Madrid</div>
      <h2 class="section-title">Cómo llegar</h2>
      <p class="section-subtitle">
        Encuentra el centro, revisa las opciones de transporte y abre la ruta directamente en Google Maps.
      </p>
    </div>
  </div>

  <div class="llegar-grid">
    <article class="llegar-card">
      <h3 class="llegar-sede"><?= htmlspecialchars(SEDE_NOMBRE) ?></h3>

      <address class="llegar-direccion">
        <span class="llegar-icon" aria-hidden="true">📍</span>
        <span>
          <strong><?= htmlspecialchars(SEDE_CALLE) ?></strong><br>
          <?= htmlspecialchars(SEDE_CIUDAD) ?>
        </span>
      </address>

      <div class="llegar-transporte">
        <p class="llegar-label">Cómo llegar:</p>
        <ul>
          <li>
            <span class="llegar-icon" aria-hidden="true">🚇</span>
            <span><strong>Metro:</strong> Suanzes (Línea 5) a 6 minutos a pie o Ciudad Lineal.</span>
          </li>
          <li>
            <span class="llegar-icon" aria-hidden="true">🚌</span>
            <span><strong>Autobuses:</strong> Líneas 77, 104 y 105.</span>
          </li>
          <li>
            <span class="llegar-icon" aria-hidden="true">🎟️</span>
            <span><strong>Acceso al torneo:</strong> entrada principal, ascensores directos a la Planta 5 (Aulas Tech / Arena Gaming).</span>
          </li>
        </ul>
      </div>

      <a class="llegar-btn"
         href="<?= htmlspecialchars(MAPA_RUTA_URL) ?>"
         target="_blank"
         rel="noopener noreferrer">
        Abrir en Google Maps <span aria-hidden="true">↗</span>
      </a>
    </article>

    <div class="llegar-mapa">
      <iframe
        src="<?= htmlspecialchars(MAPA_EMBED_URL) ?>"
        title="Mapa de la sede: <?= htmlspecialchars(SEDE_NOMBRE) ?>, <?= htmlspecialchars(SEDE_CALLE) ?>"
        allowfullscreen
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
  </div>
</section>
