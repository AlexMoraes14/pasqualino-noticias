document.addEventListener('DOMContentLoaded', () => {

  const tabs = document.querySelectorAll('.tab');
  const contents = document.querySelectorAll('.tab-content');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {

      // remove estado ativo de tudo
      tabs.forEach(t => t.classList.remove('active'));
      contents.forEach(c => c.classList.remove('active'));

      // ativa o botão clicado
      tab.classList.add('active');

      // ativa a section correta
      const alvo = tab.dataset.tab;
      const section = document.getElementById(alvo);

      if (section) {
        section.classList.add('active');
      }
    });
  });

});
