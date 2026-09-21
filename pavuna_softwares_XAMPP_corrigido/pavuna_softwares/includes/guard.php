<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// =============================================================================
// CONTROLE DE ACESSO
// =============================================================================

function usuarioLogado(): bool
{
    static $resultado = null;

    if ($resultado !== null) {
        return $resultado;
    }

    $usuarioId = $_SESSION['usuario_id'] ?? null;
    if (!$usuarioId) {
        return $resultado = false;
    }

    try {
        $stmt = getConexao()->prepare('SELECT id, nome, email, tipo, foto, ativo FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $usuarioId]);
        $linha = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('[pavuna] Falha ao validar sessão: ' . $e->getMessage());
        return $resultado = false;
    }

    if (!$linha || (int) $linha['ativo'] !== 1) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        return $resultado = false;
    }

    // A sessão é atualizada a partir do banco para não confiar em permissões antigas.
    $_SESSION['usuario_id'] = (int) $linha['id'];
    $_SESSION['usuario_nome'] = $linha['nome'];
    $_SESSION['usuario_email'] = $linha['email'];
    $_SESSION['usuario_tipo'] = $linha['tipo'];
    $_SESSION['usuario_foto'] = $linha['foto'];

    return $resultado = true;
}

function exigirLogin(): void
{
    if (!usuarioLogado()) {
        header('Location: login.html');
        exit;
    }
}

function exigirLoginApi(): void
{
    if (!usuarioLogado()) {
        responderErro('Sessão expirada. Faça login novamente.', 401);
    }
}

function exigirPerfilApi(array $tiposPermitidos): void
{
    exigirLoginApi();

    if (!in_array($_SESSION['usuario_tipo'] ?? '', $tiposPermitidos, true)) {
        responderErro('Seu perfil não tem permissão para essa ação.', 403);
    }
}

function tratarErroBanco(PDOException $e): never
{
    error_log('[pavuna] Erro de banco: ' . $e->getMessage());

    if (in_array($e->getCode(), ['42S22', '42S02'], true)) {
        responderErro('O banco de dados não está usando o schema oficial. Importe somente o arquivo database.sql.', 500);
    }

    responderErro('Não foi possível concluir a operação no banco de dados.', 500);
}