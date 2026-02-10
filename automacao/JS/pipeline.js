document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('btnAtualizar');
  if (!btn) return;

  btn.addEventListener('click', () => {
    btn.disabled = true;
    btn.textContent = 'Atualizando...';

    fetch('../api/run_pipeline.php')
      .then(r => r.text())
      .then(() => {
        btn.textContent = 'Atualização concluída';

        // 🔄 recarrega listas do admin
        if (window.carregarPendentes) {
          window.carregarPendentes();
        }
        if (window.carregarPublicadas) {
          window.carregarPublicadas();
        }
      })
      .catch(() => {
        btn.textContent = 'Erro ao atualizar';
        btn.disabled = false;
      });
  });
});
