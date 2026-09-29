<?php
require __DIR__ . '/banco.php';
$erro = '';
$valores = ['produto_id' => '', 'tipo' => 'entrada', 'quantidade' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verificarToken();
        foreach ($valores as $campo => $valor) {
            $valores[$campo] = campo($campo);
        }
        $produtoId = inteiro($valores['produto_id']);
        $quantidade = inteiro($valores['quantidade']);
        $tipo = $valores['tipo'];
        if (!in_array($tipo, ['entrada', 'saida'], true)) {
            throw new InvalidArgumentException('Selecione entrada ou saída.');
        }

        $pdo->beginTransaction();
        // A condição na própria atualização impede saídas acima do saldo.
        if ($tipo === 'saida') {
            $consulta = $pdo->prepare('UPDATE produtos SET quantidade = quantidade - ? WHERE id = ? AND quantidade >= ?');
            $consulta->execute([$quantidade, $produtoId, $quantidade]);
        } else {
            $consulta = $pdo->prepare('UPDATE produtos SET quantidade = quantidade + ? WHERE id = ? AND quantidade <= ?');
            $consulta->execute([$quantidade, $produtoId, 2147483647 - $quantidade]);
        }
        if (!$consulta->rowCount()) {
            throw new InvalidArgumentException($tipo === 'saida'
                ? 'Estoque insuficiente para esta saída ou produto não encontrado.'
                : 'Produto não encontrado ou quantidade acima do limite permitido.');
        }
        $consulta = $pdo->prepare('INSERT INTO movimentacoes (produto_id, tipo, quantidade, data) VALUES (?, ?, ?, ?)');
        $consulta->execute([$produtoId, $tipo, $quantidade, date('Y-m-d H:i:s')]);
        $pdo->commit();
        sucesso('movimentacoes.php', 'Movimentação registrada e estoque atualizado.');
    }
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $erro = $e->getMessage();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $erro = 'Não foi possível registrar a movimentação. Tente novamente.';
}
$produtos = $pdo->query('SELECT id, nome, quantidade FROM produtos ORDER BY nome, id')->fetchAll();
$movimentacoes = $pdo->query('SELECT m.*, p.nome AS produto FROM movimentacoes m JOIN produtos p ON p.id = m.produto_id ORDER BY m.data DESC, m.id DESC')->fetchAll();
cabecalho('Movimentações', 'Registre entradas e saídas e acompanhe o histórico do seu estoque.', 'movimentacoes.php');
?>
<?php if ($erro): ?><p class="alerta erro" role="alert"><?= h($erro) ?></p><?php endif; ?>
<?php if (!$produtos): ?><p class="alerta aviso">Para movimentar o estoque, <a href="produtos.php">cadastre um produto primeiro</a>.</p><?php endif; ?>
<div class="grade-conteudo">
    <section class="card">
        <h2>Nova movimentação</h2>
        <p class="texto-apoio">O saldo será atualizado automaticamente.</p>
        <form method="post" action="movimentacoes.php" class="formulario">
            <?php token(); ?>
            <label for="produto_id">Produto</label>
            <select id="produto_id" name="produto_id" required>
                <option value="">Selecione um produto</option>
                <?php foreach ($produtos as $produto): ?>
                    <option value="<?= h($produto['id']) ?>" <?= (string) $valores['produto_id'] === (string) $produto['id'] ? 'selected' : '' ?>><?= h($produto['nome']) ?> · <?= h($produto['quantidade']) ?> un.</option>
                <?php endforeach; ?>
            </select>
            <label for="tipo">Tipo de movimentação</label>
            <select id="tipo" name="tipo" required>
                <option value="entrada" <?= $valores['tipo'] === 'entrada' ? 'selected' : '' ?>>Entrada — adicionar ao estoque</option>
                <option value="saida" <?= $valores['tipo'] === 'saida' ? 'selected' : '' ?>>Saída — retirar do estoque</option>
            </select>
            <label for="quantidade">Quantidade</label>
            <input id="quantidade" name="quantidade" type="number" min="1" max="2147483647" step="1" value="<?= h($valores['quantidade']) ?>" placeholder="Ex.: 10" required>
            <p class="nota">Uma saída precisa ser menor ou igual ao saldo disponível.</p>
            <button type="submit" <?= !$produtos ? 'disabled' : '' ?>>Registrar movimentação</button>
        </form>
    </section>
    <section class="card">
        <div class="cabecalho-card"><h2>Histórico de movimentações</h2><span class="contador"><?= count($movimentacoes) ?></span></div>
        <?php if (!$movimentacoes): ?>
            <div class="vazio"><h3>Nenhuma movimentação por enquanto</h3><p>As entradas e saídas registradas aparecerão aqui, da mais recente para a mais antiga.</p></div>
        <?php else: ?>
        <div class="tabela-container"><table>
            <thead><tr><th scope="col">Produto</th><th scope="col">Tipo</th><th scope="col">Quantidade</th><th scope="col">Data</th></tr></thead>
            <tbody><?php foreach ($movimentacoes as $movimentacao): ?>
                <tr>
                    <td class="destaque"><?= h($movimentacao['produto']) ?></td>
                    <td><span class="etiqueta <?= $movimentacao['tipo'] === 'entrada' ? 'entrada' : 'saida' ?>"><?= $movimentacao['tipo'] === 'entrada' ? 'Entrada' : 'Saída' ?></span></td>
                    <td><?= h($movimentacao['quantidade']) ?> un.</td>
                    <td class="sem-quebra"><?= h(date('d/m/Y H:i', strtotime($movimentacao['data']))) ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
    </section>
</div>
<?php rodape(); ?>
