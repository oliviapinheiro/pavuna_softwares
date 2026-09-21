// ============================================================
// AUTENTICAÇÃO (backend PHP + banco de dados)
// Login, cadastro inicial, "esqueci minha senha" e criação de nova senha
// ============================================================

function emailValido(email){
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// ---------- HELPERS DE UI (com atributos ARIA para leitores de tela) ----------
function marcarErro(campoId, mensagem){
  const input = document.getElementById(campoId);
  if (!input) return;
  const campo = input.closest('.auth-field');
  campo.classList.add('field-error');
  input.setAttribute('aria-invalid', 'true');
  const erroEl = document.getElementById(campoId + '-erro') || campo.querySelector('.auth-error');
  if (erroEl){
    erroEl.textContent = mensagem;
    if (!erroEl.id) erroEl.id = campoId + '-erro';
    const atuais = (input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
    if (!atuais.includes(erroEl.id)) atuais.push(erroEl.id);
    input.setAttribute('aria-describedby', atuais.join(' '));
  }
}
function limparErros(form){
  form.querySelectorAll('.auth-field').forEach(campo => {
    campo.classList.remove('field-error');
    const erroEl = campo.querySelector('.auth-error');
    if (erroEl) erroEl.textContent = '';
    campo.querySelectorAll('input, select').forEach(i => i.removeAttribute('aria-invalid'));
  });
}
function focarPrimeiroErro(form){
  const primeiro = form.querySelector('[aria-invalid="true"]');
  if (primeiro) primeiro.focus();
}
function mostrarAlerta(el, texto, tipo){
  if (!el) return;
  el.textContent = texto;
  el.className = `auth-alert show ${tipo}`;
}
function esconderAlerta(el){
  if (!el) return;
  el.textContent = '';
  el.className = 'auth-alert';
}

async function enviarJSON(url, corpo){
  const resp = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(corpo)
  });
  let data = {};
  try { data = await resp.json(); } catch (e) { /* sem corpo JSON */ }
  return { ok: resp.ok, data };
}

// ---------- CADASTRO INICIAL ----------
function inicializarCadastro(){
  const form = document.getElementById('formCadastro');
  if (!form) return;
  const alerta = document.getElementById('cadastroAlerta');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    limparErros(form);
    esconderAlerta(alerta);

    const nome = document.getElementById('nome').value.trim();
    const email = document.getElementById('email').value.trim().toLowerCase();
    const tipo = document.getElementById('tipo').value;
    const senha = document.getElementById('senha').value;
    const confirmarSenha = document.getElementById('confirmarSenha').value;

    let valido = true;
    if (nome.length < 2){ marcarErro('nome', 'Informe seu nome completo.'); valido = false; }
    if (!emailValido(email)){ marcarErro('email', 'Informe um e-mail válido.'); valido = false; }
     if (senha.length < 8){ marcarErro('senha', 'A senha precisa ter ao menos 8 caracteres.'); valido = false; }
    if (confirmarSenha !== senha){ marcarErro('confirmarSenha', 'As senhas não coincidem.'); valido = false; }
    if (!valido){ focarPrimeiroErro(form); return; }

    try{
      const { ok, data } = await enviarJSON('auth/register.php', { nome, email, senha, tipo });
      if (!ok){
        mostrarAlerta(alerta, data.erro || 'Não foi possível criar a conta.', 'error');
        return;
      }
      mostrarAlerta(alerta, 'Conta criada com sucesso! Redirecionando para o login...', 'success');
      form.reset();
      setTimeout(() => { window.location.href = 'login.html'; }, 1500);
    } catch (err){
      mostrarAlerta(alerta, 'Erro de conexão com o servidor. Verifique se o Apache/MySQL estão rodando.', 'error');
    }
  });
}

