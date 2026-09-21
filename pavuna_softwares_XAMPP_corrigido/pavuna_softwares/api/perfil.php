<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/guard.php';
header('Content-Type: application/json; charset=utf-8');
exigirLoginApi();
exigirCSRF();

// =============================================================================
// ATUALIZAÇÃO DE PERFIL
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Método não permitido.', 405);
}

$pdo = getConexao();
$usuarioId = (int) $_SESSION['usuario_id'];
$nome = trim(is_string($_POST['nome'] ?? null) ? $_POST['nome'] : '');
$temFoto = isset($_FILES['foto']) && is_array($_FILES['foto']);

if ($nome === '' && !$temFoto) {
    $dados = lerJSON();
    $nome = trim(is_string($dados['nome'] ?? null) ? $dados['nome'] : '');
}

if ($nome !== '') {
    $nome = validarNome($nome);
}

if ($nome === '' && !$temFoto) {
    responderErro('Informe um nome ou selecione uma foto.', 422);
}

$caminhoPublico = null;
if ($temFoto) {
    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['foto']['tmp_name'])) {
        responderErro('Não foi possível receber a imagem enviada.', 422);
    }
    if ((int) $_FILES['foto']['size'] > 3 * 1024 * 1024) {
        responderErro('A imagem deve ter no máximo 3 MB.', 422);
    }

    $tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $tipoArquivo = mime_content_type($_FILES['foto']['tmp_name']);
    if (!is_string($tipoArquivo) || !isset($tiposPermitidos[$tipoArquivo])) {
        responderErro('Envie uma imagem JPG, PNG ou WEBP.', 422);
    }

    $diretorio = __DIR__ . '/../uploads/fotos';
    if (!is_dir($diretorio) && !mkdir($diretorio, 0750, true) && !is_dir($diretorio)) {
        responderErro('Não foi possível preparar o armazenamento da foto.', 500);
    }

    $nomeArquivo = 'usuario_' . $usuarioId . '_' . bin2hex(random_bytes(12)) . '.' . $tiposPermitidos[$tipoArquivo];
    $destino = $diretorio . '/' . $nomeArquivo;
    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
        responderErro('Não foi possível salvar a foto no servidor.', 500);
    }
    $caminhoPublico = 'uploads/fotos/' . $nomeArquivo;
}

try {
    if ($nome !== '' && $caminhoPublico !== null) {
        $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, foto = ? WHERE id = ?');
        $stmt->execute([$nome, $caminhoPublico, $usuarioId]);
        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['usuario_foto'] = $caminhoPublico;
    } elseif ($nome !== '') {
        $stmt = $pdo->prepare('UPDATE usuarios SET nome = ? WHERE id = ?');
        $stmt->execute([$nome, $usuarioId]);
        $_SESSION['usuario_nome'] = $nome;
    } elseif ($caminhoPublico !== null) {
        $stmt = $pdo->prepare('UPDATE usuarios SET foto = ? WHERE id = ?');
        $stmt->execute([$caminhoPublico, $usuarioId]);
        $_SESSION['usuario_foto'] = $caminhoPublico;
    }
} catch (PDOException $e) {
    error_log('[pavuna] Falha ao atualizar perfil: ' . $e->getMessage());
    responderErro('Não foi possível atualizar o perfil.', 500);
}

echo json_encode([
    'sucesso' => true,
    'nome' => $_SESSION['usuario_nome'],
    'foto' => $_SESSION['usuario_foto'] ?? null,
], JSON_UNESCAPED_UNICODE);