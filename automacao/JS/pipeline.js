document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('btnAtualizar');
  if (!btn) return;

  let carregando = false;
  const textoOriginal = (btn.textContent || 'Atualizar noticias').trim();

  function restaurarBotao(delayMs = 2200) {
    setTimeout(() => {
      btn.textContent = textoOriginal;
      btn.style.backgroundColor = '';
      btn.disabled = false;
      carregando = false;
    }, delayMs);
  }

  function recarregarListas() {
    if (typeof window.carregarPendentes === 'function') {
      window.carregarPendentes();
    }
    if (typeof window.carregarPublicadas === 'function') {
      window.carregarPublicadas();
    }
  }

  async function chamarPipeline() {
    const r = await fetch('../api/run_pipeline.php', {
      credentials: 'same-origin',
      cache: 'no-store',
    });

    if (!r.ok) {
      throw new Error(`HTTP ${r.status}`);
    }

    const ct = r.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      throw new Error('Resposta invalida do pipeline');
    }

    return r.json();
  }

  btn.addEventListener('click', async () => {
    if (carregando) return;

    carregando = true;
    btn.disabled = true;
    btn.style.backgroundColor = '';
    btn.textContent = 'Atualizando...';

    try {
      const data = await chamarPipeline();

      if (data && data.status === 'locked') {
        btn.textContent = 'Em andamento';
        btn.style.backgroundColor = '#f59e0b';
        recarregarListas();
        restaurarBotao(1500);
        return;
      }

      if (!data || data.status !== 'ok') {
        throw new Error((data && data.mensagem) || 'Falha ao executar pipeline');
      }

      setTimeout(() => recarregarListas(), 500);
      setTimeout(() => recarregarListas(), 1800);

      const restantes = Number(data.restantes || 0);
      if (restantes > 0) {
        btn.textContent = `Lote ok (${restantes})`;
        btn.style.backgroundColor = '#f59e0b';
        restaurarBotao(2600);
      } else {
        btn.textContent = 'Atualizada';
        btn.style.backgroundColor = '#10b981';
        restaurarBotao(2200);
      }
    } catch (err) {
      console.error('Erro ao atualizar noticias:', err);
      const msg = String((err && err.message) || '');
      if (msg.toLowerCase().includes('configurad')) {
        btn.textContent = 'Configurar integracao';
      } else {
        btn.textContent = 'Falha';
      }
      btn.style.backgroundColor = '#ef4444';
      restaurarBotao(2200);
    }
  });
});
