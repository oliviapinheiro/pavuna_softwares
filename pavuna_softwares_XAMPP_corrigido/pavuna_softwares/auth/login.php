<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

// =============================================================================
// AUTENTICAÇÃO
// =============================================================================

$dados = lerJSON();
$email = validarEmail($dados['email'] ?? '');
$senha = is_string($dados['senha'] ?? null) ? $dados['senha'] : '';

if ($senha === '') {
    responderErro('Informe e-mail e senha.', 422);
}

$stmt = getConexao()->prepare('SELECT id, nome, email, senha, tipo, foto, ativo FROM usuarios WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($senha, $usuario['senha'])) {
    responderErro('E-mail ou senha incorretos.', 401);
}

if ((int) $usuario['ativo'] !== 1) {
    responderErro('Este usuário está desativado. Procure a coordenação.', 403);
}

// Evita fixação de sessão depois que as credenciais foram confirmadas.
session_regenerate_id(true);
$_SESSION['usuario_id'] = (int) $usuario['id'];
$_SESSION['usuario_nome'] = $usuario['nome'];
$_SESSION['usuario_email'] = $usuario['email'];
$_SESSION['usuario_tipo'] = $usuario['tipo'];
$_SESSION['usuario_foto'] = $usuario['foto'];
tokenCSRF();

echo json_encode([
    'sucesso' => true,
    'usuario' => [
        'nome' => $usuario['nome'],
        'tipo' => $usuario['tipo'],
        'foto' => $usuario['foto'],
    ],
], JSON_UNESCAPED_UNICODE);
