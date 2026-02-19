document.addEventListener('DOMContentLoaded', () => {
  const grid = document.getElementById('gridNoticias');
  const layout = document.querySelector('.noticias-layout');
  const bodyEl = document.body;
  const detalheTitulo = document.getElementById('detalheTitulo');
  const detalheData = document.getElementById('detalheData');
  const detalheTexto = document.getElementById('detalheTexto');
  const painelDetalhe = document.getElementById('painelDetalhe');
  const btnFecharDetalhe = document.getElementById('btnFecharDetalhe');
  const dataEl = document.getElementById('data');

  let cards = [];
  let noticias = [];
  let indiceAtivo = null;
  let animandoPainel = false;
  const DURACAO_FECHAR_MS = 460;
  const DURACAO_FADE_TEXTO_MS = 170;

  if (dataEl) {
    dataEl.textContent = new Date().toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: 'long',
      year: 'numeric'
    });
  }

  function atualizarEstadoTopo() {
    if (!bodyEl) return;
    bodyEl.classList.toggle('topo-reduzido', window.scrollY > 12);
  }

  atualizarEstadoTopo();
  window.addEventListener('scroll', atualizarEstadoTopo, { passive: true });

  function limparHtml(texto) {
    const temp = document.createElement('div');
    temp.innerHTML = normalizarMarcacaoMarkdown(texto || '');
    return (temp.textContent || temp.innerText || '').replace(/\s+/g, ' ').trim();
  }

  function resumo(texto, limite = 190) {
    const limpo = limparHtml(texto);
    if (limpo.length <= limite) return limpo;
    return limpo.slice(0, limite).trimEnd() + '...';
  }

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

  function textoParaHtml(texto) {
    return escapeHtml(normalizarMarcacaoMarkdown(texto))
      .replace(/\n{2,}/g, '</p><p>')
      .replace(/\n/g, '<br>');
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

  function montarDetalheHtml(conteudoHtml, textoBruto) {
    const htmlTratado = prepararConteudoHtml(conteudoHtml);
    if (htmlTratado) return htmlTratado;

    const texto = (textoBruto || '').trim();
    if (!texto) return '<p>Conteudo indisponivel.</p>';

    const match = texto.match(/(conte.do reformulado automaticamente[\s\S]*)/i);
    if (!match || typeof match.index !== 'number') {
      return `<p>${textoParaHtml(texto)}</p>`;
    }

    const principal = texto.slice(0, match.index).trim();
    const assinatura = match[1].trim();

    if (!principal) {
      return `<div class="assinatura-nota"><em>${textoParaHtml(assinatura)}</em></div>`;
    }

    return `<p>${textoParaHtml(principal)}</p><div class="assinatura-nota"><em>${textoParaHtml(assinatura)}</em></div>`;
  }

  function atualizarConteudoDetalhe(item, usarFade = true) {
    const aplicarConteudo = () => {
      detalheTitulo.textContent = item.titulo || 'Sem titulo';
      detalheData.textContent = item.data || '';
      detalheTexto.innerHTML = montarDetalheHtml(item.conteudo_html, item.texto);
      detalheTexto.scrollTop = 0;
    };

    if (!painelDetalhe || !usarFade) {
      aplicarConteudo();
      return;
    }

    painelDetalhe.classList.add('trocando-conteudo');
    setTimeout(() => {
      aplicarConteudo();
      requestAnimationFrame(() => {
        painelDetalhe.classList.remove('trocando-conteudo');
      });
    }, DURACAO_FADE_TEXTO_MS);
  }

  function abrirNoticia(index) {
    if (!noticias[index]) return;

    indiceAtivo = index;
    cards.forEach((card, i) => {
      card.classList.toggle('ativa', i === index);
    });
    grid.classList.add('tem-selecao');
    if (layout) layout.classList.add('com-detalhe');

    const item = noticias[index];
    atualizarConteudoDetalhe(item, true);
  }

  function resetarPainel() {
    detalheTitulo.textContent = 'Selecione uma noticia';
    detalheData.textContent = '';
    detalheTexto.innerHTML = '<p>Clique em um balao para ler a materia completa aqui.</p>';
  }

  function fecharPainel({ limparTexto = true, onFim } = {}) {
    animandoPainel = true;
    indiceAtivo = null;
    cards.forEach((card) => card.classList.remove('ativa'));
    grid.classList.remove('tem-selecao');
    if (layout) layout.classList.remove('com-detalhe');

    if (limparTexto) {
      setTimeout(() => {
        resetarPainel();
      }, 170);
    }

    setTimeout(() => {
      animandoPainel = false;
      if (typeof onFim === 'function') onFim();
    }, DURACAO_FECHAR_MS);
  }

  function limparSelecao() {
    if (animandoPainel) return;
    fecharPainel({ limparTexto: true });
  }

  fetch('../api/listar_publicadas.php')
    .then((r) => {
      if (!r.ok) throw new Error(`Erro HTTP: ${r.status}`);
      return r.json();
    })
    .then((lista) => {
      noticias = Array.isArray(lista) ? lista : [];

      if (!noticias.length) {
        grid.innerHTML = '<p style="color:#fff;">Nenhuma noticia publicada.</p>';
        detalheTitulo.textContent = 'Sem noticias';
        detalheTexto.innerHTML = '<p>Nenhuma materia disponivel no momento.</p>';
        return;
      }

      noticias.forEach((n, index) => {
        const card = document.createElement('article');
        card.className = 'card';
        card.innerHTML = `
          <h3>${n.titulo || 'Sem titulo'}</h3>
          <p class="resumo">${resumo(n.texto)}</p>
        `;

        card.addEventListener('click', () => {
          if (animandoPainel) return;

          if (indiceAtivo === index) {
            limparSelecao();
            return;
          }

          if (indiceAtivo !== null && indiceAtivo !== index) {
            fecharPainel({
              limparTexto: false,
              onFim: () => abrirNoticia(index)
            });
            return;
          }

          abrirNoticia(index);
        });

        cards.push(card);
        grid.appendChild(card);
      });

      limparSelecao();
    })
    .catch((err) => {
      console.error('Erro ao carregar noticias:', err);
      grid.innerHTML = '<p style="color:#fff;">Erro ao carregar noticias.</p>';
      detalheTitulo.textContent = 'Erro';
      detalheTexto.innerHTML = '<p>Nao foi possivel carregar as noticias agora.</p>';
    });

  if (btnFecharDetalhe) {
    btnFecharDetalhe.addEventListener('click', () => {
      limparSelecao();
    });
  }
});
