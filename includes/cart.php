<?php

function carrinho_iniciar()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }

    if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
        $_SESSION['carrinho'] = [];
    }
}

function carrinho_itens()
{
    carrinho_iniciar();

    return $_SESSION['carrinho'];
}

function carrinho_contagem()
{
    carrinho_iniciar();

    $total = 0;

    foreach ($_SESSION['carrinho'] as $item) {
        $total += (int) ($item['quantidade'] ?? 0);
    }

    return $total;
}

function carrinho_total($itens = null)
{
    if ($itens === null) {
        $itens = carrinho_itens();
    }

    $total = 0.0;

    foreach ($itens as $item) {
        $total += (float) ($item['preco'] ?? 0) * (int) ($item['quantidade'] ?? 0);
    }

    return $total;
}

function carrinho_adicionar($conexao, $id, $quantidade = 1)
{
    if (!$conexao instanceof PDO) {
        return ['ok' => false, 'erro' => 'Sem conexão com o banco de dados.'];
    }

    $produto = consultar_produto($conexao, $id);

    if (!$produto) {
        return ['ok' => false, 'erro' => 'Produto não encontrado.'];
    }

    $estoque = (int) $produto['estoque'];

    if ($estoque < 1) {
        return ['ok' => false, 'erro' => 'Produto esgotado.'];
    }

    carrinho_iniciar();

    $quantidade = (int) $quantidade;

    if ($quantidade < 1) {
        $quantidade = 1;
    }

    $atual = (int) ($_SESSION['carrinho'][$id]['quantidade'] ?? 0);

    if ($atual >= $estoque) {
        return ['ok' => false, 'erro' => 'Você já adicionou todo o estoque deste produto ao carrinho.'];
    }

    $novaQuantidade = $atual + $quantidade;
    $aviso = null;

    if ($novaQuantidade > $estoque) {
        $novaQuantidade = $estoque;
        $aviso = 'Quantidade ajustada ao estoque disponível (' . $estoque . ').';
    }

    $adicionadas = $novaQuantidade - $atual;

    $_SESSION['carrinho'][$id] = [
        'id' => (int) $produto['id'],
        'nome' => $produto['nome'],
        'preco' => (float) $produto['preco'],
        'imagem' => $produto['imagem'],
        'estoque' => $estoque,
        'quantidade' => $novaQuantidade,
    ];

    return [
        'ok' => true,
        'produto' => $produto,
        'quantidade' => $adicionadas,
        'aviso' => $aviso,
    ];
}

function carrinho_alterar($conexao, $id, $quantidade)
{
    if (!$conexao instanceof PDO) {
        return ['ok' => false, 'erro' => 'Sem conexão com o banco de dados.'];
    }

    carrinho_iniciar();

    $id = (int) $id;

    if (!isset($_SESSION['carrinho'][$id])) {
        return ['ok' => false, 'erro' => 'Item não está no carrinho.'];
    }

    $produto = consultar_produto($conexao, $id);

    if (!$produto) {
        unset($_SESSION['carrinho'][$id]);

        return ['ok' => false, 'erro' => 'Produto não está mais disponível e foi removido do carrinho.'];
    }

    $estoque = (int) $produto['estoque'];

    if ($estoque < 1) {
        unset($_SESSION['carrinho'][$id]);

        return ['ok' => false, 'erro' => 'Produto esgotado e removido do carrinho.'];
    }

    $quantidade = (int) $quantidade;

    if ($quantidade < 1) {
        unset($_SESSION['carrinho'][$id]);

        return ['ok' => true, 'removido' => true];
    }

    $aviso = null;

    if ($quantidade > $estoque) {
        $quantidade = $estoque;
        $aviso = 'Quantidade ajustada ao estoque disponível (' . $estoque . ').';
    }

    $_SESSION['carrinho'][$id] = [
        'id' => (int) $produto['id'],
        'nome' => $produto['nome'],
        'preco' => (float) $produto['preco'],
        'imagem' => $produto['imagem'],
        'estoque' => $estoque,
        'quantidade' => $quantidade,
    ];

    return ['ok' => true, 'quantidade' => $quantidade, 'aviso' => $aviso];
}

