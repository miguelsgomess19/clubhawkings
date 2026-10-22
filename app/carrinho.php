<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../login/verifica_user.php';

$urlCarrinho = '/clubhawkings/app/carrinho.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Botão "Remover" de cada linha da tabela.
    if (isset($_POST['remover'])) {
        carrinho_remover((int) $_POST['remover']);
        set_flash('success', 'Item removido do carrinho.');
        redirecionar($urlCarrinho);
    }

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'limpar') {
        carrinho_limpar();
        set_flash('success', 'Carrinho esvaziado.');
        redirecionar($urlCarrinho);
    }

    if ($acao === 'finalizar') {
        $resultado = carrinho_finalizar($conexao);

        if ($resultado['ok']) {
            set_flash('success', 'Compra finalizada com sucesso!');
        } else {
            set_flash('error', $resultado['erro']);
        }

        redirecionar($urlCarrinho);
    }

    if ($acao === 'atualizar') {
        $quantidades = $_POST['quantidade'] ?? [];
        $erro = null;

        if (is_array($quantidades)) {
            foreach ($quantidades as $id => $quantidade) {
                $resultado = carrinho_alterar($conexao, (int) $id, (int) $quantidade);

                if (!$resultado['ok'] && $erro === null) {
                    $erro = $resultado['erro'];
                }
            }
        }

        if ($erro !== null) {
            set_flash('error', $erro);
        } else {
            set_flash('success', 'Carrinho atualizado.');
        }

        redirecionar($urlCarrinho);
    }

    redirecionar($urlCarrinho);
}

$itens = carrinho_sincronizar($conexao);
$total = carrinho_total($itens);
$quantidadeTotal = carrinho_contagem();
$flash = get_flash();
$pedido = get_ultimo_pedido();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/clubhawkings/style/style.css">
    <title>Carrinho</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <h1>Meu carrinho</h1>

        <?php if ($flash): ?>
            <p class="alert alert--<?= $flash['tipo'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['mensagem']) ?>
            </p>
        <?php endif; ?>

        <?php if ($pedido): ?>
            <section class="panel recibo">
                <h2>Compra finalizada!</h2>
                <p class="muted">Pedido realizado em <?= htmlspecialchars($pedido['data']) ?></p>

                <ul class="recibo__itens">
                    <?php foreach ($pedido['itens'] as $item): ?>
                        <li>
                            <span>
                                <?= (int) $item['quantidade'] ?> x <?= htmlspecialchars($item['nome']) ?>
                                <small class="muted">
                                    (R$ <?= number_format((float) $item['preco'], 2, ',', '.') ?> cada)
                                </small>
                            </span>
                            <strong>R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p class="recibo__total">
                    Total pago: <strong>R$ <?= number_format((float) $pedido['total'], 2, ',', '.') ?></strong>
                </p>

                <div class="cart-acoes">
                    <a class="btn btn--solid" href="/clubhawkings/app/produtos.php">Continuar comprando</a>
                </div>
            </section>
        <?php endif; ?>

        <?php if (empty($itens)): ?>
            <div class="panel carrinho-vazio">
                <h2>Seu carrinho está vazio</h2>
                <p>Adicione produtos na página de produtos para começar a comprar.</p>
                <a class="btn btn--solid" href="/clubhawkings/app/produtos.php">Ver produtos</a>
            </div>
        <?php else: ?>
            <form class="cart-form" action="/clubhawkings/app/carrinho.php" method="POST">
                <div class="table-wrap">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Preço</th>
                                <th>Quantidade</th>
                                <th>Subtotal</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $item): ?>
                                <?php
                                $id = (int) $item['id'];
                                $quantidade = (int) $item['quantidade'];
                                $preco = (float) $item['preco'];
                                $subtotal = $preco * $quantidade;
                                ?>
                                <tr>
                                    <td>
                                        <div class="cart-produto">
                                            <img src="/clubhawkings/uploads/<?= htmlspecialchars($item['imagem']) ?>"
                                                 alt="<?= htmlspecialchars($item['nome']) ?>">
                                            <div>
                                                <strong><?= htmlspecialchars($item['nome']) ?></strong>
                                                <small class="muted">Estoque: <?= (int) $item['estoque'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>R$ <?= number_format($preco, 2, ',', '.') ?></td>
                                    <td>
                                        <input type="number"
                                               name="quantidade[<?= $id ?>]"
                                               value="<?= $quantidade ?>"
                                               min="1"
                                               max="<?= (int) $item['estoque'] ?>"
                                               step="1"
                                               inputmode="numeric"
                                               aria-label="Quantidade de <?= htmlspecialchars($item['nome']) ?>">
                                    </td>
                                    <td>R$ <?= number_format($subtotal, 2, ',', '.') ?></td>
                                    <td>
                                        <button class="btn btn--danger btn--sm"
                                                type="submit"
                                                name="remover"
                                                value="<?= $id ?>"
                                                formaction="/clubhawkings/app/carrinho.php"
                                                formnovalidate>
                                            Remover
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cart-resumo">
                    <p>
                        Itens no carrinho:
                        <strong><?= (int) $quantidadeTotal ?></strong>
                    </p>
                    <p class="cart-total">
                        Total: <strong>R$ <?= number_format($total, 2, ',', '.') ?></strong>
                    </p>
                </div>

                <div class="cart-acoes">
                    <button class="btn btn--ghost" type="submit" name="acao" value="atualizar">Atualizar carrinho</button>
                    <button class="btn btn--ghost" type="submit" name="acao" value="limpar" formnovalidate>Limpar carrinho</button>
                    <button class="btn btn--solid" type="submit" name="acao" value="finalizar">Finalizar compra</button>
                </div>
            </form>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
