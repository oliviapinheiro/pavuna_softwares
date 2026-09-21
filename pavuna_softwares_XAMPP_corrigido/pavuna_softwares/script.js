'use strict';

// ==============================================================================
// PAVUNA SOFTWARES — front-end
// ==============================================================================
const SESSAO = window.PAVUNA_SESSAO || {};
const EH_ADM = SESSAO.tipo === 'coordenador';
const VE_LISTAS = SESSAO.tipo === 'coordenador' || SESSAO.tipo === 'instrutor';

const STATUS_LABEL = { confirmada: 'Confirmada', reposicao: 'Reposição', cancelada: 'Cancelada' };
const TURNO_LABEL = { manha: 'Manhã', tarde: 'Tarde', noite: 'Noite' };
const DIA_LABEL = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
const TITULOS = {
  'grade': 'Grade da semana',
  'instrutores': 'Consulta de instrutores',
  'alunos': 'Consulta de alunos',
  'relatorios': 'Relatório de instrutor',
  'cadastro-instrutor': 'Cadastrar instrutor',
  'cadastro-aluno': 'Cadastrar aluno'
};
const NOME_SISTEMA = 'Pavuna Softwares';

let TURMAS = [];
let AULAS = [];
let INSTRUTORES = [];
let ALUNOS = [];

// Guarda, para cada aba, exatamente o que está sendo exibido (usado nos relatórios)
const RELATORIO = {};

// ============ UTILITÁRIOS ============
const $ = (sel, raiz = document) => raiz.querySelector(sel);
const $$ = (sel, raiz = document) => Array.from(raiz.querySelectorAll(sel));