function carrinho_remover($id)
{
    carrinho_iniciar();

    unset($_SESSION['carrinho'][(int) $id]);
}

function carrinho_limpar()
{
    carrinho_iniciar();

    $_SESSION['carrinho'] = [];
}

/**
 * Recarrega nome, preço, imagem e estoque de cada item direto do banco.
 * Itens que não existem mais (ou estão esgotados) saem do carrinho.
 */
function carrinho_sincronizar($conexao)
{
    carrinho_iniciar();

    if (!$conexao instanceof PDO) {
        return $_SESSION['carrinho'];
    }

    foreach ($_SESSION['carrinho'] as $id => $item) {
        $produto = consultar_produto($conexao, (int) $id);

        if (!$produto || (int) $produto['estoque'] < 1) {
            unset($_SESSION['carrinho'][$id]);
            continue;
        }

        $quantidade = (int) ($item['quantidade'] ?? 1);
        $quantidade = max(1, min($quantidade, (int) $produto['estoque']));

        $_SESSION['carrinho'][$id] = [
            'id' => (int) $produto['id'],
            'nome' => $produto['nome'],
            'preco' => (float) $produto['preco'],
            'imagem' => $produto['imagem'],
            'estoque' => (int) $produto['estoque'],
            'quantidade' => $quantidade,
        ];
    }

    return $_SESSION['carrinho'];
}

function carrinho_finalizar($conexao)
{
    if (!$conexao instanceof PDO) {
        return ['ok' => false, 'erro' => 'Sem conexão com o banco de dados.'];
    }

    $itens = carrinho_sincronizar($conexao);

    if (empty($itens)) {
        return ['ok' => false, 'erro' => 'Seu carrinho está vazio.'];
    }

    try {
        $conexao->beginTransaction();

        $comprados = [];
        $total = 0.0;

        foreach ($itens as $id => $item) {
            $stmt = $conexao->prepare(
                "SELECT id, nome, preco, estoque, imagem FROM produtos WHERE id = :id FOR UPDATE"
            );
            $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
            $stmt->execute();
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                throw new RuntimeException('Um dos produtos não está mais disponível.');
            }

            $quantidade = (int) $item['quantidade'];

            if ((int) $produto['estoque'] < $quantidade) {
                throw new RuntimeException('Estoque insuficiente para "' . $produto['nome'] . '".');
            }

            $update = $conexao->prepare(
                "UPDATE produtos SET estoque = estoque - :quantidade WHERE id = :id AND estoque >= :quantidade"
            );
            $update->bindValue(':id', (int) $id, PDO::PARAM_INT);
            $update->bindValue(':quantidade', $quantidade, PDO::PARAM_INT);
            $update->execute();

            if ($update->rowCount() === 0) {
                throw new RuntimeException('Não foi possível finalizar a compra de "' . $produto['nome'] . '".');
            }

            $subtotal = (float) $produto['preco'] * $quantidade;
            $total += $subtotal;

            $comprados[] = [
                'nome' => $produto['nome'],
                'preco' => (float) $produto['preco'],
                'quantidade' => $quantidade,
                'subtotal' => $subtotal,
            ];
        }

        $conexao->commit();
    } catch (Throwable $e) {
        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }

        return ['ok' => false, 'erro' => $e->getMessage()];
    }

    carrinho_limpar();

    $pedido = [
        'itens' => $comprados,
        'quantidade' => array_sum(array_column($comprados, 'quantidade')),
        'total' => $total,
        'data' => date('d/m/Y H:i'),
    ];

    $_SESSION['ultimo_pedido'] = $pedido;

    return ['ok' => true, 'pedido' => $pedido];
}

function get_ultimo_pedido()
{
    carrinho_iniciar();

    if (!isset($_SESSION['ultimo_pedido'])) {
        return null;
    }

    $pedido = $_SESSION['ultimo_pedido'];
    unset($_SESSION['ultimo_pedido']);

    return $pedido;
}
