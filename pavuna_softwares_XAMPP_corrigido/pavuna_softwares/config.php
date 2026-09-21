<?php
declare(strict_types=1);

// =============================================================================
// CONFIGURAÇÃO CENTRAL — o banco oficial usa as tabelas no plural:
// usuarios, turmas, aulas, matriculas e redefinicoes_senha.
// =============================================================================

$configLocal = __DIR__ . '/config.local.php';
if (is_readable($configLocal)) {
    require_once $configLocal;
}

function pavunaSetting(string $name, string $default = ''): string
{
    $environmentValue = getenv($name);
    if ($environmentValue !== false) {
        return (string) $environmentValue;
    }

    return defined($name) ? (string) constant($name) : $default;
}

define('DB_HOST', pavunaSetting('PAVUNA_DB_HOST', '127.0.0.1'));
define('DB_PORT', pavunaSetting('PAVUNA_DB_PORT', '3307'));
define('DB_NAME', pavunaSetting('PAVUNA_DB_NAME', 'pavuna_softwares'));
define('DB_USER', pavunaSetting('PAVUNA_DB_USER', 'root'));
define('DB_PASS', pavunaSetting('PAVUNA_DB_PASS', ''));
define('SITE_URL', rtrim(pavunaSetting('PAVUNA_SITE_URL', 'http://localhost/pavuna_softwares'), '/'));
define('MAIL_FROM', pavunaSetting('PAVUNA_MAIL_FROM', 'no-reply@pavuna.local'));
define('MAIL_LOG_DEV', filter_var(pavunaSetting('PAVUNA_MAIL_LOG_DEV', 'true'), FILTER_VALIDATE_BOOLEAN));

// =============================================================================
// CABEÇALHOS E SESSÃO
// =============================================================================

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

// =============================================================================
// ACESSO AO BANCO
// =============================================================================

function getConexao(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('[pavuna] Falha ao conectar ao MySQL: ' . $e->getMessage());
        http_response_code(500);
        responderJSON(false, 'Não foi possível conectar ao banco de dados. Confira o host, a porta, o usuário e a senha da conexão.', [], 500);
    }

    return $pdo;
}

// =============================================================================
// RESPOSTAS E ENTRADAS HTTP
// =============================================================================

function responderJSON(bool $sucesso, string $mensagem, array $dadosAdicionais = [], int $codigoHttp = 200): never
{
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        array_merge(['sucesso' => $sucesso, 'mensagem' => $mensagem], $dadosAdicionais),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function lerJSON(): array
{
    $conteudo = file_get_contents('php://input');
    if (!is_string($conteudo) || trim($conteudo) === '') {
        return $_POST;
    }

    $dados = json_decode($conteudo, true);
    return is_array($dados) ? $dados : [];
}

function responderErro(string $mensagem, int $codigoHttp = 400): never
{
    responderJSON(false, $mensagem, ['erro' => $mensagem], $codigoHttp);
}

// =============================================================================
// PROTEÇÃO CONTRA CSRF
// =============================================================================

function tokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function exigirCSRF(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!is_string($token) || $token === '' || !hash_equals(tokenCSRF(), $token)) {
        responderErro('A sessão de segurança expirou. Atualize a página e tente novamente.', 419);
    }
}

// =============================================================================
// VALIDAÇÕES REUTILIZÁVEIS
// =============================================================================

function validarNome(mixed $nome): string
{
    $nome = is_string($nome) ? trim($nome) : '';
    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 150) {
        responderErro('Informe um nome entre 2 e 150 caracteres.', 422);
    }

    return $nome;
}

function validarEmail(mixed $email): string
{
    $email = is_string($email) ? strtolower(trim($email)) : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        responderErro('Informe um e-mail válido.', 422);
    }

    return $email;
}

function validarSenha(mixed $senha, int $minimo = 8): string
{
    $senha = is_string($senha) ? $senha : '';
    if (strlen($senha) < $minimo || strlen($senha) > 255) {
        responderErro("A senha precisa ter entre {$minimo} e 255 caracteres.", 422);
    }

    return $senha;
}

function validarId(mixed $id, string $campo = 'Identificador'): int
{
    $valor = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($valor === false) {
        responderErro("{$campo} inválido.", 422);
    }

    return (int) $valor;
}