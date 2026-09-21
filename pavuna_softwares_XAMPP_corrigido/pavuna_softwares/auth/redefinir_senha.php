<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

// =============================================================================
// CONSUMO DE TOKEN E REDEFINIÇÃO DE SENHA
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Método não permitido.', 405);
}

$dados = lerJSON();
$token = is_string($dados['token'] ?? null) ? $dados['token'] : '';
$nova = validarSenha($dados['nova_senha'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    responderErro('Link inválido ou expirado. Peça um novo link na tela de login.', 400);
}

$pdo = getConexao();
$stmt = $pdo->prepare(
    'SELECT r.id, r.usuario_id
     FROM redefinicoes_senha r
     JOIN usuarios u ON u.id = r.usuario_id
     WHERE r.token_hash = ? AND r.usado = 0 AND r.expira_em > NOW() AND u.ativo = 1
     LIMIT 1'
);
$stmt->execute([hash('sha256', $token)]);
$pedido = $stmt->fetch();

if (!$pedido) {
    responderErro('Link inválido ou expirado. Peça um novo link na tela de login.', 400);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
    $stmt->execute([password_hash($nova, PASSWORD_DEFAULT), $pedido['usuario_id']]);
    $stmt = $pdo->prepare('UPDATE redefinicoes_senha SET usado = 1 WHERE usuario_id = ?');
    $stmt->execute([$pedido['usuario_id']]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[pavuna] Falha ao redefinir senha: ' . $e->getMessage());
    responderErro('Não foi possível redefinir a senha. Tente novamente.', 500);
}

echo json_encode(['sucesso' => true, 'mensagem' => 'Senha redefinida com sucesso! Você já pode entrar.'], JSON_UNESCAPED_UNICODE);