// 1. Datos dinámicos del torneo (Brackets)
const tournamentData = {
  eafc: {
    quarters: [
      { p1: { name: "Alex Rivas (DAW)", score: 3, win: true }, p2: { name: "DarkGhost", score: 1, win: false } },
      { p1: { name: "Javi_99", score: 0, win: false }, p2: { name: "ShadowK (3D)", score: 2, win: true } }
    ],
    semis: [
      { p1: { name: "Alex Rivas (DAW)", score: 1, win: false }, p2: { name: "ShadowK (3D)", score: 2, win: true }, live: true }
    ],
    final: [
      { p1: { name: "ShadowK (3D)", score: "-", win: false }, p2: { name: "Por Determinar", score: "-", win: false } }
    ]
  },
  tekken: {
    quarters: [
      { p1: { name: "Kazuya_Main", score: 2, win: true }, p2: { name: "Jin_Fury", score: 0, win: false } },
      { p1: { name: "PandaPower", score: 2, win: true }, p2: { name: "LawKing", score: 1, win: false } }
    ],
    semis: [
      { p1: { name: "Kazuya_Main", score: 0, win: false }, p2: { name: "PandaPower", score: 2, win: true }, live: false }
    ],
    final: [
      { p1: { name: "PandaPower", score: "-", win: false }, p2: { name: "Por Determinar", score: "-", win: false } }
    ]
  }
};

const bracketContainer = document.getElementById("bracketContainer");
const tabButtons = document.querySelectorAll(".tab-btn");

// 2. Renderizado del cuadro de eliminatorias
function renderBrackets(gameKey) {
  const data = tournamentData[gameKey];
  bracketContainer.innerHTML = "";

  const stages = [
    { title: "Cuartos de Final", matches: data.quarters },
    { title: "Semifinal", matches: data.semis },
    { title: "Gran Final", matches: data.final }
  ];

  stages.forEach(stage => {
    const col = document.createElement("div");
    col.className = "round-col";
    
    let html = `<h4>${stage.title}</h4>`;
    stage.matches.forEach(m => {
      const isFeatured = m.live ? "featured" : "";
      html += `
        <div class="match-card ${isFeatured}">
          <div class="player-row ${m.p1.win ? 'winner' : (m.p1.score !== '-' && !m.p1.win ? 'loser' : '')}">
            <span>${m.p1.name}</span>
            <span class="score">${m.p1.score}</span>
          </div>
          <div class="player-row ${m.p2.win ? 'winner' : (m.p2.score !== '-' && !m.p2.win ? 'loser' : '')}">
            <span>${m.p2.name}</span>
            <span class="score">${m.p2.score}</span>
          </div>
        </div>
      `;
    });

    col.innerHTML = html;
    bracketContainer.appendChild(col);
  });
}

// Eventos de pestañas
tabButtons.forEach(btn => {
  btn.addEventListener("click", () => {
    tabButtons.forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    renderBrackets(btn.dataset.game);
  });
});

renderBrackets("eafc");

// 3. Conexión con Google Sheets
const SCRIPT_URL = "https://script.google.com/macros/s/AKfycbxooEJwotUP4fTrB_mhfYU9724uq3siYbxu0v6Tt0a7uVODgAe08k9Ov-Ay5TWnWUK7/exec";

const form = document.getElementById("tournamentForm");
const feedback = document.getElementById("formFeedback");
const submitBtn = form.querySelector("button[type='submit']");

form.addEventListener("submit", (e) => {
  e.preventDefault();

  const payload = {
    nombre: document.getElementById("playerName").value.trim(),
    gamertag: document.getElementById("playerTag").value.trim(),
    email: document.getElementById("playerEmail").value.trim(),
    juego: document.getElementById("playerGame").value,
    tipo_asistente: document.getElementById("affiliation").value
  };

  if (!payload.nombre || !payload.gamertag || !payload.email) return;

  const originalText = submitBtn.innerText;
  submitBtn.innerText = "REGISTRANDO...";
  submitBtn.disabled = true;

  fetch(SCRIPT_URL, {
    method: "POST",
    mode: "no-cors",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  })
  .then(() => {
    feedback.innerText = "✓ ¡Inscripción completada! Tus datos se han registrado correctamente en la lista.";
    feedback.classList.remove("is-hidden");
    form.reset();
  })
  .catch((err) => {
    console.error("Error al registrar:", err);
    feedback.innerText = "Hubo un error al guardar la plaza. Inténtalo de nuevo.";
    feedback.classList.remove("is-hidden");
  })
  .finally(() => {
    submitBtn.innerText = originalText;
    submitBtn.disabled = false;
    setTimeout(() => feedback.classList.add("is-hidden"), 6000);
  });
});
