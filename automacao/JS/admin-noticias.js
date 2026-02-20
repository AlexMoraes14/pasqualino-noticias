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

  let acaoConfirmacao = null;
  let carregandoPendentes = false;
  let carregandoPublicadas = false;
  let adminAutenticado = false;
  let botaoConfirmacaoTextoOriginal = '';

  function escapeHtml(texto) {
    return (texto || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function normalizarMarcacaoMarkdown(texto) {
    return (texto || '')
      .replace(/(^|[\r\n>])\s{0,3}#{1,6}\s+/g, '$1')
      .replace(/\*\*([^*]+?)\*\*/g, '$1')
      .replace(/\*\*/g, '');
  }

  function prepararConteudoHtml(conteudoHtml) {
    const html = normalizarMarcacaoMarkdown((conteudoHtml || '').trim());
    if (!html) return '';

    const temp = document.createElement('div');
    temp.innerHTML = html;

    temp.querySelectorAll('table').forEach((table) => {
      if (table.closest('.tabela-scroll')) return;
      const wrapper = document.createElement('div');
      wrapper.className = 'tabela-scroll';
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    });

    return temp.innerHTML.trim();
  }

  function htmlParaTextoComQuebras(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html || '';

    temp.querySelectorAll('br').forEach((el) => {
      el.replaceWith('\n');
    });

    temp.querySelectorAll('td,th').forEach((el) => {
      el.appendChild(document.createTextNode(' | '));
    });

    temp.querySelectorAll('p,div,section,article,li,tr,h1,h2,h3,h4,h5,h6').forEach((el) => {
      el.appendChild(document.createTextNode('\n'));
    });

    return (temp.textContent || temp.innerText || '')
      .replace(/\u00A0/g, ' ')
      .replace(/[ \t]+\n/g, '\n')
      .replace(/\n{3,}/g, '\n\n')
      .trim();
  }

  function montarHtmlVisualizacao(noticia) {
    const htmlTratado = prepararConteudoHtml(noticia.conteudo_html);
    if (htmlTratado) {
      return `<div class="conteudo-visualizacao">${htmlTratado}</div>`;
    }

    const texto = normalizarMarcacaoMarkdown((noticia.texto || '').trim());
    if (!texto) {
      return '<div class="conteudo-visualizacao"><p>Conte\u00fado indispon\u00edvel.</p></div>';
    }

    const textoHtml = escapeHtml(texto)
      .replace(/\n{2,}/g, '</p><p>')
      .replace(/\n/g, '<br>');

    return `<div class="conteudo-visualizacao"><p>${textoHtml}</p></div>`;
  }

  function textoParaEdicao(noticia) {
    const htmlTratado = prepararConteudoHtml(noticia.conteudo_html);
    if (htmlTratado) {
      return normalizarMarcacaoMarkdown(htmlParaTextoComQuebras(htmlTratado));
    }

    return normalizarMarcacaoMarkdown((noticia.texto || '').trim());
  }

  function aplicarFeedbackClique(el) {
    if (!el) return;
    el.classList.remove('is-pressed');
    void el.offsetWidth;
    el.classList.add('is-pressed');
    setTimeout(() => el.classList.remove('is-pressed'), 180);
  }

  function setEstadoBotao(el, estado) {
    if (!el) return;
    el.classList.remove('is-loading', 'is-success', 'is-error');
    if (estado) el.classList.add(estado);
  }

  function removerCardSuave(card, onDone) {
    if (!card) return;
    const estilo = window.getComputedStyle(card);
    const alturaInicial = card.getBoundingClientRect().height;

    card.style.maxHeight = `${alturaInicial}px`;
    card.style.overflow = 'hidden';
    card.style.marginTop = estilo.marginTop;
    card.style.marginBottom = estilo.marginBottom;
    card.style.paddingTop = estilo.paddingTop;
    card.style.paddingBottom = estilo.paddingBottom;

    void card.offsetHeight;
    card.classList.add('removendo');

    requestAnimationFrame(() => {
      card.style.maxHeight = '0px';
      card.style.marginTop = '0px';
      card.style.marginBottom = '0px';
      card.style.paddingTop = '0px';
      card.style.paddingBottom = '0px';
      card.style.borderWidth = '0px';
      card.style.opacity = '0';
      card.style.transform = 'translateY(8px) scale(0.985)';
    });

    let finalizado = false;
    const finalizar = () => {
      if (finalizado) return;
      finalizado = true;
      if (card.parentNode) card.remove();
      if (typeof onDone === 'function') onDone();
    };

    card.addEventListener('transitionend', finalizar, { once: true });
    setTimeout(finalizar, 520);
  }

  function garantirMensagemVazia(listaEl, mensagem) {
    if (!listaEl) return;
    if (listaEl.querySelector('.news-card')) return;
    if (listaEl.querySelector('[data-empty-msg="1"]')) return;

    const p = document.createElement('p');
    p.setAttribute('data-empty-msg', '1');
    p.style.textAlign = 'center';
    p.style.color = '#999';
    p.textContent = mensagem;
    listaEl.appendChild(p);
  }

  function abrirConfirmacao({ titulo, mensagem, textoBotao, onConfirm }) {
    if (!confirmModal || !confirmOverlay || !confirmarIgnorar) return;

    if (confirmTitulo) confirmTitulo.textContent = titulo;
    if (confirmMensagem) confirmMensagem.textContent = mensagem;
    confirmarIgnorar.textContent = textoBotao;
    botaoConfirmacaoTextoOriginal = textoBotao;
    acaoConfirmacao = onConfirm;

    confirmModal.classList.add('show');
    confirmOverlay.classList.add('show');
  }

  function executarLogout() {
    return fetch('../api/logout_admin.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
      .then(() => {
        window.location.href = 'noticias.html';
        return true;
      })
      .catch((err) => {
        console.error('Erro ao encerrar sessao:', err);
        window.location.href = 'noticias.html';
        return false;
      });
  }

  if (btnSair) {
    btnSair.addEventListener('click', () => {
      abrirConfirmacao({
        titulo: 'Sair da \u00e1rea administrativa?',
        mensagem: 'Voc\u00ea precisar\u00e1 entrar novamente para continuar.',
        textoBotao: 'Sair',
        onConfirm: executarLogout
      });
    });
  }

  function criarCard(noticia, isPendente) {
    const card = document.createElement('div');
    card.className = 'news-card';
    card.classList.add(isPendente ? 'pendente' : 'publicada');

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

    const btnEditar = document.createElement('button');
    btnEditar.className = 'btn-secundario';
    btnEditar.textContent = 'Editar';
    btnEditar.type = 'button';
    btnEditar.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      abrirModalEdicao(noticia);
    });

    if (isPendente) {
      const btnAprovar = document.createElement('button');
      btnAprovar.className = 'btn-principal';
      btnAprovar.textContent = 'Aprovar';
      btnAprovar.type = 'button';

      btnAprovar.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        aplicarFeedbackClique(btnAprovar);
        setEstadoBotao(btnAprovar, 'is-loading');
        btnAprovar.disabled = true;

        fetch('../api/aprovar_noticia.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `id=${encodeURIComponent(noticia.id)}`
        })
          .then((r) => {
            if (!r.ok) throw new Error(`Erro HTTP: ${r.status}`);
            const ct = r.headers.get('content-type') || '';
            if (ct.includes('application/json')) return r.json();
            return r.text();
          })
          .then((dataResp) => {
            if (typeof dataResp === 'object' && dataResp.success === false) {
              setEstadoBotao(btnAprovar, 'is-error');
              btnAprovar.disabled = false;
              alert('Erro ao aprovar: ' + (dataResp.data || dataResp.message || ''));
              return;
            }
            setEstadoBotao(btnAprovar, 'is-success');
            removerCardSuave(card, () => {
              garantirMensagemVazia(pendentesEl, 'Nenhuma not\u00edcia pendente');
            });
            setTimeout(() => carregarPublicadas(), 320);
          })
          .catch((err) => {
            setEstadoBotao(btnAprovar, 'is-error');
            btnAprovar.disabled = false;
            console.error('Erro ao aprovar:', err);
            alert('Erro ao aprovar not\u00edcia: ' + err.message);
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
        aplicarFeedbackClique(btnIgnorar);

        abrirConfirmacao({
          titulo: 'Ignorar not\u00edcia?',
          mensagem: 'Essa a\u00e7\u00e3o n\u00e3o poder\u00e1 ser desfeita.',
          textoBotao: 'Ignorar',
          onConfirm: () => ignorarNoticia(noticia.id).then((sucesso) => {
            if (!sucesso) throw new Error('N\u00e3o foi poss\u00edvel ignorar a not\u00edcia');
            removerCardSuave(card, () => {
              garantirMensagemVazia(pendentesEl, 'Nenhuma not\u00edcia pendente');
            });
          })
        });
      }, true);

      acoesPrincipais.appendChild(btnEditar);
      acoesPrincipais.appendChild(btnAprovar);
      acoesSecundarias.appendChild(btnIgnorar);
    } else {
      const btnExcluir = document.createElement('button');
      btnExcluir.className = 'btn-excluir';
      btnExcluir.textContent = 'Excluir';
      btnExcluir.type = 'button';

      btnExcluir.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        aplicarFeedbackClique(btnExcluir);

        abrirConfirmacao({
          titulo: 'Excluir not\u00edcia?',
          mensagem: 'Essa a\u00e7\u00e3o n\u00e3o poder\u00e1 ser desfeita.',
          textoBotao: 'Excluir',
          onConfirm: () => excluirNoticia(noticia.id).then((sucesso) => {
            if (!sucesso) throw new Error('N\u00e3o foi poss\u00edvel excluir a not\u00edcia');
            setEstadoBotao(btnExcluir, 'is-success');
            removerCardSuave(card, () => {
              garantirMensagemVazia(publicadasEl, 'Nenhuma not\u00edcia publicada');
            });
          })
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

    card.addEventListener('click', (e) => {
      if (e.target.closest('button') || e.target.closest('span') || e.target.closest('a')) return;
      abrirModalVisualizacao(noticia);
    });

    return card;
  }

  function abrirModalVisualizacao(noticia) {
    modalTitulo.textContent = noticia.titulo;
    modalTexto.innerHTML = montarHtmlVisualizacao(noticia);
    modal.classList.add('show');
    overlay.classList.add('show');
  }

  function fecharModal() {
    modal.classList.remove('show');
    overlay.classList.remove('show');
  }

  function abrirModalEdicao(noticia) {
    const tituloAtual = escapeHtml((noticia.titulo || '').trim());
    const conteudoAtual = escapeHtml(textoParaEdicao(noticia));

    modalTitulo.innerHTML = `
      <label class="modal-label">T\u00edtulo</label>
      <input id="editTitulo" value="${tituloAtual}">
    `;

    modalTexto.innerHTML = `
      <label class="modal-label">Conte\u00fado</label>
      <textarea id="editTexto">${conteudoAtual}</textarea>
      <div class="modal-acoes">
        <button class="btn-secundario" id="btnFecharInterno">Fechar</button>
        <button class="btn-principal" id="btnSalvar">Salvar altera\u00e7\u00f5es</button>
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
      alert('T\u00edtulo e conte\u00fado s\u00e3o obrigat\u00f3rios');
      return;
    }

    fetch('../api/editar_noticia.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${encodeURIComponent(id)}&titulo=${encodeURIComponent(titulo)}&texto=${encodeURIComponent(texto)}`
    })
      .then((r) => {
        if (!r.ok) throw new Error(`Erro HTTP: ${r.status}`);
        const ct = r.headers.get('content-type') || '';
        if (ct.includes('application/json')) return r.json();
        return r.text();
      })
      .then((dataResp) => {
        if (typeof dataResp === 'object' && dataResp.success === false) {
          alert('Erro ao salvar: ' + (dataResp.data || dataResp.message || ''));
          return;
        }
        fecharModal();
        carregarPublicadas();
        carregarPendentes();
      })
      .catch((err) => {
        console.error('Erro ao salvar:', err);
        alert('Erro ao salvar not\u00edcia: ' + err.message);
      });
  }

  if (overlay) overlay.addEventListener('click', fecharModal);

  if (cancelarIgnorar) {
    cancelarIgnorar.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      fecharConfirmacao();
    });
  }

  if (confirmOverlay) {
    confirmOverlay.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      fecharConfirmacao();
    });
  }

  function ignorarNoticia(idNoticia) {
    return fetch('../api/ignorar_noticia.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${encodeURIComponent(idNoticia)}`
    })
      .then((r) => {
        if (!r.ok) throw new Error(`Erro HTTP: ${r.status}`);
        const ct = r.headers.get('content-type') || '';
        if (ct.includes('application/json')) return r.json();
        return r.text();
      })
      .then((dataResp) => {
        if (typeof dataResp === 'object' && dataResp.success === false) {
          alert('Erro ao ignorar: ' + (dataResp.message || ''));
          return false;
        }
        return true;
      })
      .catch((err) => {
        console.error('Erro ao ignorar:', err);
        alert('Erro ao ignorar not\u00edcia: ' + err.message);
        return false;
      });
  }

  function excluirNoticia(idNoticia) {
    return fetch('../api/excluir_noticia.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${encodeURIComponent(idNoticia)}`
    })
      .then((r) => {
        if (!r.ok) throw new Error(`Erro HTTP: ${r.status}`);
        const ct = r.headers.get('content-type') || '';
        if (ct.includes('application/json')) return r.json();
        return r.text();
      })
      .then((dataResp) => {
        if (typeof dataResp === 'object' && dataResp.success === false) {
          alert('Erro ao excluir: ' + (dataResp.message || ''));
          return false;
        }
        return true;
      })
      .catch((err) => {
        console.error('Erro ao excluir:', err);
        alert('Erro ao excluir not\u00edcia: ' + err.message);
        return false;
      });
  }

  if (confirmarIgnorar) {
    confirmarIgnorar.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      aplicarFeedbackClique(confirmarIgnorar);

      if (typeof acaoConfirmacao === 'function') {
        const acao = acaoConfirmacao;
        setEstadoBotao(confirmarIgnorar, 'is-loading');
        confirmarIgnorar.disabled = true;
        Promise.resolve(acao())
          .then(() => {
            setEstadoBotao(confirmarIgnorar, 'is-success');
          })
          .catch(() => {
            setEstadoBotao(confirmarIgnorar, 'is-error');
          })
          .finally(() => {
            setTimeout(() => fecharConfirmacao(), 300);
          });
      }
    });
  }

  function fecharConfirmacao() {
    confirmModal.classList.remove('show');
    confirmOverlay.classList.remove('show');
    acaoConfirmacao = null;
    confirmarIgnorar.textContent = botaoConfirmacaoTextoOriginal || 'Ignorar';
    confirmarIgnorar.disabled = false;
    setEstadoBotao(confirmarIgnorar, null);
  }

  function carregarPendentes() {
    if (!adminAutenticado || !pendentesEl) return;
    if (carregandoPendentes) return;

    carregandoPendentes = true;
    pendentesEl.innerHTML = '<p style="text-align:center;color:#999;">Carregando...</p>';

    fetch('../api/listar_pendentes.php')
      .then(async (r) => {
        const data = await r.json();
        if (!r.ok) {
          throw new Error((data && data.message) || `Erro HTTP: ${r.status}`);
        }
        if (!Array.isArray(data)) {
          throw new Error((data && data.message) || 'Resposta invalida para pendentes');
        }
        return data;
      })
      .then((lista) => {
        pendentesEl.innerHTML = '';
        if (lista && lista.length > 0) {
          lista.forEach((n) => pendentesEl.appendChild(criarCard(n, true)));
        } else {
          pendentesEl.innerHTML = '<p style="text-align:center;color:#999;">Nenhuma not\u00edcia pendente</p>';
        }
      })
      .catch((err) => {
        console.error('Erro ao carregar pendentes:', err);
        pendentesEl.innerHTML = '<p style="text-align:center;color:red;">Erro ao carregar</p>';
      })
      .finally(() => {
        carregandoPendentes = false;
      });
  }

  function carregarPublicadas() {
    if (!adminAutenticado || !publicadasEl) return;
    if (carregandoPublicadas) return;

    carregandoPublicadas = true;
    publicadasEl.innerHTML = '<p style="text-align:center;color:#999;">Carregando...</p>';

    fetch('../api/listar_publicadas.php')
      .then(async (r) => {
        const data = await r.json();
        if (!r.ok) {
          throw new Error((data && data.message) || `Erro HTTP: ${r.status}`);
        }
        if (!Array.isArray(data)) {
          throw new Error((data && data.message) || 'Resposta invalida para publicadas');
        }
        return data;
      })
      .then((lista) => {
        publicadasEl.innerHTML = '';
        if (lista && lista.length > 0) {
          lista.forEach((n) => publicadasEl.appendChild(criarCard(n, false)));
        } else {
          publicadasEl.innerHTML = '<p style="text-align:center;color:#999;">Nenhuma not\u00edcia publicada</p>';
        }
      })
      .catch((err) => {
        console.error('Erro ao carregar publicadas:', err);
        publicadasEl.innerHTML = '<p style="text-align:center;color:red;">Erro ao carregar</p>';
      })
      .finally(() => {
        carregandoPublicadas = false;
      });
  }

  function validarSessaoAdmin() {
    fetch('../api/check_admin.php', { credentials: 'same-origin' })
      .then((r) => {
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          throw new Error('Resposta inv\u00e1lida de autentica\u00e7\u00e3o');
        }
        return r.json().then((dataResp) => ({ ok: r.ok, dataResp }));
      })
      .then(({ ok, dataResp }) => {
        if (!ok || !dataResp.success) {
          window.location.href = 'noticias.html';
          return;
        }
        adminAutenticado = true;
        carregarPendentes();
        carregarPublicadas();
      })
      .catch((err) => {
        console.error('Erro ao validar sessao admin:', err);
        window.location.href = 'noticias.html';
      });
  }

  validarSessaoAdmin();
  window.carregarPendentes = carregarPendentes;
  window.carregarPublicadas = carregarPublicadas;
});
