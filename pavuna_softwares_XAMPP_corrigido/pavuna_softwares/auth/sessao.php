<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || !usuarioLogado()){
    http_response_code(401);
    echo json_encode(['logado' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'logado' => true,
    'nome'   => $_SESSION['usuario_nome'],
    'email'  => $_SESSION['usuario_email'],
    'tipo'   => $_SESSION['usuario_tipo'],
    'foto'   => $_SESSION['usuario_foto'],
    'csrf'   => tokenCSRF(),
]);
