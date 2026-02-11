document.addEventListener('DOMContentLoaded', () => {

  const pendentesEl = document.getElementById('lista-pendentes');
  const publicadasEl = document.getElementById('lista-publicadas');

  const modal = document.getElementById('modal');
  const overlay = document.getElementById('overlay');
  const modalTitulo = document.getElementById('modalTitulo');
  const modalTexto = document.getElementById('modalTexto');

  const confirmModal = document.getElementById('confirmModal');
  const confirmOverlay = document.getElementById('confirmOverlay');
  const cancelarIgnorar = document.getElementById('cancelarIgnorar');
  const confirmarIgnorar = document.getElementById('confirmarIgnorar');
  const confirmTitulo = confirmModal ? confirmModal.querySelector('h3') : null;
  const confirmMensagem = confirmModal ? confirmModal.querySelector('p') : null;
  const btnSair = document.getElementById('btnSair');

  let idParaIgnorar = null;
  let acaoConfirmacao = null;
  let carregandoPendentes = false;
  let carregandoPublicadas = false;
  let adminAutenticado = false;

  function abrirConfirmacao({ titulo, mensagem, textoBotao, onConfirm }) {
    if (!confirmModal || !confirmOverlay || !confirmarIgnorar) return;

    if (confirmTitulo) confirmTitulo.textContent = titulo;
    if (confirmMensagem) confirmMensagem.textContent = mensagem;
    confirmarIgnorar.textContent = textoBotao;
    acaoConfirmacao = onConfirm;

    confirmModal.classList.add('show');
    confirmOverlay.classList.add('show');
  }

  function executarLogout() {
    fetch('../api/logout_admin.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
      .then(() => {
        window.location.href = 'index.html';
      })
      .catch((err) => {
        console.error('Erro ao encerrar sessao:', err);
        window.location.href = 'index.html';
      });
  }

  if (btnSair) {
    btnSair.addEventListener('click', () => {
      abrirConfirmacao({
        titulo: 'Sair da área administrativa?',
        mensagem: 'Você precisará entrar novamente para continuar.',
        textoBotao: 'Sair',
        onConfirm: executarLogout
      });
    });
  }

  /* =========================
     CARDS
  ========================= */
  function criarCard(noticia, isPendente) {

    const card = document.createElement('div');
    card.className = 'news-card';
    if (isPendente) {
      card.classList.add('pendente');
    } else {
      card.classList.add('publicada');
    }

    const titulo = document.createElement('h3');
    titulo.textContent = noticia.titulo;

    const data = document.createElement('span');
    data.className = 'data';
    data.textContent = noticia.data;

    const acoes = document.createElement('div');
    acoes.className = 'acoes';

    const acoesPrincipais = document.createElement('div');
    acoesPrincipais.className = 'acoes-principais';

    const acoesSecundarias = document.createElement('div');
    acoesSecundarias.className = 'acoes-secundarias';

    // =========================
    // BOTÃO EDITAR (serve para pendentes e publicadas)
    // =========================
    const btnEditar = document.createElement('button');
    btnEditar.className = 'btn-secundario';
    btnEditar.textContent = 'Editar';
    
    btnEditar.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      abrirModalEdicao(noticia);
    });

    // =========================
    // PENDENTES
    // =========================
    if (isPendente) {

      const btnAprovar = document.createElement('button');
      btnAprovar.className = 'btn-principal';
      btnAprovar.textContent = 'Aprovar';

      btnAprovar.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        fetch('../api/aprovar_noticia.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(noticia.id)}`
          })
            .then(r => {
              if (!r.ok) throw new Error(`Erro HTTP: ${r.status}`);
              const ct = r.headers.get('content-type') || '';
              if (ct.includes('application/json')) return r.json();
              return r.text();
            })
            .then(data => {
              if (typeof data === 'object' && data.success === false) {
                alert('Erro ao aprovar: ' + (data.data || data.message || ''));
                return;
              }
              carregarPendentes();
              carregarPublicadas();
            })
            .catch(err => {
              console.error('Erro ao aprovar:', err);
              alert('Erro ao aprovar notícia: ' + err.message);
            });
      });

      const btnIgnorar = document.createElement('button');
      btnIgnorar.className = 'btn-perigo';
      btnIgnorar.textContent = 'Ignorar';
      btnIgnorar.type = 'button';

      btnIgnorar.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        abrirConfirmacao({
          titulo: 'Ignorar notícia?',
          mensagem: 'Essa ação não poderá ser desfeita.',
          textoBotao: 'Ignorar',
          onConfirm: () => ignorarNoticia(noticia.id)
        });
      }, true);

      acoesPrincipais.appendChild(btnEditar);
      acoesPrincipais.appendChild(btnAprovar);
      acoesSecundarias.appendChild(btnIgnorar);
    }

    // =========================
    // PUBLICADAS
    // =========================
    if (!isPendente) {

      const btnExcluir = document.createElement('span');
      btnExcluir.className = 'btn-excluir';
      btnExcluir.textContent = 'Excluir';
      btnExcluir.style.cursor = 'pointer';

      btnExcluir.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        if (!confirm('Excluir esta notícia? Essa ação não poderá ser desfeita.')) {
          return;
        }

        fetch('../api/excluir_noticia.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `id=${encodeURIComponent(noticia.id)}`
        })
          .then(r => {
            if (!r.ok) throw new Error(`Erro HTTP: ${r.status}`);
            const ct = r.headers.get('content-type') || '';
            if (ct.includes('application/json')) return r.json();
            return r.text();
          })
          .then(data => {
            if (typeof data === 'object' && data.success === false) {
              alert('Erro ao excluir: ' + (data.message || ''));
              return;
            }
            carregarPublicadas();
          })
          .catch(err => {
            console.error('Erro ao excluir:', err);
            alert('Erro ao excluir notícia: ' + err.message);
          });
      });

      acoesPrincipais.appendChild(btnEditar);
      acoesSecundarias.appendChild(btnExcluir);
    }

    acoes.appendChild(acoesPrincipais);
    acoes.appendChild(acoesSecundarias);

    card.appendChild(titulo);
    card.appendChild(data);
    card.appendChild(acoes);

    // clicar no card abre visualização (mas não nos botões)
    card.addEventListener('click', (e) => {
      // Não abre modal se clicou em botão ou link
      if (e.target.closest('button') || e.target.closest('span') || e.target.closest('a')) {
        return;
      }
      abrirModalVisualizacao(noticia);
    });

    return card;
  }

  function abrirModalVisualizacao(noticia) {
    modalTitulo.textContent = noticia.titulo;
    modalTexto.innerHTML = `<p>${noticia.texto}</p>`;

    modal.classList.add('show');
    overlay.classList.add('show');
  }

  function fecharModal() {
    modal.classList.remove('show');
    overlay.classList.remove('show');
  }

  /* =========================
     MODAL FUNCTIONS
  ========================= */
  function abrirModalEdicao(noticia) {
    modalTitulo.innerHTML = `
      <label>Título</label>
      <input id="editTitulo" value="${noticia.titulo}">
    `;

    modalTexto.innerHTML = `
      <label>Conteúdo</label>
      <textarea id="editTexto">${noticia.texto}</textarea>
      <div class="modal-acoes">
        <button class="btn-secundario" id="btnFecharInterno">Fechar</button>
        <button class="btn-principal" id="btnSalvar">Salvar alterações</button>
      </div>
    `;

    modal.classList.add('show');
    overlay.classList.add('show');

    document.getElementById('btnFecharInterno').onclick = fecharModal;
    document.getElementById('btnSalvar').onclick = () => salvarEdicao(noticia.id);
  }

  function salvarEdicao(id) {
    const titulo = document.getElementById('editTitulo').value;
    const texto = document.getElementById('editTexto').value;

    if (!titulo.trim() || !texto.trim()) {
      alert('Título e conteúdo são obrigatórios');
      return;
    }

    fetch('../api/editar_noticia.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${encodeURIComponent(id)}&titulo=${encodeURIComponent(titulo)}&texto=${encodeURIComponent(texto)}`
    })
      .then(r => {
        if (!r.ok) throw new Error(`Erro HTTP: ${r.status}`);
        const ct = r.headers.get('content-type') || '';
        if (ct.includes('application/json')) return r.json();
        return r.text();
      })
      .then(data => {
        if (typeof data === 'object' && data.success === false) {
          alert('Erro ao salvar: ' + (data.data || data.message || ''));
          return;
        }
        fecharModal();
        carregarPublicadas();
        carregarPendentes();
      })
      .catch(err => {
        console.error('Erro ao salvar:', err);
        alert('Erro ao salvar notícia: ' + err.message);
      });
  }

  if (overlay) {
    overlay.addEventListener('click', fecharModal);
  }

  /* =========================
     CONFIRMA IGNORAR
  ========================= */
  cancelarIgnorar.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    fecharConfirmacao();
  });

  confirmOverlay.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    fecharConfirmacao();
  });

  function ignorarNoticia(idNoticia) {
    fetch('../api/ignorar_noticia.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${encodeURIComponent(idNoticia)}`
    })
      .then(r => {
        if (!r.ok) throw new Error(`Erro HTTP: ${r.status}`);
        const ct = r.headers.get('content-type') || '';
        if (ct.includes('application/json')) return r.json();
        return r.text();
      })
      .then(data => {
        if (typeof data === 'object' && data.success === false) {
          alert('Erro ao ignorar: ' + (data.message || ''));
          return;
        }
        carregarPendentes();
      })
      .catch(err => {
        console.error('Erro ao ignorar:', err);
        alert('Erro ao ignorar notícia: ' + err.message);
      });
  }

  confirmarIgnorar.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    if (typeof acaoConfirmacao === 'function') {
      const acao = acaoConfirmacao;
      fecharConfirmacao();
      acao();
      return;
    }

  });

  function fecharConfirmacao() {
    confirmModal.classList.remove('show');
    confirmOverlay.classList.remove('show');
    acaoConfirmacao = null;
    idParaIgnorar = null;
    confirmarIgnorar.textContent = 'Ignorar';
  }

  /* =========================
     LOAD
  ========================= */
  function carregarPendentes() {
    if (!adminAutenticado) return;
    // Evita múltiplas requisições simultâneas
    if (carregandoPendentes) {
      console.log('Já está carregando pendentes...');
      return;
    }

    carregandoPendentes = true;
    pendentesEl.innerHTML = '<p style="text-align: center; color: #999;">Carregando...</p>';

    fetch('../api/listar_pendentes.php')
      .then(r => r.json())
      .then(lista => {
        pendentesEl.innerHTML = '';
        if (lista && lista.length > 0) {
          lista.forEach(n => pendentesEl.appendChild(criarCard(n, true)));
        } else {
          pendentesEl.innerHTML = '<p style="text-align: center; color: #999;">Nenhuma notícia pendente</p>';
        }
      })
      .catch(err => {
        console.error('Erro ao carregar pendentes:', err);
        pendentesEl.innerHTML = '<p style="text-align: center; color: red;">Erro ao carregar</p>';
      })
      .finally(() => {
        carregandoPendentes = false;
      });
  }

  function carregarPublicadas() {
    if (!adminAutenticado) return;
    // Evita múltiplas requisições simultâneas
    if (carregandoPublicadas) {
      console.log('Já está carregando publicadas...');
      return;
    }

    carregandoPublicadas = true;
    publicadasEl.innerHTML = '<p style="text-align: center; color: #999;">Carregando...</p>';

    fetch('../api/listar_publicadas.php')
      .then(r => r.json())
      .then(lista => {
        publicadasEl.innerHTML = '';
        if (lista && lista.length > 0) {
          lista.forEach(n => publicadasEl.appendChild(criarCard(n, false)));
        } else {
          publicadasEl.innerHTML = '<p style="text-align: center; color: #999;">Nenhuma notícia publicada</p>';
        }
      })
      .catch(err => {
        console.error('Erro ao carregar publicadas:', err);
        publicadasEl.innerHTML = '<p style="text-align: center; color: red;">Erro ao carregar</p>';
      })
      .finally(() => {
        carregandoPublicadas = false;
      });
  }

  function validarSessaoAdmin() {
    fetch('../api/check_admin.php', { credentials: 'same-origin' })
      .then(r => {
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          throw new Error('Resposta invalida de autenticacao');
        }
        return r.json().then(data => ({ ok: r.ok, data }));
      })
      .then(({ ok, data }) => {
        if (!ok || !data.success) {
          window.location.href = 'index.html';
          return;
        }
        adminAutenticado = true;
        carregarPendentes();
        carregarPublicadas();
      })
      .catch(err => {
        console.error('Erro ao validar sessao admin:', err);
        window.location.href = 'index.html';
      });
  }

  validarSessaoAdmin();

  // Expor funções globalmente para serem usadas por outros scripts
  window.carregarPendentes = carregarPendentes;
  window.carregarPublicadas = carregarPublicadas;

});
