document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('btnAtualizar');
  if (!btn) return;

  let carregando = false; // Flag para evitar múltiplos cliques

  // Função para mostrar notificação
  function mostrarNotificacao(mensagem, tipo = 'info') {
    // Remove notificação anterior se existir
    const notificacaoAntiga = document.getElementById('notificacao-toast');
    if (notificacaoAntiga) notificacaoAntiga.remove();

    const notificacao = document.createElement('div');
    notificacao.id = 'notificacao-toast';
    notificacao.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: ${tipo === 'aviso' ? '#f59e0b' : tipo === 'erro' ? '#ef4444' : tipo === 'sucesso' ? '#10b981' : '#3b82f6'};
      color: white;
      padding: 16px 24px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      z-index: 9999;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      animation: slideIn 0.3s ease forwards;
      transition: opacity 0.3s ease;
    `;
    notificacao.textContent = mensagem;
    document.body.appendChild(notificacao);

    // Remove após 3 segundos com transição suave
    setTimeout(() => {
      notificacao.style.animation = 'slideOut 0.3s ease forwards';
      setTimeout(() => {
        if (notificacao.parentNode) {
          notificacao.remove();
        }
      }, 300);
    }, 3000);
  }

  // Adiciona animações CSS
  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideIn {
      from {
        transform: translateX(450px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
    @keyframes slideOut {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(450px);
        opacity: 0;
      }
    }
  `;
  document.head.appendChild(style);

  btn.addEventListener('click', () => {
    // Bloqueia se já está carregando
    if (carregando) {
      mostrarNotificacao('⏳ Aguarde! A atualização já está em andamento...', 'aviso');
      return;
    }

    carregando = true;
    btn.disabled = true;
    btn.textContent = 'Atualizando...';

    fetch('../api/run_pipeline.php')
      .then(async (r) => {
        if (!r.ok) {
          throw new Error(`Erro HTTP: ${r.status}`);
        }
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          throw new Error('Resposta invalida do pipeline');
        }
        return r.json();
      })
      .then((data) => {
        if (!data || data.status !== 'ok') {
          throw new Error((data && data.mensagem) || 'Falha ao executar pipeline');
        }
        console.log('Pipeline executado:', data);

        // Aguarda um pouco para garantir que o banco de dados foi atualizado
        setTimeout(() => {
          // Recarrega as listas do admin
          if (window.carregarPendentes && window.carregarPublicadas) {
            console.log('Recarregando notícias...');
            window.carregarPendentes();
            window.carregarPublicadas();

            btn.textContent = 'Atualização concluída!';
            btn.style.backgroundColor = '#10b981';

            // Retorna ao estado normal após 3 segundos
            setTimeout(() => {
              btn.textContent = 'Atualizar notícias';
              btn.style.backgroundColor = '';
              btn.disabled = false;
              carregando = false;
            }, 3000);
          } else {
            btn.textContent = 'Atualizar notícias';
            btn.disabled = false;
            carregando = false;
          }
        }, 500);
      })
      .catch((err) => {
        console.error('Erro:', err);
        btn.textContent = 'Erro ao atualizar';
        btn.style.backgroundColor = '#ef4444';
        mostrarNotificacao('❌ Erro ao atualizar notícias', 'erro');

        // Retorna ao estado normal após 3 segundos
        setTimeout(() => {
          btn.textContent = 'Atualizar notícias';
          btn.style.backgroundColor = '';
          btn.disabled = false;
          carregando = false;
        }, 3000);
      });
  });
});
