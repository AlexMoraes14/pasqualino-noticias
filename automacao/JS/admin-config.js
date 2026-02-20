document.addEventListener('DOMContentLoaded', () => {
  const btnConfig = document.getElementById('btnConfig');
  const configModal = document.getElementById('configModal');
  const configOverlay = document.getElementById('configOverlay');
  const btnFechar = document.getElementById('btnConfigFechar');
  const btnTestar = document.getElementById('btnConfigTestar');
  const btnSalvar = document.getElementById('btnConfigSalvar');
  const statusEl = document.getElementById('configStatus');

  if (!btnConfig || !configModal || !configOverlay || !btnFechar || !btnTestar || !btnSalvar || !statusEl) {
    return;
  }

  const fields = {
    wpBaseUrl: document.getElementById('cfgWpBaseUrl'),
    wpRestUser: document.getElementById('cfgWpRestUser'),
    wpAppPassword: document.getElementById('cfgWpAppPassword'),
    wpAuthorId: document.getElementById('cfgWpAuthorId'),
    wpTimeout: document.getElementById('cfgWpTimeout'),
    wpCategoryFederal: document.getElementById('cfgWpCategoryFederal'),
    wpCategoryTrabalhista: document.getElementById('cfgWpCategoryTrabalhista'),
    wpCategoryComex: document.getElementById('cfgWpCategoryComex'),
    dbHost: document.getElementById('cfgDbHost'),
    dbPort: document.getElementById('cfgDbPort'),
    dbCharset: document.getElementById('cfgDbCharset'),
    dbName: document.getElementById('cfgDbName'),
    dbUser: document.getElementById('cfgDbUser'),
    dbPassword: document.getElementById('cfgDbPassword'),
    dbTableNoticias: document.getElementById('cfgDbTableNoticias'),
    dbTableExecucoes: document.getElementById('cfgDbTableExecucoes'),
  };

  function setStatus(texto, tipo = '') {
    statusEl.textContent = texto || '';
    statusEl.classList.remove('ok', 'erro', 'aviso');
    if (tipo) {
      statusEl.classList.add(tipo);
    }
  }

  function abrirModal() {
    configModal.classList.add('show');
    configOverlay.classList.add('show');
  }

  function fecharModal() {
    configModal.classList.remove('show');
    configOverlay.classList.remove('show');
    setStatus('');
    fields.wpAppPassword.value = '';
    fields.dbPassword.value = '';
  }

  function preencherCampos(cfg) {
    fields.wpBaseUrl.value = cfg.wp_base_url || '';
    fields.wpRestUser.value = cfg.wp_rest_user || '';
    fields.wpAuthorId.value = Number(cfg.wp_author_id || 0);
    fields.wpTimeout.value = Number(cfg.wp_timeout || 20);
    fields.wpCategoryFederal.value = Number(cfg.wp_category_federal || 0);
    fields.wpCategoryTrabalhista.value = Number(cfg.wp_category_trabalhista || 0);
    fields.wpCategoryComex.value = Number(cfg.wp_category_comex || 0);
    fields.dbHost.value = cfg.db_host || '';
    fields.dbPort.value = Number(cfg.db_port || 3306);
    fields.dbCharset.value = cfg.db_charset || 'utf8mb4';
    fields.dbName.value = cfg.db_name || '';
    fields.dbUser.value = cfg.db_user || '';
    fields.dbTableNoticias.value = cfg.db_table_noticias || 'cnp_noticias';
    fields.dbTableExecucoes.value = cfg.db_table_execucoes || 'cnp_pipeline_execucoes';

    fields.wpAppPassword.value = '';
    fields.dbPassword.value = '';
    fields.wpAppPassword.placeholder = cfg.wp_app_password_configured
      ? 'Ja configurada (deixe em branco para manter)'
      : 'Cole aqui a senha de aplicacao';
    fields.dbPassword.placeholder = cfg.db_password_configured
      ? 'Ja configurada (deixe em branco para manter)'
      : 'Cole aqui a senha do banco externo';
  }

  function coletarPayload() {
    return new URLSearchParams({
      wp_base_url: (fields.wpBaseUrl.value || '').trim(),
      wp_rest_user: (fields.wpRestUser.value || '').trim(),
      wp_app_password: (fields.wpAppPassword.value || '').trim(),
      wp_author_id: (fields.wpAuthorId.value || '0').trim(),
      wp_timeout: (fields.wpTimeout.value || '20').trim(),
      wp_category_federal: (fields.wpCategoryFederal.value || '0').trim(),
      wp_category_trabalhista: (fields.wpCategoryTrabalhista.value || '0').trim(),
      wp_category_comex: (fields.wpCategoryComex.value || '0').trim(),
      db_host: (fields.dbHost.value || '').trim(),
      db_port: (fields.dbPort.value || '3306').trim(),
      db_charset: (fields.dbCharset.value || 'utf8mb4').trim(),
      db_name: (fields.dbName.value || '').trim(),
      db_user: (fields.dbUser.value || '').trim(),
      db_password: (fields.dbPassword.value || '').trim(),
      db_table_noticias: (fields.dbTableNoticias.value || 'cnp_noticias').trim(),
      db_table_execucoes: (fields.dbTableExecucoes.value || 'cnp_pipeline_execucoes').trim(),
    });
  }

  async function carregarConfiguracao() {
    const r = await fetch('../api/integracao_obter.php', { credentials: 'same-origin', cache: 'no-store' });
    const data = await r.json();
    if (!r.ok || !data.success) {
      throw new Error((data && data.message) || `HTTP ${r.status}`);
    }
    preencherCampos(data.config || {});
  }

  async function salvarConfiguracao() {
    const r = await fetch('../api/integracao_salvar.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: coletarPayload().toString(),
    });
    const data = await r.json();
    if (!r.ok || !data.success) {
      throw new Error((data && data.message) || `HTTP ${r.status}`);
    }
    preencherCampos(data.config || {});
    return data;
  }

  async function testarConfiguracao() {
    const r = await fetch('../api/integracao_testar.php', {
      credentials: 'same-origin',
      cache: 'no-store',
    });
    const data = await r.json();
    if (!r.ok) {
      throw new Error((data && data.message) || `HTTP ${r.status}`);
    }
    return data;
  }

  btnConfig.addEventListener('click', async () => {
    setStatus('Carregando configuracoes...', 'aviso');
    abrirModal();
    try {
      await carregarConfiguracao();
      setStatus('Configuracoes carregadas.', 'ok');
    } catch (err) {
      setStatus(`Erro ao carregar configuracoes: ${err.message}`, 'erro');
    }
  });

  btnSalvar.addEventListener('click', async () => {
    setStatus('Salvando configuracoes...', 'aviso');
    btnSalvar.disabled = true;
    try {
      await salvarConfiguracao();
      setStatus('Configuracoes salvas com sucesso.', 'ok');
    } catch (err) {
      setStatus(`Erro ao salvar configuracoes: ${err.message}`, 'erro');
    } finally {
      btnSalvar.disabled = false;
    }
  });

  btnTestar.addEventListener('click', async () => {
    setStatus('Testando conexoes...', 'aviso');
    btnTestar.disabled = true;
    try {
      const resultado = await testarConfiguracao();
      const dbMsg = resultado.db && resultado.db.message ? resultado.db.message : 'Teste de banco sem retorno.';
      const wpMsg = resultado.wp && resultado.wp.message ? resultado.wp.message : 'Teste WordPress sem retorno.';

      if (resultado.success) {
        setStatus(`${dbMsg} ${wpMsg}`, 'ok');
      } else {
        setStatus(`${dbMsg} ${wpMsg}`, 'erro');
      }
    } catch (err) {
      setStatus(`Falha no teste de conexao: ${err.message}`, 'erro');
    } finally {
      btnTestar.disabled = false;
    }
  });

  btnFechar.addEventListener('click', fecharModal);
  configOverlay.addEventListener('click', fecharModal);
});

