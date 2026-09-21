<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/guard.php';
header('Content-Type: application/json; charset=utf-8');

// =============================================================================
// CONSULTA E CADASTRO DE ALUNOS
// =============================================================================

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    exigirPerfilApi(['coordenador', 'instrutor']);
    $pdo = getConexao();
    $filtroAtivos = $_SESSION['usuario_tipo'] === 'coordenador' ? '' : 'AND u.ativo = 1';

    try {
        $sql = "SELECT u.id, u.nome, u.email, u.ativo,
                       t.id AS turma_id, t.codigo AS turma_codigo, t.nome AS turma_nome,
                       m.frequencia
                FROM usuarios u
                LEFT JOIN matriculas m ON m.aluno_id = u.id
                LEFT JOIN turmas t ON t.id = m.turma_id
                WHERE u.tipo = 'aluno' {$filtroAtivos}
                ORDER BY u.nome";
        echo json_encode($pdo->query($sql)->fetchAll(), JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        tratarErroBanco($e);
    }
    exit;
}

if ($metodo === 'POST') {
    exigirPerfilApi(['coordenador', 'instrutor']);
    exigirCSRF();
    $pdo = getConexao();

    $dados = lerJSON();
    $nome = validarNome($dados['nome'] ?? '');
    $email = validarEmail($dados['email'] ?? '');
    $senha = validarSenha($dados['senha'] ?? '');
    $turmaId = !empty($dados['turma_id']) ? validarId($dados['turma_id'], 'Turma') : null;

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        responderErro('Já existe uma conta com esse e-mail.', 409);
    }

    if ($turmaId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM turmas WHERE id = ? LIMIT 1');
        $stmt->execute([$turmaId]);
        if (!$stmt->fetch()) {
            responderErro('A turma selecionada não existe.', 422);
        }
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'aluno')");
        $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
        $alunoId = (int) $pdo->lastInsertId();

        if ($turmaId !== null) {
            $stmt = $pdo->prepare('INSERT INTO matriculas (aluno_id, turma_id, frequencia) VALUES (?, ?, 100)');
            $stmt->execute([$alunoId, $turmaId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[pavuna] Falha ao cadastrar aluno: ' . $e->getMessage());
        responderErro('Não foi possível cadastrar o aluno. Confira os dados e tente novamente.', 500);
    }

    echo json_encode(['sucesso' => true, 'id' => $alunoId], JSON_UNESCAPED_UNICODE);
    exit;
}

responderErro('Método não permitido.', 405);