function esc(valor) {
  return String(valor ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

const pad = n => String(n).padStart(2, '0');
const toISO = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const fromISO = s => { const [a, m, d] = s.split('-').map(Number); return new Date(a, m - 1, d); };
const fmtBR = d => `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
const fmtISOBR = s => (s ? fmtBR(fromISO(s)) : '');
const dataValida = v => (/^\d{4}-\d{2}-\d{2}$/.test(v || '') ? v : '');

function addDias(d, n) {
  const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  x.setDate(x.getDate() + n);
  return x;
}

function segundaDaSemana(d) {
  const dia = d.getDay(); // 0 = domingo
  return addDias(d, dia === 0 ? -6 : 1 - dia);
}

// Quantas vezes o dia da semana (0 = segunda … 5 = sábado) ocorre entre ini e fim (inclusive)
function contarDiaSemana(idx, ini, fim) {
  if (fim < ini) return 0;
  const alvo = (idx + 1) % 7; // getDay(): segunda = 1 … sábado = 6
  const total = Math.round((fim - ini) / 86400000) + 1;
  const deslocamento = (alvo - ini.getDay() + 7) % 7;
  if (deslocamento >= total) return 0;
  return Math.floor((total - 1 - deslocamento) / 7) + 1;
}

function fmtHoras(h) {
  const n = Math.round(h * 10) / 10;
  return (Number.isInteger(n) ? String(n) : n.toFixed(1).replace('.', ',')) + ' h';
}

const vigenteEm = (a, iso) => (!a.data_inicio || a.data_inicio <= iso) && (!a.data_fim || a.data_fim >= iso);

function textoVigencia(a) {
  if (!a.data_inicio && !a.data_fim) return 'Sem período definido';
  return `${a.data_inicio ? fmtISOBR(a.data_inicio) : '—'} a ${a.data_fim ? fmtISOBR(a.data_fim) : '—'}`;
}

const nomeComStatus = p => p.nome + (Number(p.ativo) === 0 ? ' (inativo)' : '');

function ordenarNatural(lista) {
  return lista.slice().sort((a, b) => String(a).localeCompare(String(b), 'pt-BR', { numeric: true }));
}

// ============ AVISOS E ANÚNCIOS PARA LEITOR DE TELA ============
let timerAviso = null;
function mostrarAviso(texto, tipo = 'info') {
  const el = $('#avisoGlobal');
  if (!el) return;
  el.textContent = texto;
  el.className = 'aviso' + (tipo === 'erro' ? ' aviso-erro' : '');
  el.hidden = false;
  clearTimeout(timerAviso);
  timerAviso = setTimeout(() => { el.hidden = true; }, 8000);
}

function anunciar(texto) {
  const el = $('#anuncioSr');
  if (!el) return;
  el.textContent = '';
  setTimeout(() => { el.textContent = texto; }, 60);
}

// ============ REDE ============
async function pegarJSON(url, opcoes) {
  const config = { credentials: 'same-origin', ...opcoes };
  const metodo = String(config.method || 'GET').toUpperCase();
  const headers = new Headers(config.headers || {});
  if (metodo !== 'GET' && SESSAO.csrf) headers.set('X-CSRF-Token', SESSAO.csrf);
  config.headers = headers;
  const resp = await fetch(url, config);
  if (resp.status === 401) {
    window.location.href = 'login.html';
    throw new Error('Sessão expirada. Faça login novamente.');
  }
  let dados = null;
  try { dados = await resp.json(); } catch (e) { /* resposta sem JSON */ }
  if (!resp.ok) throw new Error((dados && (dados.erro || dados.message)) || 'Erro ao comunicar com o servidor.');
  return dados;
}

async function postAcao(formData) {
  if (SESSAO.csrf) formData.append('csrf_token', SESSAO.csrf);
  const resp = await fetch('index.php', { method: 'POST', body: formData, credentials: 'same-origin' });
  try {
    const data = await resp.json();
    if (data.success === undefined && data.sucesso !== undefined) {
      data.success = data.sucesso;
      data.message = data.message || data.mensagem || data.erro;
    }
    return data;
  } catch (e) {
    return { success: false, message: 'Resposta inesperada do servidor.' };
  }
}

// ============ NAVEGAÇÃO ENTRE ABAS ============
function fecharMenuMobile() {
  const painel = $('#mobilePanel');
  const botao = $('#menuToggle');
  if (painel) painel.classList.remove('open');
  if (botao) {
    botao.setAttribute('aria-expanded', 'false');
    botao.setAttribute('aria-label', 'Abrir menu de navegação');
  }
}

function irPara(view, moverFoco = true) {
  const alvo = document.getElementById('view-' + view);
  if (!alvo) return;
  $$('.view').forEach(v => v.classList.toggle('active', v === alvo));
  $$('.tab, .mobile-link[data-view]').forEach(b => {
    const ativo = b.dataset.view === view;
    b.classList.toggle('active', ativo);
    if (ativo) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
  });
  fecharMenuMobile();
  document.title = `${TITULOS[view] || 'Início'} · ${NOME_SISTEMA}`;
  window.scrollTo(0, 0);
  if (moverFoco) {
    const titulo = $('h1', alvo);
    if (titulo) titulo.focus();
  }
}

function viewAtiva() {
  const v = $('.view.active');
  return v ? v.id.replace('view-', '') : 'grade';
}

// ============ MODAIS (foco preso, Esc fecha, foco volta ao botão de origem) ============
let modalAtivo = null;
let gatilhoModal = null;

function definirInerte(valor) {
  ['.topbar', '.mobile-panel', 'main'].forEach(sel => {
    const el = $(sel);
    if (el) el.inert = valor;
  });
}

function abrirModal(overlay, gatilho) {
  if (!overlay) return;
  if (modalAtivo) fecharModal(false);
  modalAtivo = overlay;
  gatilhoModal = gatilho || document.activeElement;
  overlay.hidden = false;
  document.body.classList.add('modal-aberto');
  definirInerte(true);
  const foco = overlay.querySelector('[data-foco-inicial]') || overlay.querySelector('.modal');
  if (foco) foco.focus();
}

function fecharModal(devolverFoco = true) {
  if (!modalAtivo) return;
  modalAtivo.hidden = true;
  document.body.classList.remove('modal-aberto');
  definirInerte(false);
  const g = gatilhoModal;
  modalAtivo = null;
  gatilhoModal = null;
  if (devolverFoco && g && document.contains(g) && !g.closest('[hidden]')) g.focus();
}

document.addEventListener('keydown', e => {
  if (!modalAtivo) return;
  if (e.key === 'Escape') {
    e.preventDefault();
    fecharModal();
    return;
  }
  if (e.key === 'Tab') {
    const focaveis = $$('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', modalAtivo)
      .filter(el => !el.closest('[hidden]'));
    if (!focaveis.length) return;
    const primeiro = focaveis[0];
    const ultimo = focaveis[focaveis.length - 1];
    if (e.shiftKey && document.activeElement === primeiro) { e.preventDefault(); ultimo.focus(); }
    else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primeiro.focus(); }
  }
});

['detailOverlay', 'senhaOverlay'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', e => {
    if (e.target.id === id) fecharModal();
  });
});

// ============ CARREGAMENTO DE DADOS ============
async function carregarDados() {
  try {
    const dadosAulas = await pegarJSON('api/aulas.php');
    TURMAS = dadosAulas.turmas || [];
    AULAS = dadosAulas.aulas || [];
  } catch (e) {
    mostrarAviso(e.message, 'erro');
  }

  if (VE_LISTAS) {
    try { INSTRUTORES = await pegarJSON('api/instrutores.php'); } catch (e) { INSTRUTORES = []; mostrarAviso(e.message, 'erro'); }
    try { ALUNOS = await pegarJSON('api/alunos.php'); } catch (e) { ALUNOS = []; mostrarAviso(e.message, 'erro'); }
    if (!Array.isArray(INSTRUTORES)) INSTRUTORES = [];
    if (!Array.isArray(ALUNOS)) ALUNOS = [];
  }

  popularFiltrosGrade();
  renderGrade();

  if (VE_LISTAS) {
    popularSelectInstrutores();
    popularFiltrosAlunos();
    popularSelectsTurma();
    renderInstrutor();
    renderAlunos();
    if ($('#relatorioInstrutor')) popularSelectRelatorioInstrutor();
  }
}

function preencherSelect(sel, opcoes, textoTodos) {
  if (!sel) return;
  const atual = sel.value;
  sel.innerHTML = `<option value="todos">${esc(textoTodos)}</option>` +
    opcoes.map(o => `<option value="${esc(o.valor)}">${esc(o.texto)}</option>`).join('');
  if (Array.from(sel.options).some(o => o.value === atual)) sel.value = atual;
}

// ============ GRADE DA SEMANA ============
function popularFiltrosGrade() {
  const instrutores = new Map();
  AULAS.forEach(a => { if (a.instrutor_id != null) instrutores.set(String(a.instrutor_id), a.instrutor_nome); });
  preencherSelect($('#filtroInstrutorGrade'),
    Array.from(instrutores, ([valor, texto]) => ({ valor, texto })).sort((a, b) => a.texto.localeCompare(b.texto, 'pt-BR')),
    'Todos os instrutores');

  const turmas = TURMAS.length ? TURMAS : Array.from(new Map(AULAS.map(a => [a.turma_id, { id: a.turma_id, codigo: a.turma_codigo, nome: a.turma_nome }])).values());
  preencherSelect($('#filtroTurmaGrade'), turmas.map(t => ({ valor: t.id, texto: `${t.codigo} · ${t.nome}` })), 'Todas as turmas');

  const salas = ordenarNatural(Array.from(new Set(AULAS.map(a => a.sala).filter(Boolean))));
  preencherSelect($('#filtroSalaGrade'), salas.map(s => ({ valor: s, texto: s })), 'Todas as salas');
}

function filtrosGrade() {
  return {
    turno: $('#filtroTurno')?.value || 'todos',
    dia: $('#filtroDiaSemana')?.value || 'todos',
    instrutor: $('#filtroInstrutorGrade')?.value || 'todos',
    turma: $('#filtroTurmaGrade')?.value || 'todos',
    sala: $('#filtroSalaGrade')?.value || 'todos'
  };
}

function passaFiltrosGrade(a, f) {
  return (f.instrutor === 'todos' || String(a.instrutor_id) === f.instrutor)
    && (f.turma === 'todos' || String(a.turma_id) === f.turma)
    && (f.sala === 'todos' || a.sala === f.sala);
}

function textoSelecionado(sel) {
  return sel && sel.value !== 'todos' ? sel.options[sel.selectedIndex].text : '';
}

function renderGrade() {
  const corpo = $('#corpoGrade');
  if (!corpo) return;

  const dataISO = dataValida($('#filtroData').value) || toISO(new Date());
  const segunda = segundaDaSemana(fromISO(dataISO));
  const sabado = addDias(segunda, 5);
  const hojeISO = toISO(new Date());
  const f = filtrosGrade();

  ['manha', 'tarde', 'noite'].forEach(t => {
    const th = $('#th-' + t);
    if (th) th.hidden = !(f.turno === 'todos' || f.turno === t);
  });

  corpo.innerHTML = '';
  const linhasCsv = [];
  let total = 0;

  for (let idx = 0; idx < 6; idx++) {
    if (f.dia !== 'todos' && Number(f.dia) !== idx) continue;

    const data = addDias(segunda, idx);
    const iso = toISO(data);
    const tr = document.createElement('tr');
    if (iso === dataISO) tr.classList.add('selecionado');
    if (iso === hojeISO) tr.classList.add('hoje');

    const th = document.createElement('th');
    th.scope = 'row';
    th.className = 'col-dia';
    th.innerHTML = `${esc(DIA_LABEL[idx])}<small>${fmtBR(data)}</small>` + (iso === hojeISO ? '<span class="tag-hoje">Hoje</span>' : '');
    if (iso === dataISO) th.setAttribute('aria-current', 'date');
    tr.appendChild(th);

    ['manha', 'tarde', 'noite'].forEach(turno => {
      const td = document.createElement('td');
      if (!(f.turno === 'todos' || f.turno === turno)) {
        td.hidden = true;
        tr.appendChild(td);
        return;
      }

      const aulas = AULAS.filter(a => Number(a.dia_semana) === idx && a.turno === turno && vigenteEm(a, iso) && passaFiltrosGrade(a, f));

      if (!aulas.length) {
        td.innerHTML = '<span class="chip-empty"><span aria-hidden="true">—</span><span class="sr-only">Sem aulas</span></span>';
      } else {
        aulas.forEach(a => {
          total++;
          const chip = document.createElement('button');
          chip.type = 'button';
          chip.className = `chip shift-${a.turno} status-${a.status}`;
          chip.setAttribute('aria-haspopup', 'dialog');
          const materiaDistinta = a.materia && a.materia !== a.turma_nome;
          chip.innerHTML =
            `<span class="chip-turma">${esc(a.turma_codigo)} · ${esc(a.turma_nome)}</span>` +
            (materiaDistinta ? `<span class="chip-materia">${esc(a.materia)}</span>` : '') +
            `<span class="chip-meta">${esc(a.instrutor_nome || 'Sem instrutor definido')} · Sala ${esc(a.sala || '-')}</span>` +
            `<span class="badge badge-${esc(a.status)}">${esc(STATUS_LABEL[a.status] || a.status)}</span>`;
          chip.addEventListener('click', () => abrirDetalhe(a, data, chip));
          td.appendChild(chip);

          linhasCsv.push([
            fmtBR(data), DIA_LABEL[idx], TURNO_LABEL[a.turno], `${a.turma_codigo} - ${a.turma_nome}`,
            a.materia || '', a.instrutor_nome || '', a.sala || '', STATUS_LABEL[a.status] || a.status
          ]);
        });
      }
      tr.appendChild(td);
    });

    corpo.appendChild(tr);
  }

  const periodo = `Semana de ${fmtBR(segunda)} a ${fmtBR(sabado)}`;
  const resumo = `${periodo} · ${total} ${total === 1 ? 'aula encontrada' : 'aulas encontradas'}.`;
  const pl = $('#periodoGrade');
  if (pl) pl.textContent = resumo;
  const cap = $('#capGrade');
  if (cap) cap.textContent = `Grade semanal de aulas por dia e turno. ${periodo}.`;
  const vazio = $('#vazioGrade');
  if (vazio) vazio.hidden = total > 0;

  const filtrosTxt = [
    f.turno !== 'todos' ? `Turno: ${TURNO_LABEL[f.turno]}` : '',
    f.dia !== 'todos' ? `Dia: ${DIA_LABEL[Number(f.dia)]}` : '',
    textoSelecionado($('#filtroInstrutorGrade')) && `Instrutor: ${textoSelecionado($('#filtroInstrutorGrade'))}`,
    textoSelecionado($('#filtroTurmaGrade')) && `Turma: ${textoSelecionado($('#filtroTurmaGrade'))}`,
    textoSelecionado($('#filtroSalaGrade')) && `Sala: ${textoSelecionado($('#filtroSalaGrade'))}`
  ].filter(Boolean).join(' · ') || 'Sem filtros adicionais';

  RELATORIO.grade = {
    titulo: 'Grade da semana',
    subtitulo: `${periodo}. ${filtrosTxt}`,
    arquivo: `grade-semana_${toISO(segunda)}.csv`,
    linhas: [
      ['Relatório: Grade da semana'],
      ['Período', `${fmtBR(segunda)} a ${fmtBR(sabado)}`],
      ['Filtros', filtrosTxt],
      [],
      ['Data', 'Dia', 'Turno', 'Turma', 'Matéria', 'Instrutor', 'Sala', 'Status'],
      ...linhasCsv
    ]
  };
}

function definirDataGrade(iso) {
  $('#filtroData').value = iso;
  renderGrade();
  anunciar($('#periodoGrade').textContent);
}

function moverSemana(delta) {
  const atual = dataValida($('#filtroData').value) || toISO(new Date());
  definirDataGrade(toISO(addDias(fromISO(atual), delta * 7)));
}

// ============ DETALHE DA AULA ============
function abrirDetalhe(aula, data, gatilho) {
  $('#detalheTitulo').textContent = `${aula.turma_codigo} · ${aula.turma_nome}`;
  const linhas = [
    ['Data', `${DIA_LABEL[Number(aula.dia_semana)]}, ${fmtBR(data)}`],
    ['Turno', TURNO_LABEL[aula.turno]],
    ['Matéria', aula.materia || aula.turma_nome],
    ['Instrutor', aula.instrutor_nome || 'Não definido'],
    ['Sala', aula.sala || '-'],
    ['Vigência da aula', textoVigencia(aula)],
    ['Duração do encontro', fmtHoras(Number(aula.horas_aula) || 0)],
    ['Status', STATUS_LABEL[aula.status] || aula.status]
  ];
  $('#detalheCorpo').innerHTML = '<dl class="detail-list">' +
    linhas.map(([k, v]) => `<div class="detail-row"><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`).join('') + '</dl>';
  abrirModal($('#detailOverlay'), gatilho);
}

// ============ INSTRUTORES ============
function popularSelectInstrutores() {
  preencherSelect($('#filtroInstrutor'), INSTRUTORES.map(i => ({ valor: i.id, texto: nomeComStatus(i) })), 'Todos os instrutores');
  atualizarBotaoStatus('instrutor');
}

function definirPeriodoPadraoInstrutor() {
  const hoje = new Date();
  $('#instrDataIni').value = toISO(new Date(hoje.getFullYear(), hoje.getMonth(), 1));
  $('#instrDataFim').value = toISO(new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0));
}

function renderInstrutor() {
  const sel = $('#filtroInstrutor');
  if (!sel) return;

  const id = sel.value;
  const ini = dataValida($('#instrDataIni').value);
  const fim = dataValida($('#instrDataFim').value);
  const erroEl = $('#erroPeriodoInstr');
  const tbody = $('#tabelaInstrutor tbody');
  const tbodyCarga = $('#tabelaCarga tbody');
  const tfootCarga = $('#tabelaCarga tfoot');
  const resumoEl = $('#resumoInstrutor');
  const vazioEl = $('#vazioInstrutor');

  tbody.innerHTML = '';
  tbodyCarga.innerHTML = '';
  tfootCarga.innerHTML = '';
  RELATORIO.instrutores = null;

  let erro = '';
  if (!ini || !fim) erro = 'Informe a data inicial e a data final para calcular a carga horária.';
  else if (fim < ini) erro = 'A data final precisa ser igual ou posterior à data inicial.';
  erroEl.hidden = !erro;
  erroEl.textContent = erro;
  if (erro) {
    resumoEl.textContent = '';
    vazioEl.hidden = true;
    return;
  }

  const selecionadas = AULAS.filter(a =>
    (id === 'todos' || String(a.instrutor_id) === id)
    && (!a.data_fim || a.data_fim >= ini)
    && (!a.data_inicio || a.data_inicio <= fim));

  const linhas = selecionadas.map(a => {
    const iniEf = a.data_inicio && a.data_inicio > ini ? a.data_inicio : ini;
    const fimEf = a.data_fim && a.data_fim < fim ? a.data_fim : fim;
    const ocorrencias = contarDiaSemana(Number(a.dia_semana), fromISO(iniEf), fromISO(fimEf));
    const conta = a.status !== 'cancelada';
    const horasAula = Number(a.horas_aula) || 0;
    return { a, aulas: conta ? ocorrencias : 0, horas: conta ? ocorrencias * horasAula : 0 };
  });

  vazioEl.hidden = linhas.length > 0;

  linhas.forEach(({ a, horas }) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(a.instrutor_nome || 'Sem instrutor')}</td>
      <td>${esc(DIA_LABEL[Number(a.dia_semana)])}</td>
      <td>${esc(TURNO_LABEL[a.turno])}</td>
      <td>${esc(a.turma_codigo)} · ${esc(a.turma_nome)}</td>
      <td>${esc(a.materia)}</td>
      <td class="mono">${esc(a.sala || '-')}</td>
      <td>${esc(textoVigencia(a))}</td>
      <td><span class="badge badge-${esc(a.status)}">${esc(STATUS_LABEL[a.status] || a.status)}</span></td>
      <td class="num">${esc(fmtHoras(horas))}</td>`;
    tbody.appendChild(tr);
  });

  // Carga horária por matéria (instrutor + matéria + turma)
  const grupos = new Map();
  linhas.forEach(({ a, aulas, horas }) => {
    const chave = `${a.instrutor_id}|${a.materia}|${a.turma_id}`;
    if (!grupos.has(chave)) {
      grupos.set(chave, { instrutor: a.instrutor_nome || 'Sem instrutor', materia: a.materia, turma: `${a.turma_codigo} · ${a.turma_nome}`, aulas: 0, horas: 0 });
    }
    const g = grupos.get(chave);
    g.aulas += aulas;
    g.horas += horas;
  });
  const resumo = Array.from(grupos.values()).sort((x, y) => x.instrutor.localeCompare(y.instrutor, 'pt-BR') || x.materia.localeCompare(y.materia, 'pt-BR'));
  const totalAulas = resumo.reduce((s, g) => s + g.aulas, 0);
  const totalHoras = resumo.reduce((s, g) => s + g.horas, 0);

  resumo.forEach(g => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${esc(g.instrutor)}</td><td>${esc(g.materia)}</td><td>${esc(g.turma)}</td><td class="num">${g.aulas}</td><td class="num">${esc(fmtHoras(g.horas))}</td>`;
    tbodyCarga.appendChild(tr);
  });
  if (resumo.length) {
    tfootCarga.innerHTML = `<tr><th scope="row" colspan="3">Total no período</th><td class="num">${totalAulas}</td><td class="num">${esc(fmtHoras(totalHoras))}</td></tr>`;
  }

  const nomeSel = textoSelecionado(sel) || 'Todos os instrutores';
  const periodo = `${fmtBR(fromISO(ini))} a ${fmtBR(fromISO(fim))}`;
  resumoEl.textContent = `${nomeSel} · Período de ${periodo} · ${linhas.length} ${linhas.length === 1 ? 'aula semanal' : 'aulas semanais'} · Carga horária total: ${fmtHoras(totalHoras)} (aulas canceladas não entram no cálculo).`;

  RELATORIO.instrutores = {
    titulo: 'Consulta de instrutores',
    subtitulo: `${nomeSel}. Período de ${periodo}. Carga horária total: ${fmtHoras(totalHoras)}.`,
    arquivo: `instrutores_${ini}_a_${fim}.csv`,
    linhas: [
      ['Relatório: Instrutores'],
      ['Instrutor', nomeSel],
      ['Período', periodo],
      ['Observação', 'Aulas canceladas não entram na carga horária'],
      [],
      ['Carga horária por matéria'],
      ['Instrutor', 'Matéria', 'Turma', 'Aulas no período', 'Carga horária (h)'],
      ...resumo.map(g => [g.instrutor, g.materia, g.turma, g.aulas, String(Math.round(g.horas * 10) / 10).replace('.', ',')]),
      ['Total no período', '', '', totalAulas, String(Math.round(totalHoras * 10) / 10).replace('.', ',')],
      [],
      ['Aulas no período'],
      ['Instrutor', 'Dia', 'Turno', 'Turma', 'Matéria', 'Sala', 'Vigência', 'Status', 'Carga horária no período (h)'],
      ...linhas.map(({ a, horas }) => [
        a.instrutor_nome || 'Sem instrutor', DIA_LABEL[Number(a.dia_semana)], TURNO_LABEL[a.turno], `${a.turma_codigo} - ${a.turma_nome}`,
        a.materia, a.sala || '', textoVigencia(a), STATUS_LABEL[a.status] || a.status, String(Math.round(horas * 10) / 10).replace('.', ',')
      ])
    ]
  };
}

// ============ ALUNOS ============
function popularSelectAlunos() {
  preencherSelect($('#filtroAlunoSelect'), ALUNOS.map(a => ({ valor: a.id, texto: nomeComStatus(a) })), 'Todos os alunos');
  atualizarBotaoStatus('aluno');
}

function popularFiltrosAlunos() {
  popularSelectAlunos();
  preencherSelect($('#filtroTurmaAluno'), TURMAS.map(t => ({ valor: t.id, texto: `${t.codigo} · ${t.nome}` })), 'Todas as turmas');
  const salas = ordenarNatural(Array.from(new Set(AULAS.map(a => a.sala).filter(Boolean))));
  preencherSelect($('#filtroSalaAluno'), salas.map(s => ({ valor: s, texto: s })), 'Todas as salas');
}

// turma_id -> conjunto de salas em que a turma tem aula
function salasPorTurma() {
  const mapa = new Map();
  AULAS.forEach(a => {
    if (!a.sala) return;
    const k = String(a.turma_id);
    if (!mapa.has(k)) mapa.set(k, new Set());
    mapa.get(k).add(a.sala);
  });
  return mapa;
}

function renderAlunos() {
  const wrap = $('#listaAlunos');
  if (!wrap) return;

  const alunoSel = $('#filtroAlunoSelect').value;
  const turmaSel = $('#filtroTurmaAluno').value;
  const salaSel = $('#filtroSalaAluno').value;
  const mapaSalas = salasPorTurma();

  const filtrados = ALUNOS.filter(a =>
    (alunoSel === 'todos' || String(a.id) === alunoSel)
    && (turmaSel === 'todos' || String(a.turma_id) === turmaSel)
    && (salaSel === 'todos' || (mapaSalas.get(String(a.turma_id)) || new Set()).has(salaSel)));

  wrap.innerHTML = '';
  $('#vazioAluno').hidden = filtrados.length > 0;

  const linhasCsv = [];
  filtrados.forEach(a => {
    const freq = a.frequencia !== null && a.frequencia !== undefined ? Number(a.frequencia) : null;
    const salas = ordenarNatural(Array.from(mapaSalas.get(String(a.turma_id)) || []));
    const inativo = Number(a.ativo) === 0;
    const li = document.createElement('li');
    li.className = 'aluno-card' + (inativo ? ' inativo' : '');
    li.innerHTML = `
      <div class="aluno-nome">${esc(a.nome)}</div>
      <div class="aluno-turma">${a.turma_codigo ? esc(a.turma_codigo + ' · ' + a.turma_nome) : 'Sem turma'}</div>
      ${salas.length ? `<div class="aluno-salas">Sala${salas.length > 1 ? 's' : ''}: ${esc(salas.join(', '))}</div>` : ''}
      ${inativo ? '<span class="badge badge-inativo">Inativo</span>' : ''}
      ${freq !== null ? `
      <div class="freq-bar" aria-hidden="true"><div class="freq-fill ${freq < 75 ? 'low' : ''}" style="width:${Math.max(0, Math.min(100, freq))}%"></div></div>
      <div class="freq-label">Frequência: ${freq}%${freq < 75 ? ' (abaixo de 75%)' : ''}</div>` : ''}`;
    wrap.appendChild(li);

    const linha = [a.nome, a.turma_codigo ? `${a.turma_codigo} - ${a.turma_nome}` : 'Sem turma', salas.join(', '), freq !== null ? String(freq).replace('.', ',') : ''];
    if (EH_ADM) linha.push(inativo ? 'Inativo' : 'Ativo');
    linhasCsv.push(linha);
  });

  const filtrosTxt = [
    textoSelecionado($('#filtroAlunoSelect')) && `Aluno: ${textoSelecionado($('#filtroAlunoSelect'))}`,
    textoSelecionado($('#filtroTurmaAluno')) && `Turma: ${textoSelecionado($('#filtroTurmaAluno'))}`,
    textoSelecionado($('#filtroSalaAluno')) && `Sala: ${textoSelecionado($('#filtroSalaAluno'))}`
  ].filter(Boolean).join(' · ') || 'Sem filtros adicionais';

  $('#resumoAlunos').textContent = `${filtrados.length} ${filtrados.length === 1 ? 'aluno encontrado' : 'alunos encontrados'}. ${filtrosTxt}.`;

  const cab = ['Nome', 'Turma', 'Salas', 'Frequência (%)'];
  if (EH_ADM) cab.push('Situação');
  RELATORIO.alunos = {
    titulo: 'Consulta de alunos',
    subtitulo: `${filtrados.length} alunos. ${filtrosTxt}`,
    arquivo: `alunos_${toISO(new Date())}.csv`,
    linhas: [
      ['Relatório: Alunos'],
      ['Filtros', filtrosTxt],
      ['Total de alunos', filtrados.length],
      [],
      cab,
      ...linhasCsv
    ]
  };
}

function popularSelectsTurma() {
  const sel = $('#caTurma');
  if (sel) sel.innerHTML = TURMAS.map(t => `<option value="${esc(t.id)}">${esc(t.codigo)} · ${esc(t.nome)}</option>`).join('');
}

// ============ ADMIN: DESATIVAR / REATIVAR / EXCLUIR ============
const CONFIG_TIPO = {
  instrutor: { select: '#filtroInstrutor', botaoStatus: '#btnStatusInstrutor', botaoExcluir: '#btnDeletarInstrutorSelecionado', lista: () => INSTRUTORES, artigo: 'um instrutor' },
  aluno: { select: '#filtroAlunoSelect', botaoStatus: '#btnStatusAluno', botaoExcluir: '#btnDeletarAlunoSelecionado', lista: () => ALUNOS, artigo: 'um aluno' }
};

function itemSelecionado(tipo) {
  const c = CONFIG_TIPO[tipo];
  const sel = $(c.select);
  if (!sel || sel.value === 'todos') return null;
  return c.lista().find(x => String(x.id) === sel.value) || null;
}

function atualizarBotaoStatus(tipo) {
  const btn = $(CONFIG_TIPO[tipo].botaoStatus);
  if (!btn) return;
  const item = itemSelecionado(tipo);
  btn.textContent = item && Number(item.ativo) === 0 ? `Reativar ${tipo} selecionado` : `Desativar ${tipo} selecionado`;
}

function repopular(tipo) {
  if (tipo === 'instrutor') {
    popularSelectInstrutores();
    popularFiltrosGrade();
    if ($('#relatorioInstrutor')) popularSelectRelatorioInstrutor();
    renderInstrutor();
    renderGrade();
  } else {
    popularSelectAlunos();
    renderAlunos();
  }
}

async function alternarStatus(tipo) {
  const item = itemSelecionado(tipo);
  if (!item) {
    mostrarAviso(`Selecione ${CONFIG_TIPO[tipo].artigo} específico na lista antes de continuar.`, 'erro');
    return;
  }
  const novoAtivo = Number(item.ativo) === 1 ? 0 : 1;
  const pergunta = novoAtivo
    ? `Reativar o cadastro de "${item.nome}"?`
    : `Desativar o cadastro de "${item.nome}"?\n\nA pessoa deixa de aparecer nas listas dos instrutores e não consegue mais entrar no sistema. Você pode reativar depois.`;
  if (!confirm(pergunta)) return;

  const fd = new FormData();
  fd.append('acao', 'alterar_status_usuario');
  fd.append('id', item.id);
  fd.append('tipo', tipo);
  fd.append('ativo', String(novoAtivo));
  try {
    const res = await postAcao(fd);
    if (res.success) {
      item.ativo = res.ativo;
      repopular(tipo);
      mostrarAviso(`${item.nome}: ${novoAtivo ? 'cadastro reativado' : 'cadastro desativado'}.`);
    } else {
      mostrarAviso(res.message, 'erro');
    }
  } catch (e) {
    mostrarAviso('Erro ao se comunicar com o servidor.', 'erro');
  }
}

async function excluirUsuario(tipo) {
  const item = itemSelecionado(tipo);
  if (!item) {
    mostrarAviso(`Selecione ${CONFIG_TIPO[tipo].artigo} específico na lista antes de excluir.`, 'erro');
    return;
  }
  if (!confirm(`Tem certeza que deseja EXCLUIR permanentemente "${item.nome}" do banco de dados?\n\nEssa ação não pode ser desfeita. Se quiser apenas tirar a pessoa das listas, use "Desativar".`)) return;

  const fd = new FormData();
  fd.append('acao', tipo === 'instrutor' ? 'deletar_instrutor' : 'deletar_aluno');
  fd.append('id', item.id);
  try {
    const res = await postAcao(fd);
    if (res.success) {
      const lista = CONFIG_TIPO[tipo].lista();
      lista.splice(lista.indexOf(item), 1);
      $(CONFIG_TIPO[tipo].select).value = 'todos';
      if (tipo === 'instrutor') AULAS.forEach(a => { if (String(a.instrutor_id) === String(item.id)) { a.instrutor_id = null; a.instrutor_nome = null; } });
      repopular(tipo);
      mostrarAviso(res.message);
    } else {
      mostrarAviso(res.message, 'erro');
    }
  } catch (e) {
    mostrarAviso('Erro ao se comunicar com o servidor.', 'erro');
  }
}

// ============ CADASTROS ============
function mostrarAlertaGenerico(el, texto, tipo) {
  if (!el) return;
  el.textContent = texto;
  el.className = `auth-alert show ${tipo}`;
}

function limparAlerta(el) {
  if (!el) return;
  el.textContent = '';
  el.className = 'auth-alert';
}

const formCI = $('#formCadastroInstrutor');
if (formCI) {
  formCI.addEventListener('submit', async e => {
    e.preventDefault();
    const alerta = $('#alertaCadastroInstrutor');
    const nome = $('#ciNome').value.trim();
    const email = $('#ciEmail').value.trim();
    const senha = $('#ciSenha').value;

    if (!nome || !email || !senha) {
      mostrarAlertaGenerico(alerta, 'Preencha nome, e-mail e senha provisória.', 'error');
      return;
    }
    limparAlerta(alerta);

    try {
      await pegarJSON('api/instrutores.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nome, email, senha })
      });
      mostrarAlertaGenerico(alerta, 'Instrutor cadastrado com sucesso!', 'success');
      formCI.reset();
      INSTRUTORES = await pegarJSON('api/instrutores.php');
      popularSelectInstrutores();
      if ($('#relatorioInstrutor')) popularSelectRelatorioInstrutor();
    } catch (err) {
      mostrarAlertaGenerico(alerta, err.message || 'Falha ao conectar com o servidor.', 'error');
    }
  });
}

const formCA = $('#formCadastroAluno');
if (formCA) {
  formCA.addEventListener('submit', async e => {
    e.preventDefault();
    const alerta = $('#alertaCadastroAluno');
    const nome = $('#caNome').value.trim();
    const email = $('#caEmail').value.trim();
    const senha = $('#caSenha').value;
    const turma_id = $('#caTurma').value;

    if (!nome || !email || !senha) {
      mostrarAlertaGenerico(alerta, 'Preencha nome, e-mail e senha provisória.', 'error');
      return;
    }
    limparAlerta(alerta);

    try {
      await pegarJSON('api/alunos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nome, email, senha, turma_id })
      });
      mostrarAlertaGenerico(alerta, 'Aluno cadastrado com sucesso!', 'success');
      formCA.reset();
      ALUNOS = await pegarJSON('api/alunos.php');
      popularSelectAlunos();
      renderAlunos();
    } catch (err) {
      mostrarAlertaGenerico(alerta, err.message || 'Falha ao conectar com o servidor.', 'error');
    }
  });
}

// ============ ALTERAR SENHA ============
function abrirAlterarSenha(gatilho) {
  $('#formSenha').reset();
  limparAlerta($('#alertaSenha'));
  fecharDropdownPerfil();
  fecharMenuMobile();
  abrirModal($('#senhaOverlay'), gatilho);
}

const formSenha = $('#formSenha');
if (formSenha) {
  formSenha.addEventListener('submit', async e => {
    e.preventDefault();
    const alerta = $('#alertaSenha');
    const atual = $('#senhaAtual').value;
    const nova = $('#senhaNova').value;
    const confirma = $('#senhaConfirma').value;

    if (!atual) { mostrarAlertaGenerico(alerta, 'Informe a senha atual.', 'error'); $('#senhaAtual').focus(); return; }
    if (nova.length < 8) { mostrarAlertaGenerico(alerta, 'A nova senha precisa ter ao menos 8 caracteres.', 'error'); $('#senhaNova').focus(); return; }
    if (nova !== confirma) { mostrarAlertaGenerico(alerta, 'A confirmação não é igual à nova senha.', 'error'); $('#senhaConfirma').focus(); return; }
    limparAlerta(alerta);

    try {
      await pegarJSON('auth/alterar_senha.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ senha_atual: atual, nova_senha: nova })
      });
      formSenha.reset();
      fecharModal();
      mostrarAviso('Senha alterada com sucesso.');
    } catch (err) {
      mostrarAlertaGenerico(alerta, err.message || 'Não foi possível alterar a senha.', 'error');
    }
  });
}

// ============ RELATÓRIOS DA ABA (CSV e impressão) ============
function csvCelula(v) {
  let s = v === null || v === undefined ? '' : String(v);
  if (/^[=+\-@\t\r]/.test(s)) s = "'" + s; // evita fórmulas em planilhas
  return '"' + s.replace(/"/g, '""') + '"';
}

function baixarCSV(nome, linhas) {
  const conteudo = '\uFEFF' + linhas.map(l => l.map(csvCelula).join(';')).join('\r\n');
  const blob = new Blob([conteudo], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = nome;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1500);
}

function gerarRelatorio(view) {
  const rel = RELATORIO[view];
  if (!rel) {
    mostrarAviso('Não há dados para gerar o relatório. Confira os filtros da tela.', 'erro');
    return;
  }
  baixarCSV(rel.arquivo, rel.linhas);
  mostrarAviso(`Relatório gerado: ${rel.arquivo}. Abra o arquivo no Excel ou em outra planilha.`);
}

function preencherCabecalhoImpressao() {
  const rel = RELATORIO[viewAtiva()];
  const el = $('#printMeta');
  if (!el) return;
  if (!rel) { el.innerHTML = ''; return; }
  const agora = new Date();
  el.innerHTML = `<strong>Pavuna Softwares · ${esc(rel.titulo)}</strong>` +
    `${esc(rel.subtitulo)}<br>Gerado em ${fmtBR(agora)} às ${pad(agora.getHours())}:${pad(agora.getMinutes())} por ${esc(SESSAO.nome || '')} (${esc(SESSAO.rotulo || '')})`;
}
window.addEventListener('beforeprint', preencherCabecalhoImpressao);

// ============ ABA RELATÓRIOS (resumo por instrutor, já existente) ============
function popularSelectRelatorioInstrutor() {
  const sel = $('#relatorioInstrutor');
  if (sel) sel.innerHTML = INSTRUTORES.map(i => `<option value="${esc(i.id)}">${esc(nomeComStatus(i))}</option>`).join('');
}

function urlRelatorioInstrutor(extra = '') {
  const sel = $('#relatorioInstrutor');
  const base = sel ? `api/relatorio_instrutor.php?instrutor_id=${encodeURIComponent(sel.value)}` : 'api/relatorio_instrutor.php?';
  return base + extra;
}

async function gerarRelatorioInstrutor() {
  const resumoEl = $('#relatorioResumo');
  const tbody = $('#tabelaRelatorio tbody');
  try {
    const data = await pegarJSON(urlRelatorioInstrutor());
    resumoEl.innerHTML = `
      <div class="resumo-card"><strong>${Number(data.resumo.confirmada) || 0}</strong><span>Confirmadas</span></div>
      <div class="resumo-card"><strong>${Number(data.resumo.reposicao) || 0}</strong><span>Reposições</span></div>
      <div class="resumo-card"><strong>${Number(data.resumo.cancelada) || 0}</strong><span>Canceladas</span></div>
      <div class="resumo-card"><strong>${Number(data.total) || 0}</strong><span>Total de aulas</span></div>`;
    tbody.innerHTML = data.aulas.map(a => `
      <tr>
        <td>${esc(DIA_LABEL[Number(a.dia_semana)])}</td>
        <td>${esc(TURNO_LABEL[a.turno])}</td>
        <td>${esc(a.turma_codigo)} · ${esc(a.turma_nome)}</td>
        <td class="mono">${esc(a.sala || '-')}</td>
        <td><span class="badge badge-${esc(a.status)}">${esc(STATUS_LABEL[a.status] || a.status)}</span></td>
      </tr>`).join('');
  } catch (e) {
    resumoEl.innerHTML = `<p class="empty-state">${esc(e.message || 'Erro ao gerar relatório.')}</p>`;
    tbody.innerHTML = '';
  }
}

// ============ PERFIL / MENU MÓVEL ============
function fecharDropdownPerfil() {
  const btn = $('#perfilBtn');
  const dd = $('#perfilDropdown');
  if (btn) btn.setAttribute('aria-expanded', 'false');
  if (dd) dd.hidden = true;
}

// ============ LIGAÇÃO DOS EVENTOS ============
function ligarEventos() {
  // Navegação
  $$('.tab, .mobile-link[data-view]').forEach(btn => btn.addEventListener('click', () => irPara(btn.dataset.view)));

  const menuToggle = $('#menuToggle');
  if (menuToggle) {
    menuToggle.addEventListener('click', () => {
      const painel = $('#mobilePanel');
      const aberto = painel.classList.toggle('open');
      menuToggle.setAttribute('aria-expanded', String(aberto));
      menuToggle.setAttribute('aria-label', aberto ? 'Fechar menu de navegação' : 'Abrir menu de navegação');
    });
  }

  // Perfil
  const perfilBtn = $('#perfilBtn');
  if (perfilBtn) {
    perfilBtn.addEventListener('click', e => {
      e.stopPropagation();
      const dd = $('#perfilDropdown');
      const abrir = dd.hidden;
      dd.hidden = !abrir;
      perfilBtn.setAttribute('aria-expanded', String(abrir));
    });
    document.addEventListener('click', e => { if (!e.target.closest('.perfil-area')) fecharDropdownPerfil(); });
  }

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape' || modalAtivo) return;
    if (!$('#perfilDropdown').hidden) { fecharDropdownPerfil(); perfilBtn?.focus(); }
    else if ($('#mobilePanel').classList.contains('open')) { fecharMenuMobile(); menuToggle?.focus(); }
  });

  $('#btnAlterarSenha')?.addEventListener('click', e => abrirAlterarSenha(perfilBtn || e.currentTarget));
  $('#btnAlterarSenhaMobile')?.addEventListener('click', e => abrirAlterarSenha(menuToggle || e.currentTarget));
  $('#cancelarSenha')?.addEventListener('click', () => fecharModal());
  $('#fecharDetalhe')?.addEventListener('click', () => fecharModal());

  // Grade
  $('#filtroData').addEventListener('change', () => { renderGrade(); anunciar($('#periodoGrade').textContent); });
  ['#filtroTurno', '#filtroDiaSemana', '#filtroInstrutorGrade', '#filtroTurmaGrade', '#filtroSalaGrade'].forEach(sel => {
    $(sel).addEventListener('change', () => { renderGrade(); anunciar($('#periodoGrade').textContent); });
  });
  $('#btnSemanaAnterior').addEventListener('click', () => moverSemana(-1));
  $('#btnSemanaSeguinte').addEventListener('click', () => moverSemana(1));
  $('#btnHoje').addEventListener('click', () => definirDataGrade(toISO(new Date())));
  $('#btnLimparGrade').addEventListener('click', () => {
    ['#filtroTurno', '#filtroDiaSemana', '#filtroInstrutorGrade', '#filtroTurmaGrade', '#filtroSalaGrade'].forEach(sel => { $(sel).value = 'todos'; });
    definirDataGrade(toISO(new Date()));
  });

  // Relatórios por aba
  $$('.js-relatorio').forEach(b => b.addEventListener('click', () => gerarRelatorio(b.dataset.view)));
  $$('.js-imprimir').forEach(b => b.addEventListener('click', () => { preencherCabecalhoImpressao(); window.print(); }));

  if (!VE_LISTAS) return;

  // Instrutores
  $('#filtroInstrutor').addEventListener('change', () => { atualizarBotaoStatus('instrutor'); renderInstrutor(); anunciar($('#resumoInstrutor').textContent); });
  ['#instrDataIni', '#instrDataFim'].forEach(sel => $(sel).addEventListener('change', () => { renderInstrutor(); anunciar($('#resumoInstrutor').textContent); }));
  $('#btnLimparInstr').addEventListener('click', () => {
    $('#filtroInstrutor').value = 'todos';
    definirPeriodoPadraoInstrutor();
    atualizarBotaoStatus('instrutor');
    renderInstrutor();
  });

  // Alunos
  ['#filtroAlunoSelect', '#filtroTurmaAluno', '#filtroSalaAluno'].forEach(sel => {
    $(sel).addEventListener('change', () => { atualizarBotaoStatus('aluno'); renderAlunos(); anunciar($('#resumoAlunos').textContent); });
  });
  $('#btnLimparAlunos').addEventListener('click', () => {
    ['#filtroAlunoSelect', '#filtroTurmaAluno', '#filtroSalaAluno'].forEach(sel => { $(sel).value = 'todos'; });
    atualizarBotaoStatus('aluno');
    renderAlunos();
  });

  // Administrador
  $('#btnStatusInstrutor')?.addEventListener('click', () => alternarStatus('instrutor'));
  $('#btnStatusAluno')?.addEventListener('click', () => alternarStatus('aluno'));
  $('#btnDeletarInstrutorSelecionado')?.addEventListener('click', () => excluirUsuario('instrutor'));
  $('#btnDeletarAlunoSelecionado')?.addEventListener('click', () => excluirUsuario('aluno'));

  // Aba Relatórios (resumo por instrutor)
  $('#gerarRelatorio')?.addEventListener('click', gerarRelatorioInstrutor);
  $('#exportarRelatorio')?.addEventListener('click', () => { window.location.href = urlRelatorioInstrutor('&formato=csv'); });
}

function iniciar() {
  $('#filtroData').value = toISO(new Date());
  if (VE_LISTAS) definirPeriodoPadraoInstrutor();
  ligarEventos();
  carregarDados();
}

iniciar();
