<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/guard.php';
exigirLoginApi();
header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = getConexao()->query('SELECT id, codigo, nome FROM turmas ORDER BY codigo');
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    tratarErroBanco($e);
}
