<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/guard.php';
header('Content-Type: application/json; charset=utf-8');
exigirLoginApi();
exigirCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

$dados = lerJSON();
$atual = is_string($dados['senha_atual'] ?? null) ? $dados['senha_atual'] : '';
$nova = validarSenha($dados['nova_senha'] ?? '');

if ($atual === ''){
    http_response_code(422);
    echo json_encode(['erro' => 'Informe a senha atual.']);
    exit;
}
if ($nova === $atual){
    responderErro('A nova senha precisa ser diferente da atual.', 422);
}

$pdo = getConexao();
$stmt = $pdo->prepare('SELECT senha FROM usuarios WHERE id = ?');
$stmt->execute([$_SESSION['usuario_id']]);
$hash = $stmt->fetchColumn();

if (!$hash || !password_verify($atual, $hash)){
    responderErro('A senha atual está incorreta.', 403);
}

$stmt = $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
$stmt->execute([password_hash($nova, PASSWORD_DEFAULT), $_SESSION['usuario_id']]);
session_regenerate_id(true);

echo json_encode(['sucesso' => true, 'mensagem' => 'Senha alterada com sucesso.'], JSON_UNESCAPED_UNICODE);
