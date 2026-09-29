<?php
require __DIR__ . '/banco.php';
$erro = '';
$edicao = null;
$nome = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verificarToken();
        $acao = campo('acao');
        $nome = campo('nome');
        if ($acao === 'excluir') {
            $id = inteiro(campo('id'));
            $consulta = $pdo->prepare('DELETE FROM categorias WHERE id = ?');
            $consulta->execute([$id]);
            if (!$consulta->rowCount()) {
                throw new InvalidArgumentException('Categoria não encontrada.');
            }
            sucesso('categorias.php', 'Categoria excluída.');
        }
        if (!in_array($acao, ['cadastrar', 'editar'], true)) {
            throw new InvalidArgumentException('Ação inválida.');
        }
        if ($acao === 'editar') {
            $edicao = inteiro(campo('id'));
        }
        if ($nome === '') {
            throw new InvalidArgumentException('Informe o nome da categoria.');
        }
        if ($edicao !== null) {
            $consulta = $pdo->prepare('UPDATE categorias SET nome = ? WHERE id = ?');
            $consulta->execute([$nome, $edicao]);
            if (!$consulta->rowCount()) {
                throw new InvalidArgumentException('Categoria não encontrada.');
            }
        } else {
            $consulta = $pdo->prepare('INSERT INTO categorias (nome) VALUES (?)');
            $consulta->execute([$nome]);
        }
        sucesso('categorias.php', $edicao ? 'Categoria atualizada.' : 'Categoria cadastrada.');
    } elseif (isset($_GET['editar'])) {
        $edicao = inteiro(is_string($_GET['editar']) ? $_GET['editar'] : '');
        $consulta = $pdo->prepare('SELECT * FROM categorias WHERE id = ?');
        $consulta->execute([$edicao]);
        $categoria = $consulta->fetch();
        if (!$categoria) {
            $edicao = null;
            throw new InvalidArgumentException('Categoria não encontrada.');
        }
        $nome = $categoria['nome'];
    }
} catch (InvalidArgumentException $e) {
    $erro = $e->getMessage();
} catch (PDOException $e) {
    $erro = $e->getCode() === '23000'
        ? 'Esta categoria possui produtos. Altere a categoria desses produtos antes de excluir.'
        : 'Não foi possível salvar. Tente novamente.';
}
$categorias = $pdo->query('SELECT * FROM categorias ORDER BY nome, id')->fetchAll();
cabecalho('Categorias', 'Organize os produtos por grupos e mantenha tudo fácil de encontrar.', 'categorias.php');
?>
<?php if ($erro): ?><p class="alerta erro" role="alert"><?= h($erro) ?></p><?php endif; ?>
<div class="grade-conteudo">
    <section class="card">
        <h2><?= $edicao ? 'Editar categoria' : 'Nova categoria' ?></h2>
        <p class="texto-apoio">Dê um nome ao grupo de produtos.</p>
        <form method="post" action="categorias.php" class="formulario">
            <?php token(); ?>
            <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'cadastrar' ?>">
            <?php if ($edicao): ?><input type="hidden" name="id" value="<?= h($edicao) ?>"><?php endif; ?>
            <label for="nome">Nome da categoria</label>
            <input id="nome" name="nome" value="<?= h($nome) ?>" placeholder="Ex.: Material de escritório" required>
            <div class="acoes">
                <button type="submit"><?= $edicao ? 'Salvar alterações' : 'Cadastrar categoria' ?></button>
                <?php if ($edicao): ?><a class="botao secundario" href="categorias.php">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </section>
    <section class="card">
        <div class="cabecalho-card"><h2>Categorias cadastradas</h2><span class="contador"><?= count($categorias) ?></span></div>
        <?php if (!$categorias): ?>
            <div class="vazio"><h3>Ainda não há categorias</h3><p>Cadastre a primeira categoria para começar a organizar seus produtos.</p></div>
        <?php else: ?>
        <div class="tabela-container"><table>
            <thead><tr><th scope="col">Nome</th><th scope="col">Ações</th></tr></thead>
            <tbody><?php foreach ($categorias as $categoria): ?>
                <tr><td class="destaque"><?= h($categoria['nome']) ?></td><td>
                    <div class="acoes"><a class="botao pequeno secundario" href="categorias.php?editar=<?= h($categoria['id']) ?>">Editar<span class="sr-only"> <?= h($categoria['nome']) ?></span></a>
                    <form method="post" action="categorias.php">
                        <?php token(); ?>
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?= h($categoria['id']) ?>">
                        <button class="pequeno perigo" type="submit">Excluir<span class="sr-only"> <?= h($categoria['nome']) ?></span></button>
                    </form></div>
                </td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
    </section>
</div>
<?php rodape(); ?>