// ---------- LOGIN ----------
function inicializarLogin(){
  const form = document.getElementById('formLogin');
  if (!form) return;
  const alerta = document.getElementById('loginAlerta');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    limparErros(form);
    esconderAlerta(alerta);

    const email = document.getElementById('email').value.trim().toLowerCase();
    const senha = document.getElementById('senha').value;

    let valido = true;
    if (!emailValido(email)){ marcarErro('email', 'Informe um e-mail válido.'); valido = false; }
    if (senha.length === 0){ marcarErro('senha', 'Informe sua senha.'); valido = false; }
    if (!valido){ focarPrimeiroErro(form); return; }

    try{
      const { ok, data } = await enviarJSON('auth/login.php', { email, senha });
      if (!ok){
        mostrarAlerta(alerta, data.erro || 'E-mail ou senha incorretos.', 'error');
        return;
      }
      mostrarAlerta(alerta, 'Login realizado! Redirecionando...', 'success');
      setTimeout(() => { window.location.href = 'index.php'; }, 800);
    } catch (err){
      mostrarAlerta(alerta, 'Erro de conexão com o servidor. Verifique se o Apache/MySQL estão rodando.', 'error');
    }
  });
}

// ---------- ESQUECI MINHA SENHA ----------
function inicializarEsqueciSenha(){
  const form = document.getElementById('formEsqueci');
  if (!form) return;
  const alerta = document.getElementById('esqueciAlerta');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    limparErros(form);
    esconderAlerta(alerta);

    const email = document.getElementById('email').value.trim().toLowerCase();
    if (!emailValido(email)){
      marcarErro('email', 'Informe um e-mail válido.');
      focarPrimeiroErro(form);
      return;
    }

    try{
      const { ok, data } = await enviarJSON('auth/esqueci_senha.php', { email });
      if (!ok){
        mostrarAlerta(alerta, data.erro || 'Não foi possível enviar o pedido. Tente novamente.', 'error');
        return;
      }
      mostrarAlerta(alerta, data.mensagem || 'Se o e-mail estiver cadastrado, enviaremos um link para redefinir a senha.', 'success');
      form.reset();
    } catch (err){
      mostrarAlerta(alerta, 'Erro de conexão com o servidor. Tente novamente em instantes.', 'error');
    }
  });
}

// ---------- CRIAR NOVA SENHA (link recebido por e-mail) ----------
function inicializarRedefinirSenha(){
  const form = document.getElementById('formRedefinir');
  if (!form) return;
  const alerta = document.getElementById('redefinirAlerta');
  const token = new URLSearchParams(window.location.search).get('token') || '';

  if (!/^[a-f0-9]{64}$/.test(token)){
    mostrarAlerta(alerta, 'Link inválido. Peça um novo link na opção "Esqueci minha senha".', 'error');
    form.querySelectorAll('input, button').forEach(el => { el.disabled = true; });
    return;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    limparErros(form);
    esconderAlerta(alerta);

    const nova = document.getElementById('novaSenha').value;
    const confirmar = document.getElementById('confirmarNovaSenha').value;

    let valido = true;
    if (nova.length < 8){ marcarErro('novaSenha', 'A senha precisa ter ao menos 8 caracteres.'); valido = false; }
    if (confirmar !== nova){ marcarErro('confirmarNovaSenha', 'As senhas não coincidem.'); valido = false; }
    if (!valido){ focarPrimeiroErro(form); return; }

    try{
      const { ok, data } = await enviarJSON('auth/redefinir_senha.php', { token, nova_senha: nova });
      if (!ok){
        mostrarAlerta(alerta, data.erro || 'Não foi possível redefinir a senha.', 'error');
        return;
      }
      mostrarAlerta(alerta, data.mensagem || 'Senha redefinida com sucesso! Redirecionando para o login...', 'success');
      form.reset();
      setTimeout(() => { window.location.href = 'login.html'; }, 2000);
    } catch (err){
      mostrarAlerta(alerta, 'Erro de conexão com o servidor. Tente novamente em instantes.', 'error');
    }
  });
}

// ---------- INIT ----------
inicializarCadastro();
inicializarLogin();
inicializarEsqueciSenha();
inicializarRedefinirSenha();
