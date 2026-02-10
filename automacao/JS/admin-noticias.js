document.addEventListener('DOMContentLoaded', () => {

  // 🔹 referências do HTML
  const pendentesEl = document.getElementById('lista-pendentes');
  const publicadasEl = document.getElementById('lista-publicadas');

  const modal = document.getElementById('modal');
  const overlay = document.getElementById('overlay');
  const modalTitulo = document.getElementById('modalTitulo');
  const modalTexto = document.getElementById('modalTexto');
  const btnFechar = document.getElementById('btnFecharModal');

  // 🔹 carregar pendentes
  function carregarPendentes() {
    fetch('../api/listar_pendentes.php')
      .then(r => r.json())
      .then(lista => {
        pendentesEl.innerHTML = '';
        lista.forEach(n => {
          pendentesEl.appendChild(criarCard(n, true));
        });
      });
  }

  // 🔹 carregar publicadas
  function carregarPublicadas() {
    fetch('../api/listar_publicadas.php')
      .then(r => r.json())
      .then(lista => {
        publicadasEl.innerHTML = '';
        lista.forEach(n => {
          if (n.status === 'publish' || n.status === undefined) {
           publicadasEl.appendChild(criarCard(n, false));
          }
        });
    });

  }

  // 🔓 expor para pipeline.js
  window.carregarPendentes = carregarPendentes;
  window.carregarPublicadas = carregarPublicadas;

  // 🔹 cria o card da notícia
  function criarCard(noticia, isPendente) {

    const card = document.createElement('div');
    card.className = 'news-card';
    if (isPendente) {
      card.classList.add('pendete');
    }

    const titulo = document.createElement('h3');
    titulo.textContent = noticia.titulo;

    const data = document.createElement('span');
    data.className = 'data';
    data.textContent = noticia.data;

    const acoes = document.createElement('div');
    acoes.className = 'acoes';

    const btnVisualizar = document.createElement('button');
    btnVisualizar.className = 'btn-secundario';
    btnVisualizar.textContent = 'Visualizar';
    btnVisualizar.addEventListener('click', () => {
      abrirModal(noticia.titulo, noticia.texto);
    });

    acoes.appendChild(btnVisualizar);

    if (isPendente === true) {

      const btnAprovar = document.createElement('button');
      btnAprovar.className = 'btn-principal';
      btnAprovar.textContent = 'Aprovar';

      const btnIgnorar = document.createElement('button');
      btnIgnorar.className = 'btn-perigo';
      btnIgnorar.textContent = 'Ignorar';

      btnAprovar.addEventListener('click', () => {
        fetch('../api/aprovar_noticia.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `id=${noticia.id}`
        }).then(() => {
          carregarPendentes();
          carregarPublicadas();
        });
      });

      btnIgnorar.addEventListener('click', () => {
        fetch('../api/ignorar_noticia.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `id=${noticia.id}`
        }).then(() => {
          carregarPendentes();
        });
      });

      acoes.appendChild(btnAprovar);
      acoes.appendChild(btnIgnorar);
    }

    card.appendChild(titulo);
    card.appendChild(data);
    card.appendChild(acoes);

    return card;
  }

  // 🔹 modal
  function abrirModal(titulo, texto) {
    modalTitulo.textContent = titulo;
    modalTexto.textContent = texto;
    modal.classList.add('show');
    overlay.classList.add('show');
  }

  function fecharModal() {
    modal.classList.remove('show');
    overlay.classList.remove('show');
  }

  btnFechar.addEventListener('click', fecharModal);
  overlay.addEventListener('click', fecharModal);

  // 🔹 inicialização
  carregarPendentes();
  carregarPublicadas();

});
