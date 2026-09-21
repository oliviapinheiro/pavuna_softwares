<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/guard.php';
header('Content-Type: application/json; charset=utf-8');

// =============================================================================
// CONSULTA E CADASTRO DE INSTRUTORES
// =============================================================================

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    exigirPerfilApi(['coordenador', 'instrutor']);
    $pdo = getConexao();
    $filtroAtivos = $_SESSION['usuario_tipo'] === 'coordenador' ? '' : 'AND ativo = 1';

    try {
        $sql = "SELECT id, nome, email, ativo
                FROM usuarios
                WHERE tipo = 'instrutor' {$filtroAtivos}
                ORDER BY nome";
        echo json_encode($pdo->query($sql)->fetchAll(), JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        tratarErroBanco($e);
    }
    exit;
}

if ($metodo === 'POST') {
    exigirPerfilApi(['coordenador']);
    exigirCSRF();
    $pdo = getConexao();

    $dados = lerJSON();
    $nome = validarNome($dados['nome'] ?? '');
    $email = validarEmail($dados['email'] ?? '');
    $senha = validarSenha($dados['senha'] ?? '');

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        responderErro('Já existe uma conta com esse e-mail.', 409);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'instrutor')");
        $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
    } catch (PDOException $e) {
        error_log('[pavuna] Falha ao cadastrar instrutor: ' . $e->getMessage());
        responderErro('Não foi possível cadastrar o instrutor. Confira os dados e tente novamente.', 500);
    }

    echo json_encode(['sucesso' => true, 'id' => (int) $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    exit;
}

responderErro('Método não permitido.', 405);