<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../login/verifica_user.php';

$produtos = listar_produtos($conexao);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/clubhawkings/style/style.css">
    <title>Produtos</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php';?>
    <main>
        <h1>Produtos</h1>

        <?php if ($flash): ?>
            <p class="alert alert--<?= $flash['tipo'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['mensagem']) ?>
            </p>
        <?php endif; ?>

        <?php if (empty($produtos)): ?>
            <p class="text-center">Nenhum produto cadastrado ainda.</p>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($produtos as $produto): ?>
                    <article class="card">
                        <img class="produto-img"
                             src="/clubhawkings/uploads/<?= htmlspecialchars($produto['imagem']) ?>"
                             alt="<?= htmlspecialchars($produto['nome']) ?>">
                        <h3><?= htmlspecialchars($produto['nome']) ?></h3>
                        <p>Preço: R$ <?= number_format((float) ($produto['preco'] ?? 0), 2, ',', '.') ?></p>
                        <p>Estoque: <?= (int) $produto['estoque'] ?></p>
                        <?php if ((int) $produto['estoque'] > 0): ?>
                            <form action="/clubhawkings/app/comprar.php" method="POST">
                                <input type="hidden" name="id" value="<?= (int) $produto['id'] ?>">
                                <input type="hidden" name="voltar" value="produtos">
                                <input class="qtd-input"
                                       type="number"
                                       name="quantidade"
                                       value="1"
                                       min="1"
                                       max="<?= (int) $produto['estoque'] ?>"
                                       step="1"
                                       inputmode="numeric"
                                       aria-label="Quantidade de <?= htmlspecialchars($produto['nome']) ?>">
                                <button type="submit">Adicionar ao carrinho</button>
                            </form>
                        <?php else: ?>
                            <p class="alert alert--error">Esgotado</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/../includes/footer.php';?>
</body>
</html>
