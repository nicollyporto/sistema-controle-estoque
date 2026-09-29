<?php
// Todas as páginas incluem este arquivo antes de enviar HTML.
session_start();
date_default_timezone_set('America/Sao_Paulo');

try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/banco.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec(file_get_contents(__DIR__ . '/banco.sql'));
} catch (PDOException $e) {
    http_response_code(500);
    exit('Não foi possível abrir o banco. Verifique a extensão pdo_sqlite e a permissão de escrita na pasta.');
}

// Pequenas funções compartilhadas evitam repetir HTML e validações.
function h($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function campo(string $nome): string
{
    $valor = $_POST[$nome] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

function inteiro(string $valor, int $minimo = 1): int
{
    $numero = filter_var($valor, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $minimo, 'max_range' => 2147483647],
    ]);
    if ($numero === false) {
        throw new InvalidArgumentException('Informe uma quantidade ou identificador inteiro válido (mínimo ' . $minimo . ').');
    }
    return $numero;
}

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

function token(): void
{
    echo '<input type="hidden" name="token" value="' . h($_SESSION['token']) . '">';
}

function verificarToken(): void
{
    if (!hash_equals($_SESSION['token'], campo('token'))) {
        throw new InvalidArgumentException('O formulário expirou. Recarregue a página e tente novamente.');
    }
}

function sucesso(string $pagina, string $mensagem): void
{
    $_SESSION['sucesso'] = $mensagem;
    // Redirecionar após o POST evita repetir a operação ao atualizar a página.
    header('Location: ' . $pagina, true, 303);
    exit;
}

function cabecalho(string $titulo, string $descricao, string $pagina): void
{
    $links = ['index.php' => 'Visão geral', 'categorias.php' => 'Categorias',
        'produtos.php' => 'Produtos', 'movimentacoes.php' => 'Movimentações'];
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($titulo) ?> | Estoque</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <a class="pular" href="#conteudo">Pular para o conteúdo</a>
    <header class="topo">
        <a class="marca" href="index.php"><span class="simbolo" aria-hidden="true">E</span> Estoque<span class="marca-detalhe">controle simples</span></a>
        <nav aria-label="Navegação principal">
            <?php foreach ($links as $arquivo => $rotulo): ?>
                <a href="<?= h($arquivo) ?>" <?= $pagina === $arquivo ? 'class="ativo" aria-current="page"' : '' ?>><?= h($rotulo) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>
    <main id="conteudo" class="container">
        <div class="titulo-pagina"><span class="sobretitulo">SISTEMA DE CONTROLE DE ESTOQUE</span>
            <h1><?= h($titulo) ?></h1><p><?= h($descricao) ?></p>
        </div>
        <?php if (isset($_SESSION['sucesso'])): ?>
            <p class="alerta sucesso" role="status"><?= h($_SESSION['sucesso']) ?></p>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>
    <?php
}

function rodape(): void
{
    echo '</main><footer class="rodape">Estoque · Projeto acadêmico em PHP + SQLite</footer></body></html>';
}
