document.addEventListener('DOMContentLoaded', () => {
  const grid = document.getElementById('gridNoticias');
  const overlay = document.querySelector('.overlay');

  // Data bonita
  const dataEl = document.getElementById('data');
  if (dataEl) {
    dataEl.innerText = new Date().toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: 'long',
      year: 'numeric'
    });
  }

  fetch('/noticias/automacao/api/listar_publicadas.php')
    .then(r => r.json())
    .then(noticias => {
      if (!noticias || !noticias.length) {
        grid.innerHTML = '<p>Nenhuma notícia publicada hoje.</p>';
        return;
      }

      noticias.forEach(n => {
        const card = document.createElement('div');
        card.className = 'card';

        card.innerHTML = `
          <h3>${n.titulo}</h3>
          <div class="texto">
            ${n.texto}
          </div>
        `;

        card.addEventListener('click', () => {
          abrirCard(card);
        });

        grid.appendChild(card);
      });
    });

  function abrirCard(card) {
    card.classList.add('ativa');
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
  }

  function fecharCards() {
    document.querySelectorAll('.card.ativa').forEach(card => {
      card.classList.remove('ativa');
    });
    overlay.classList.remove('show');
    document.body.style.overflow = '';
  }

  overlay.addEventListener('click', fecharCards);
});
