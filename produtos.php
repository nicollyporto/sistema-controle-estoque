<?php
require __DIR__ . '/banco.php';
$erro = '';
$edicao = null;
$valores = ['nome' => '', 'preco' => '', 'quantidade' => '0', 'categoria_id' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verificarToken();
        $acao = campo('acao');
        if ($acao === 'excluir') {
            $consulta = $pdo->prepare('DELETE FROM produtos WHERE id = ?');
            $consulta->execute([inteiro(campo('id'))]);
            if (!$consulta->rowCount()) {
                throw new InvalidArgumentException('Produto não encontrado.');
            }
            sucesso('produtos.php', 'Produto excluído.');
        }
        if (!in_array($acao, ['cadastrar', 'editar'], true)) {
            throw new InvalidArgumentException('Ação inválida.');
        }
        foreach ($valores as $campo => $valor) {
            $valores[$campo] = campo($campo);
        }
        if ($acao === 'editar') {
            $edicao = inteiro(campo('id'));
        }
        if ($valores['nome'] === '') {
            throw new InvalidArgumentException('Informe o nome do produto.');
        }
        $precoTexto = str_replace(',', '.', $valores['preco']);
        if (!preg_match('/^[0-9]+(?:\\.[0-9]{1,2})?$/D', $precoTexto)
            || !is_finite((float) $precoTexto)) {
            throw new InvalidArgumentException('Informe um preço não negativo com até duas casas decimais.');
        }
        $preco = (float) $precoTexto;
        $categoriaId = inteiro($valores['categoria_id']);
        $consulta = $pdo->prepare('SELECT id FROM categorias WHERE id = ?');
        $consulta->execute([$categoriaId]);
        if (!$consulta->fetch()) {
            throw new InvalidArgumentException('Selecione uma categoria existente.');
        }

        if ($edicao !== null) {
            // O saldo só muda por movimentações após o cadastro.
            $consulta = $pdo->prepare('UPDATE produtos SET nome = ?, preco = ?, categoria_id = ? WHERE id = ?');
            $consulta->execute([$valores['nome'], $preco, $categoriaId, $edicao]);
            if (!$consulta->rowCount()) {
                throw new InvalidArgumentException('Produto não encontrado.');
            }
        } else {
            $quantidade = inteiro($valores['quantidade'], 0);
            // Um saldo inicial positivo também fica registrado no histórico.
            $pdo->beginTransaction();
            $consulta = $pdo->prepare('INSERT INTO produtos (nome, preco, quantidade, categoria_id) VALUES (?, ?, ?, ?)');
            $consulta->execute([$valores['nome'], $preco, $quantidade, $categoriaId]);
            $produtoId = $pdo->lastInsertId();
            if ($quantidade > 0) {
                $consulta = $pdo->prepare("INSERT INTO movimentacoes (produto_id, tipo, quantidade, data) VALUES (?, 'entrada', ?, ?)");
                $consulta->execute([$produtoId, $quantidade, date('Y-m-d H:i:s')]);
            }
            $pdo->commit();
        }
        sucesso('produtos.php', $edicao ? 'Produto atualizado.' : 'Produto cadastrado.');
    } elseif (isset($_GET['editar'])) {
        $edicao = inteiro(is_string($_GET['editar']) ? $_GET['editar'] : '');
        $consulta = $pdo->prepare('SELECT * FROM produtos WHERE id = ?');
        $consulta->execute([$edicao]);
        $produto = $consulta->fetch();
        if (!$produto) {
            $edicao = null;
            throw new InvalidArgumentException('Produto não encontrado.');
        }
        $valores = $produto;
    }
} catch (InvalidArgumentException $e) {
    $erro = $e->getMessage();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $erro = $e->getCode() === '23000'
        ? 'Não foi possível concluir. Produtos com movimentações não podem ser excluídos; a categoria escolhida também precisa existir.'
        : 'Não foi possível salvar. Tente novamente.';
}
$categorias = $pdo->query('SELECT id, nome FROM categorias ORDER BY nome, id')->fetchAll();
$produtos = $pdo->query('SELECT p.*, c.nome AS categoria FROM produtos p JOIN categorias c ON c.id = p.categoria_id ORDER BY p.nome, p.id')->fetchAll();
cabecalho('Produtos', 'Cadastre seus produtos e acompanhe o saldo disponível.', 'produtos.php');
?>
<?php if ($erro): ?><p class="alerta erro" role="alert"><?= h($erro) ?></p><?php endif; ?>
<?php if (!$categorias): ?><p class="alerta aviso">Para cadastrar produtos, <a href="categorias.php">crie uma categoria primeiro</a>.</p><?php endif; ?>
<div class="grade-conteudo">
    <section class="card">
        <h2><?= $edicao ? 'Editar produto' : 'Novo produto' ?></h2>
        <p class="texto-apoio">Preencha as informações do produto.</p>
        <form method="post" action="produtos.php" class="formulario">
            <?php token(); ?>
            <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'cadastrar' ?>">
            <?php if ($edicao): ?><input type="hidden" name="id" value="<?= h($edicao) ?>"><?php endif; ?>
            <label for="nome">Nome do produto</label>
            <input id="nome" name="nome" value="<?= h($valores['nome']) ?>" placeholder="Ex.: Caderno" required>
            <label for="preco">Preço (R$)</label>
            <input id="preco" name="preco" type="number" min="0" step="0.01" value="<?= h(str_replace(',', '.', $valores['preco'])) ?>" placeholder="0,00" required>
            <label for="categoria_id">Categoria</label>
            <select id="categoria_id" name="categoria_id" required>
                <option value="">Selecione uma categoria</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= h($categoria['id']) ?>" <?= (string) $valores['categoria_id'] === (string) $categoria['id'] ? 'selected' : '' ?>><?= h($categoria['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!$edicao): ?>
                <label for="quantidade">Quantidade inicial</label>
                <input id="quantidade" name="quantidade" type="number" min="0" max="2147483647" step="1" value="<?= h($valores['quantidade']) ?>" aria-describedby="ajuda-saldo" required>
                <small id="ajuda-saldo">Pode ser zero. Um saldo positivo será registrado como entrada no histórico.</small>
            <?php else: ?>
                <p class="nota">Para alterar a quantidade em estoque, registre uma <a href="movimentacoes.php">movimentação</a>.</p>
            <?php endif; ?>
            <div class="acoes">
                <button type="submit" <?= !$categorias ? 'disabled' : '' ?>><?= $edicao ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
                <?php if ($edicao): ?><a class="botao secundario" href="produtos.php">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </section>
    <section class="card">
        <div class="cabecalho-card"><h2>Produtos cadastrados</h2><span class="contador"><?= count($produtos) ?></span></div>
        <?php if (!$produtos): ?>
            <div class="vazio"><h3>Seu estoque começa aqui</h3><p>Cadastre um produto para acompanhar suas entradas e saídas.</p></div>
        <?php else: ?>
        <div class="tabela-container"><table>
            <thead><tr><th scope="col">Produto</th><th scope="col">Categoria</th><th scope="col">Preço</th><th scope="col">Estoque</th><th scope="col">Ações</th></tr></thead>
            <tbody><?php foreach ($produtos as $produto): ?>
                <tr>
                    <td class="destaque"><?= h($produto['nome']) ?></td>
                    <td><?= h($produto['categoria']) ?></td>
                    <td class="sem-quebra">R$ <?= number_format($produto['preco'], 2, ',', '.') ?></td>
                    <td><span class="etiqueta <?= $produto['quantidade'] == 0 ? 'neutra' : '' ?>"><?= h($produto['quantidade']) ?> un.</span></td>
                    <td><div class="acoes">
                        <a class="botao pequeno secundario" href="produtos.php?editar=<?= h($produto['id']) ?>">Editar<span class="sr-only"> <?= h($produto['nome']) ?></span></a>
                        <form method="post" action="produtos.php">
                            <?php token(); ?>
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= h($produto['id']) ?>">
                            <button class="pequeno perigo" type="submit">Excluir<span class="sr-only"> <?= h($produto['nome']) ?></span></button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
    </section>
</div>
<?php rodape(); ?>
