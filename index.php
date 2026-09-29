<?php
require __DIR__ . '/banco.php';
$totalCategorias = $pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn();
$totalProdutos = $pdo->query('SELECT COUNT(*) FROM produtos')->fetchColumn();
$totalMovimentacoes = $pdo->query('SELECT COUNT(*) FROM movimentacoes')->fetchColumn();
cabecalho('Seu estoque, organizado.', 'Uma visão simples para cuidar dos produtos e acompanhar cada movimentação.', 'index.php');
?>
<section class="resumo" aria-label="Resumo do estoque">
    <article class="card indicador"><span class="icone" aria-hidden="true">01</span><p>Categorias</p><strong><?= h($totalCategorias) ?></strong><span>Grupos de produtos cadastrados</span></article>
    <article class="card indicador"><span class="icone" aria-hidden="true">02</span><p>Produtos</p><strong><?= h($totalProdutos) ?></strong><span>Produtos cadastrados no sistema</span></article>
    <article class="card indicador"><span class="icone" aria-hidden="true">03</span><p>Movimentações</p><strong><?= h($totalMovimentacoes) ?></strong><span>Entradas e saídas registradas</span></article>
</section>
<div class="titulo-secao"><h2>O que você quer fazer?</h2><p>Acesse as áreas para gerenciar seu estoque.</p></div>
<section class="atalhos" aria-label="Atalhos">
    <a class="card atalho" href="categorias.php"><span class="etiqueta">Organização</span><h3>Gerenciar categorias</h3><p>Crie e organize os grupos dos seus produtos.</p><span class="link-atalho">Acessar categorias <span aria-hidden="true">→</span></span></a>
    <a class="card atalho" href="produtos.php"><span class="etiqueta">Cadastro</span><h3>Gerenciar produtos</h3><p>Cadastre itens, defina preços e consulte o saldo.</p><span class="link-atalho">Acessar produtos <span aria-hidden="true">→</span></span></a>
    <a class="card atalho" href="movimentacoes.php"><span class="etiqueta">Controle</span><h3>Movimentar estoque</h3><p>Registre entradas e saídas e consulte o histórico.</p><span class="link-atalho">Acessar movimentações <span aria-hidden="true">→</span></span></a>
</section>
<?php rodape(); ?>
