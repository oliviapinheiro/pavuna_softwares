<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

// =============================================================================
// CADASTRO INICIAL E CRIAÇÃO CONTROLADA DE USUÁRIOS
// =============================================================================

$dados = lerJSON();
$nome = validarNome($dados['nome'] ?? '');
$email = validarEmail($dados['email'] ?? '');
$senha = validarSenha($dados['senha'] ?? '');
$tipo = is_string($dados['tipo'] ?? null) ? $dados['tipo'] : '';

if (!in_array($tipo, ['coordenador', 'instrutor', 'aluno'], true)) {
    responderErro('Selecione um tipo de conta válido.', 422);
}

$pdo = getConexao();

// O cadastro sem autenticação só existe até o primeiro coordenador ser criado.
$jaTemAdm = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'coordenador'")->fetchColumn() > 0;
if ($jaTemAdm && ($_SESSION['usuario_tipo'] ?? '') !== 'coordenador') {
    responderErro('O cadastro público está desativado. Peça a um administrador para criar a sua conta.', 403);
}
if (!$jaTemAdm && $tipo !== 'coordenador') {
    responderErro('Configuração inicial: crie primeiro a conta de administrador (coordenador).', 403);
}

$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    responderErro('Já existe uma conta com esse e-mail.', 409);
}

try {
    $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)');
    $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $tipo]);
} catch (PDOException $e) {
    error_log('[pavuna] Falha ao criar usuário: ' . $e->getMessage());
    responderErro('Não foi possível criar a conta. Confira os dados e tente novamente.', 500);
}

echo json_encode(['sucesso' => true, 'mensagem' => 'Conta criada com sucesso!'], JSON_UNESCAPED_UNICODE);
