<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

// =============================================================================
// SOLICITAÇÃO DE REDEFINIÇÃO DE SENHA
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Método não permitido.', 405);
}

$dados = lerJSON();
$email = validarEmail($dados['email'] ?? '');

// A resposta é sempre a mesma para não revelar quais e-mails existem.
$resposta = [
    'sucesso' => true,
    'mensagem' => 'Se o e-mail estiver cadastrado, enviaremos um link para redefinir a senha. O link vale por 1 hora.',
];

$pdo = getConexao();
$stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1');
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if (!$usuario) {
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Somente o hash do token é persistido.
    $pdo->exec('DELETE FROM redefinicoes_senha WHERE expira_em < NOW() OR usado = 1');
    $stmt = $pdo->prepare('DELETE FROM redefinicoes_senha WHERE usuario_id = ?');
    $stmt->execute([$usuario['id']]);

    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare(
        'INSERT INTO redefinicoes_senha (usuario_id, token_hash, expira_em)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
    );
    $stmt->execute([$usuario['id'], hash('sha256', $token)]);
} catch (PDOException $e) {
    error_log('[pavuna] Falha ao criar redefinição de senha: ' . $e->getMessage());
    responderErro('Não foi possível criar o pedido de redefinição.', 500);
}

$link = SITE_URL . '/redefinir-senha.html?token=' . $token;
$assunto = '=?UTF-8?B?' . base64_encode('Redefinição de senha - Pavuna Softwares') . '?=';
$nomeSeguro = str_replace(["\r", "\n"], ' ', $usuario['nome']);
$corpo = "Olá, {$nomeSeguro}!\r\n\r\n"
    . "Recebemos um pedido para redefinir a sua senha no sistema Pavuna Softwares.\r\n"
    . "Para criar uma nova senha, acesse o link abaixo (válido por 1 hora):\r\n\r\n"
    . $link . "\r\n\r\n"
    . "Se você não fez esse pedido, ignore esta mensagem: a sua senha continua a mesma.\r\n";
$cabecalhos = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: " . MAIL_FROM . "\r\n";

mail($email, $assunto, $corpo, $cabecalhos);

if (MAIL_LOG_DEV) {
    $pasta = __DIR__ . '/../storage';
    if (!is_dir($pasta)) {
        mkdir($pasta, 0750, true);
    }
    file_put_contents(
        $pasta . '/emails.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $email . ' -> ' . $link . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

echo json_encode($resposta, JSON_UNESCAPED_UNICODE);