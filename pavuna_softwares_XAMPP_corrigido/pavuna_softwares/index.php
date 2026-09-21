<?php
require_once __DIR__ . '/includes/guard.php';

// ==============================================================================
// 1. AÇÕES AJAX (POST): PERFIL, EXCLUSÃO E ATIVAR/DESATIVAR USUÁRIOS
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!usuarioLogado()) {
        echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
        exit;
    }
    exigirCSRF();

    $tipoUsuarioLogado = $_SESSION['usuario_tipo'] ?? '';
    $acao = $_POST['acao'];

    try {
        $pdo = getConexao();

        // AÇÃO: Atualizar perfil (nome e foto)
        if ($acao === 'atualizar_perfil') {
            $usuarioId = $_SESSION['usuario_id'];
            $nome = validarNome($_POST['nome'] ?? '');
            $caminhoFotoBanco = null;

            if ($nome === '') {
                echo json_encode(['success' => false, 'message' => 'O nome não pode ficar vazio.']);
                exit;
            }

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $tipoArquivo = mime_content_type($_FILES['foto']['tmp_name']);

                if (!isset($permitidos[$tipoArquivo])) {
                    echo json_encode(['success' => false, 'message' => 'Formato de imagem inválido (apenas JPG, PNG ou WEBP).']);
                    exit;
                }
                if ($_FILES['foto']['size'] > 3 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'A imagem deve ter no máximo 3MB.']);
                    exit;
                }

                $diretorioUpload = __DIR__ . '/uploads/fotos/';
                if (!is_dir($diretorioUpload)) {
                    mkdir($diretorioUpload, 0750, true);
                }

                $nomeArquivo = 'user_' . $usuarioId . '_' . bin2hex(random_bytes(12)) . '.' . $permitidos[$tipoArquivo];
                $destinoFinal = $diretorioUpload . $nomeArquivo;

                if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destinoFinal)) {
                    echo json_encode(['success' => false, 'message' => 'Erro ao salvar a imagem no servidor.']);
                    exit;
                }
                $caminhoFotoBanco = 'uploads/fotos/' . $nomeArquivo;
            }

            if ($caminhoFotoBanco) {
                $stmt = $pdo->prepare('UPDATE usuarios SET nome = :nome, foto = :foto WHERE id = :id');
                $stmt->execute([':nome' => $nome, ':foto' => $caminhoFotoBanco, ':id' => $usuarioId]);
                $_SESSION['usuario_foto'] = $caminhoFotoBanco;
            } else {
                $stmt = $pdo->prepare('UPDATE usuarios SET nome = :nome WHERE id = :id');
                $stmt->execute([':nome' => $nome, ':id' => $usuarioId]);
            }

            $_SESSION['usuario_nome'] = $nome;

            echo json_encode([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso!',
                'nome' => $nome,
                'foto' => $_SESSION['usuario_foto'] ?? null
            ]);
            exit;
        }

        // AÇÃO: Excluir instrutor ou aluno (apenas administrador)
        if ($acao === 'deletar_instrutor' || $acao === 'deletar_aluno') {
            $tipoAlvo = ($acao === 'deletar_instrutor') ? 'instrutor' : 'aluno';
            $rotulo = ($tipoAlvo === 'instrutor') ? 'instrutores' : 'alunos';

            if ($tipoUsuarioLogado !== 'coordenador') {
                echo json_encode(['success' => false, 'message' => 'Acesso negado: apenas administradores podem excluir ' . $rotulo . '.']);
                exit;
            }

            $idAlvo = validarId($_POST['id'] ?? null, 'Cadastro');

            $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id AND tipo = :tipo');
            $stmt->execute([':id' => $idAlvo, ':tipo' => $tipoAlvo]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => ucfirst($tipoAlvo) . ' excluído com sucesso.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Cadastro não encontrado ou já excluído.']);
            }
            exit;
        }

        // AÇÃO: Desativar / reativar instrutor ou aluno (apenas administrador)
        if ($acao === 'alterar_status_usuario') {
            if ($tipoUsuarioLogado !== 'coordenador') {
                echo json_encode(['success' => false, 'message' => 'Acesso negado: apenas administradores podem desativar ou reativar cadastros.']);
                exit;
            }

            $idAlvo = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $tipoAlvo = $_POST['tipo'] ?? '';
            $novoAtivo = (($_POST['ativo'] ?? '') === '1') ? 1 : 0;

            if (!$idAlvo || !in_array($tipoAlvo, ['instrutor', 'aluno'], true)) {
                echo json_encode(['success' => false, 'message' => 'Selecione um cadastro válido.']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id AND tipo = :tipo');
            $stmt->execute([':id' => $idAlvo, ':tipo' => $tipoAlvo]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Cadastro não encontrado.']);
                exit;
            }

            $stmt = $pdo->prepare('UPDATE usuarios SET ativo = :ativo WHERE id = :id AND tipo = :tipo');
            $stmt->execute([':ativo' => $novoAtivo, ':id' => $idAlvo, ':tipo' => $tipoAlvo]);

            echo json_encode([
                'success' => true,
                'ativo' => $novoAtivo,
                'message' => ($novoAtivo ? 'Cadastro reativado com sucesso.' : 'Cadastro desativado com sucesso.')
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
        exit;

    } catch (PDOException $e) {
        error_log('[pavuna] ' . $e->getMessage());
        $mensagem = ($e->getCode() === '42S22')
            ? 'Banco de dados desatualizado. Importe o arquivo database.sql no phpMyAdmin.'
            : 'Erro no banco de dados. Tente novamente.';
        echo json_encode(['success' => false, 'message' => $mensagem]);
        exit;
    }
}

// ==============================================================================
// 2. CARREGAMENTO NORMAL DA PÁGINA HTML
// ==============================================================================
exigirLogin();

$tipo = $_SESSION['usuario_tipo'];
$nome = $_SESSION['usuario_nome'];
$foto = !empty($_SESSION['usuario_foto']) ? $_SESSION['usuario_foto'] : null;

// Perfis: coordenador = administrador (acessa tudo); instrutor; aluno (só a grade da semana)
$ehAdm = ($tipo === 'coordenador');
$podeVerListas = in_array($tipo, ['coordenador', 'instrutor'], true);   // abas Instrutores e Alunos
$podeVerRelatorios = $podeVerListas;                                    // aba Relatórios (resumo por instrutor)
$podeCadastrarInstrutor = $ehAdm;
$podeCadastrarAluno = $podeVerListas;

$rotulosTipo = ['coordenador' => 'Administrador', 'instrutor' => 'Instrutor', 'aluno' => 'Aluno'];
$rotuloTipo = $rotulosTipo[$tipo] ?? ucfirst($tipo);

$iniciais = '';
foreach (array_slice(preg_split('/\s+/', trim($nome)), 0, 2) as $parte) {
    $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grade da semana · Pavuna Softwares</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>

  <a href="#conteudo-principal" class="skip-link">Pular para o conteúdo principal</a>

  <div vw class="enabled">
    <div vw-access-button class="active" title="Ativar VLibras (tradução para Libras)"></div>
    <div vw-plugin-wrapper><div class="vw-plugin-top-wrapper"></div></div>
  </div>
  <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
  <script>if (window.VLibras) { new window.VLibras.Widget('https://vlibras.gov.br/app'); }</script>

<header class="topbar">
  <div class="brand">
    <div class="brand-text">
      <strong>NÚCLEO ESCOLAR DA PAVUNA SOFTWARES</strong>
      <span>Controle de Turmas &amp; Instrutores</span>
    </div>
  </div>

  <nav class="tabs" id="tabs" aria-label="Seções do sistema">
    <button type="button" class="tab active" data-view="grade" aria-current="page">Grade da Semana</button>
    <?php if ($podeVerListas): ?>
    <button type="button" class="tab" data-view="instrutores">Instrutores</button>
    <button type="button" class="tab" data-view="alunos">Alunos</button>
    <?php endif; ?>
    <?php if ($podeVerRelatorios): ?>
    <button type="button" class="tab" data-view="relatorios">Relatórios</button>
    <?php endif; ?>
    <?php if ($podeCadastrarInstrutor): ?>
    <button type="button" class="tab" data-view="cadastro-instrutor">Cadastrar Instrutor</button>
    <?php endif; ?>
    <?php if ($podeCadastrarAluno): ?>
    <button type="button" class="tab" data-view="cadastro-aluno">Cadastrar Aluno</button>
    <?php endif; ?>
  </nav>

  <div class="perfil-area">
    <button type="button" class="perfil-icon" id="perfilBtn" aria-expanded="false" aria-controls="perfilDropdown" aria-label="Menu da conta de <?= htmlspecialchars($nome) ?>">
      <?php if ($foto): ?>
      <img id="perfilFoto" src="<?= htmlspecialchars($foto) ?>" alt="Foto de perfil de <?= htmlspecialchars($nome) ?>">
      <?php else: ?>
      <span class="perfil-iniciais" aria-hidden="true"><?= htmlspecialchars($iniciais) ?></span>
      <?php endif; ?>
    </button>
    <div class="perfil-dropdown" id="perfilDropdown" hidden>
      <div class="perfil-nome" id="perfilNomeMenu"><?= htmlspecialchars($nome) ?></div>
      <div class="perfil-tipo"><?= htmlspecialchars($rotuloTipo) ?></div>
      <button type="button" class="perfil-link" id="btnAlterarSenha">Alterar senha</button>
      <a class="perfil-link perfil-sair" href="auth/logout.php">Sair do sistema</a>
    </div>
  </div>

  <button type="button" class="menu-toggle" id="menuToggle" aria-expanded="false" aria-controls="mobilePanel" aria-label="Abrir menu de navegação">
    <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
  </button>
</header>

<div class="mobile-panel" id="mobilePanel">
  <nav aria-label="Seções do sistema (menu móvel)">
    <button type="button" class="mobile-link" data-view="grade" aria-current="page">Grade da Semana</button>
    <?php if ($podeVerListas): ?>
    <button type="button" class="mobile-link" data-view="instrutores">Instrutores</button>
    <button type="button" class="mobile-link" data-view="alunos">Alunos</button>
    <?php endif; ?>
    <?php if ($podeVerRelatorios): ?><button type="button" class="mobile-link" data-view="relatorios">Relatórios</button><?php endif; ?>
    <?php if ($podeCadastrarInstrutor): ?><button type="button" class="mobile-link" data-view="cadastro-instrutor">Cadastrar Instrutor</button><?php endif; ?>
    <?php if ($podeCadastrarAluno): ?><button type="button" class="mobile-link" data-view="cadastro-aluno">Cadastrar Aluno</button><?php endif; ?>
    <button type="button" class="mobile-link" id="btnAlterarSenhaMobile">Alterar senha</button>
    <a class="mobile-link mobile-sair" href="auth/logout.php">Sair do Sistema</a>
  </nav>
</div>

<main id="conteudo-principal" tabindex="-1">

  <noscript><p class="aviso aviso-erro">Este sistema precisa de JavaScript ativado para carregar a grade, as listas e os relatórios.</p></noscript>

  <div class="aviso" id="avisoGlobal" role="status" aria-live="polite" hidden></div>
  <div class="sr-only" id="anuncioSr" role="status" aria-live="polite" aria-atomic="true"></div>
  <div class="print-only print-meta" id="printMeta"></div>

  <!-- VIEW: GRADE DA SEMANA -->
  <section class="view active" id="view-grade" aria-labelledby="titulo-grade">
    <div class="view-head">
      <h1 id="titulo-grade" tabindex="-1">Grade da semana</h1>
      <p>Turmas em andamento por dia e turno. Escolha uma data para ver semanas passadas, a atual ou futuras. Toque numa turma para ver detalhes.</p>
    </div>

    <div class="filters" role="group" aria-label="Filtros da grade da semana">
      <div class="field">
        <label for="filtroData">Data (dia/mês/ano)</label>
        <input type="date" id="filtroData">
      </div>
      <div class="btn-group" role="group" aria-label="Navegar entre semanas">
        <button type="button" class="btn btn-outline" id="btnSemanaAnterior"><span aria-hidden="true">←</span> Semana anterior</button>
        <button type="button" class="btn btn-outline" id="btnHoje">Hoje</button>
        <button type="button" class="btn btn-outline" id="btnSemanaSeguinte">Próxima semana <span aria-hidden="true">→</span></button>
      </div>

      <div class="field">
        <label for="filtroTurno">Turno</label>
        <select id="filtroTurno">
          <option value="todos">Todos os turnos</option>
          <option value="manha">Manhã</option>
          <option value="tarde">Tarde</option>
          <option value="noite">Noite</option>
        </select>
      </div>
      <div class="field">
        <label for="filtroDiaSemana">Dia da semana</label>
        <select id="filtroDiaSemana">
          <option value="todos">Todos os dias</option>
          <option value="0">Segunda-feira</option>
          <option value="1">Terça-feira</option>
          <option value="2">Quarta-feira</option>
          <option value="3">Quinta-feira</option>
          <option value="4">Sexta-feira</option>
          <option value="5">Sábado</option>
        </select>
      </div>
      <div class="field">
        <label for="filtroInstrutorGrade">Instrutor</label>
        <select id="filtroInstrutorGrade"><option value="todos">Todos os instrutores</option></select>
      </div>
      <div class="field">
        <label for="filtroTurmaGrade">Turma</label>
        <select id="filtroTurmaGrade"><option value="todos">Todas as turmas</option></select>
      </div>
      <div class="field">
        <label for="filtroSalaGrade">Sala</label>
        <select id="filtroSalaGrade"><option value="todos">Todas as salas</option></select>
      </div>
      <button type="button" class="btn btn-outline" id="btnLimparGrade">Limpar filtros</button>
    </div>

    <div class="toolbar">
      <div class="legend" role="group" aria-label="Legenda das cores dos turnos">
        <span class="dot dot-manha" aria-hidden="true"></span> Manhã
        <span class="dot dot-tarde" aria-hidden="true"></span> Tarde
        <span class="dot dot-noite" aria-hidden="true"></span> Noite
      </div>
      <div class="btn-group report-bar" role="group" aria-label="Relatório da grade">
        <button type="button" class="btn btn-primary js-relatorio" data-view="grade">Gerar relatório</button>
        <button type="button" class="btn btn-outline js-imprimir">Imprimir / salvar PDF</button>
      </div>
    </div>

    <p class="periodo-label" id="periodoGrade" role="status" aria-live="polite"></p>

    <div class="grade-scroll" tabindex="0" role="region" aria-label="Tabela da grade da semana (role para os lados se necessário)">
      <table class="grade" id="tabelaGrade">
        <caption class="sr-only" id="capGrade">Grade semanal de aulas por dia e turno</caption>
        <thead>
          <tr>
            <th scope="col" class="col-dia">Dia</th>
            <th scope="col" id="th-manha">Manhã</th>
            <th scope="col" id="th-tarde">Tarde</th>
            <th scope="col" id="th-noite">Noite</th>
          </tr>
        </thead>
        <tbody id="corpoGrade"></tbody>
      </table>
    </div>
    <p class="empty-state" id="vazioGrade" hidden>Nenhuma aula encontrada nesta semana com os filtros escolhidos.</p>

    <div class="page-logos">
      <img src="Pavuna Softwares/SENAI_Port_com_assinatura_cor.png" alt="Logotipo do SENAI">
      <img src="Pavuna Softwares/SESI_Port_com_assinatura_cor.png" alt="Logotipo do SESI">
    </div>
  </section>

  <?php if ($podeVerListas): ?>
  <!-- VIEW: INSTRUTORES -->
  <section class="view" id="view-instrutores" aria-labelledby="titulo-instrutores">
    <div class="view-head">
      <h1 id="titulo-instrutores" tabindex="-1">Consulta de instrutores</h1>
      <p>Escolha o instrutor e o período (data inicial e final) para ver as aulas e a carga horária por matéria.</p>
    </div>

    <div class="filters" role="group" aria-label="Filtros de instrutores">
      <div class="field">
        <label for="filtroInstrutor">Instrutor</label>
        <select id="filtroInstrutor"><option value="todos">Todos os instrutores</option></select>
      </div>
      <div class="field">
        <label for="instrDataIni">Data inicial</label>
        <input type="date" id="instrDataIni">
      </div>
      <div class="field">
        <label for="instrDataFim">Data final</label>
        <input type="date" id="instrDataFim">
      </div>
      <button type="button" class="btn btn-outline" id="btnLimparInstr">Limpar filtros</button>
    </div>

    <div class="toolbar">
      <div class="btn-group report-bar" role="group" aria-label="Relatório de instrutores">
        <button type="button" class="btn btn-primary js-relatorio" data-view="instrutores">Gerar relatório</button>
        <button type="button" class="btn btn-outline js-imprimir">Imprimir / salvar PDF</button>
      </div>
      <?php if ($ehAdm): ?>
      <div class="btn-group" role="group" aria-label="Ações de administrador sobre o instrutor selecionado">
        <button type="button" class="btn btn-warning" id="btnStatusInstrutor">Desativar instrutor selecionado</button>
        <button type="button" class="btn btn-danger" id="btnDeletarInstrutorSelecionado">Excluir instrutor selecionado</button>
      </div>
      <?php endif; ?>
    </div>

    <p class="periodo-label" id="resumoInstrutor" role="status" aria-live="polite"></p>
    <p class="aviso aviso-erro" id="erroPeriodoInstr" role="alert" hidden></p>

    <h2 class="section-title" id="tituloCarga">Carga horária por matéria</h2>
    <div class="table-wrap" tabindex="0" role="region" aria-labelledby="tituloCarga">
      <table class="lista" id="tabelaCarga">
        <thead>
          <tr>
            <th scope="col">Instrutor</th>
            <th scope="col">Matéria</th>
            <th scope="col">Turma</th>
            <th scope="col" class="num">Aulas no período</th>
            <th scope="col" class="num">Carga horária</th>
          </tr>
        </thead>
        <tbody></tbody>
        <tfoot></tfoot>
      </table>
    </div>

    <h2 class="section-title" id="tituloAulasInstr">Aulas no período</h2>
    <div class="table-wrap" tabindex="0" role="region" aria-labelledby="tituloAulasInstr">
      <table class="lista" id="tabelaInstrutor">
        <thead>
          <tr>
            <th scope="col">Instrutor</th>
            <th scope="col">Dia</th>
            <th scope="col">Turno</th>
            <th scope="col">Turma</th>
            <th scope="col">Matéria</th>
            <th scope="col">Sala</th>
            <th scope="col">Vigência</th>
            <th scope="col">Status</th>
            <th scope="col" class="num">Carga horária no período</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <p class="empty-state" id="vazioInstrutor" hidden>Nenhuma aula encontrada para esse instrutor no período escolhido.</p>

    <div class="page-logos">
      <img src="Pavuna Softwares/SENAI_Port_com_assinatura_cor.png" alt="Logotipo do SENAI">
      <img src="Pavuna Softwares/SESI_Port_com_assinatura_cor.png" alt="Logotipo do SESI">
    </div>
  </section>

  <!-- VIEW: ALUNOS -->
  <section class="view" id="view-alunos" aria-labelledby="titulo-alunos">
    <div class="view-head">
      <h1 id="titulo-alunos" tabindex="-1">Consulta de alunos</h1>
      <p>Situação de matrícula e frequência. Filtre por aluno, turma ou sala.</p>
    </div>

    <div class="filters" role="group" aria-label="Filtros de alunos">
      <div class="field">
        <label for="filtroAlunoSelect">Aluno (por nome)</label>
        <select id="filtroAlunoSelect"><option value="todos">Todos os alunos</option></select>
      </div>
      <div class="field">
        <label for="filtroTurmaAluno">Turma</label>
        <select id="filtroTurmaAluno"><option value="todos">Todas as turmas</option></select>
      </div>
      <div class="field">
        <label for="filtroSalaAluno">Sala</label>
        <select id="filtroSalaAluno"><option value="todos">Todas as salas</option></select>
      </div>
      <button type="button" class="btn btn-outline" id="btnLimparAlunos">Limpar filtros</button>
    </div>

    <div class="toolbar">
      <div class="btn-group report-bar" role="group" aria-label="Relatório de alunos">
        <button type="button" class="btn btn-primary js-relatorio" data-view="alunos">Gerar relatório</button>
        <button type="button" class="btn btn-outline js-imprimir">Imprimir / salvar PDF</button>
      </div>
      <?php if ($ehAdm): ?>
      <div class="btn-group" role="group" aria-label="Ações de administrador sobre o aluno selecionado">
        <button type="button" class="btn btn-warning" id="btnStatusAluno">Desativar aluno selecionado</button>
        <button type="button" class="btn btn-danger" id="btnDeletarAlunoSelecionado">Excluir aluno selecionado</button>
      </div>
      <?php endif; ?>
    </div>

    <p class="periodo-label" id="resumoAlunos" role="status" aria-live="polite"></p>
    <ul class="cards-alunos" id="listaAlunos" aria-label="Lista de alunos"></ul>
    <p class="empty-state" id="vazioAluno" hidden>Nenhum aluno encontrado com esses filtros.</p>

    <div class="page-logos">
      <img src="Pavuna Softwares/SENAI_Port_com_assinatura_cor.png" alt="Logotipo do SENAI">
      <img src="Pavuna Softwares/SESI_Port_com_assinatura_cor.png" alt="Logotipo do SESI">
    </div>
  </section>
  <?php endif; ?>

  <?php if ($podeVerRelatorios): ?>
  <!-- VIEW: RELATÓRIOS -->
  <section class="view" id="view-relatorios" aria-labelledby="titulo-relatorios">
    <div class="view-head">
      <h1 id="titulo-relatorios" tabindex="-1">Relatório de instrutor</h1>
      <p>Resumo de aulas confirmadas, canceladas e de reposição por instrutor.</p>
    </div>
    <div class="filters" role="group" aria-label="Filtros do relatório">
      <?php if ($ehAdm): ?>
      <div class="field">
        <label for="relatorioInstrutor">Instrutor</label>
        <select id="relatorioInstrutor"></select>
      </div>
      <?php endif; ?>
      <button type="button" class="btn btn-primary" id="gerarRelatorio">Gerar relatório</button>
      <button type="button" class="btn btn-outline" id="exportarRelatorio">Exportar CSV</button>
    </div>
    <div id="relatorioResumo" class="relatorio-resumo" role="region" aria-live="polite" aria-label="Resumo do relatório"></div>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Aulas do relatório">
      <table class="lista" id="tabelaRelatorio">
        <thead>
          <tr>
            <th scope="col">Dia</th>
            <th scope="col">Turno</th>
            <th scope="col">Turma</th>
            <th scope="col">Sala</th>
            <th scope="col">Status</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <div class="page-logos">
      <img src="Pavuna Softwares/SENAI_Port_com_assinatura_cor.png" alt="Logotipo do SENAI">
      <img src="Pavuna Softwares/SESI_Port_com_assinatura_cor.png" alt="Logotipo do SESI">
    </div>
  </section>
  <?php endif; ?>

  <?php if ($podeCadastrarInstrutor): ?>
  <!-- VIEW: CADASTRAR INSTRUTOR -->
  <section class="view" id="view-cadastro-instrutor" aria-labelledby="titulo-cad-instrutor">
    <div class="view-head">
      <h1 id="titulo-cad-instrutor" tabindex="-1">Cadastrar instrutor</h1>
      <p>Somente administradores podem criar contas de instrutor.</p>
    </div>
    <div class="form-card">
      <div class="auth-alert" id="alertaCadastroInstrutor" role="alert"></div>
      <form id="formCadastroInstrutor" class="form-stack" novalidate>
        <div class="auth-field">
          <label for="ciNome">Nome completo</label>
          <input type="text" id="ciNome" name="nome" required aria-required="true" autocomplete="off">
        </div>
        <div class="auth-field">
          <label for="ciEmail">E-mail</label>
          <input type="email" id="ciEmail" name="email" required aria-required="true" autocomplete="off">
        </div>
        <div class="auth-field">
          <label for="ciSenha">Senha provisória</label>
          <input type="password" id="ciSenha" name="senha" required aria-required="true" aria-describedby="ciSenhaDica" autocomplete="new-password">
           <span class="auth-hint" id="ciSenhaDica">Mínimo de 8 caracteres. A pessoa poderá trocar depois em “Alterar senha”.</span>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary btn-lg">Cadastrar instrutor</button>
        </div>
      </form>
    </div>

    <div class="page-logos">
      <img src="Pavuna Softwares/SENAI_Port_com_assinatura_cor.png" alt="Logotipo do SENAI">
      <img src="Pavuna Softwares/SESI_Port_com_assinatura_cor.png" alt="Logotipo do SESI">
    </div>
  </section>
  <?php endif; ?>

  <?php if ($podeCadastrarAluno): ?>
  <!-- VIEW: CADASTRAR ALUNO -->
  <section class="view" id="view-cadastro-aluno" aria-labelledby="titulo-cad-aluno">
    <div class="view-head">
      <h1 id="titulo-cad-aluno" tabindex="-1">Cadastrar aluno</h1>
      <p>Administradores e instrutores podem matricular novos alunos.</p>
    </div>
    <div class="form-card">
      <div class="auth-alert" id="alertaCadastroAluno" role="alert"></div>
      <form id="formCadastroAluno" class="form-stack" novalidate>
        <div class="auth-field">
          <label for="caNome">Nome completo</label>
          <input type="text" id="caNome" name="nome" required aria-required="true" autocomplete="off">
        </div>
        <div class="auth-field">
          <label for="caEmail">E-mail</label>
          <input type="email" id="caEmail" name="email" required aria-required="true" autocomplete="off">
        </div>
        <div class="auth-field">
          <label for="caSenha">Senha provisória</label>
          <input type="password" id="caSenha" name="senha" required aria-required="true" aria-describedby="caSenhaDica" autocomplete="new-password">
           <span class="auth-hint" id="caSenhaDica">Mínimo de 8 caracteres. A pessoa poderá trocar depois em “Alterar senha”.</span>
        </div>
        <div class="auth-field">
          <label for="caTurma">Turma</label>
          <select id="caTurma" name="turma" required aria-required="true"></select>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary btn-lg">Cadastrar aluno</button>
        </div>
      </form>
    </div>

    <div class="page-logos">
      <img src="Pavuna Softwares/SENAI_Port_com_assinatura_cor.png" alt="Logotipo do SENAI">
      <img src="Pavuna Softwares/SESI_Port_com_assinatura_cor.png" alt="Logotipo do SESI">
    </div>
  </section>
  <?php endif; ?>

</main>

<!-- MODAL: DETALHE DA AULA -->
<div class="overlay" id="detailOverlay" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="detalheTitulo" tabindex="-1">
    <h2 id="detalheTitulo"></h2>
    <div id="detalheCorpo"></div>
    <button type="button" class="btn btn-primary btn-block" id="fecharDetalhe" data-foco-inicial>Fechar</button>
  </div>
</div>

<!-- MODAL: ALTERAR SENHA -->
<div class="overlay" id="senhaOverlay" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="senhaTitulo" tabindex="-1">
    <h2 id="senhaTitulo">Alterar senha</h2>
    <div class="auth-alert" id="alertaSenha" role="alert"></div>
    <form id="formSenha" class="form-stack" novalidate>
      <div class="auth-field">
        <label for="senhaAtual">Senha atual</label>
        <input type="password" id="senhaAtual" required aria-required="true" autocomplete="current-password" data-foco-inicial>
      </div>
      <div class="auth-field">
        <label for="senhaNova">Nova senha</label>
        <input type="password" id="senhaNova" required aria-required="true" aria-describedby="senhaNovaDica" autocomplete="new-password">
        <span class="auth-hint" id="senhaNovaDica">Mínimo de 8 caracteres.</span>
      </div>
      <div class="auth-field">
        <label for="senhaConfirma">Confirmar nova senha</label>
        <input type="password" id="senhaConfirma" required aria-required="true" autocomplete="new-password">
      </div>
      <div class="form-actions form-actions-row">
        <button type="submit" class="btn btn-primary">Salvar nova senha</button>
        <button type="button" class="btn btn-outline" id="cancelarSenha">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>window.PAVUNA_SESSAO = <?= json_encode(['nome' => $nome, 'tipo' => $tipo, 'rotulo' => $rotuloTipo, 'csrf' => tokenCSRF()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="script.js"></script>
</body>
</html>
