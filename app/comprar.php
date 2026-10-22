<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../login/verifica_user.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/clubhawkings/app/produtos.php');
}

$id = (int) ($_POST['id'] ?? 0);
$quantidade = (int) ($_POST['quantidade'] ?? 1);
$voltar = ($_POST['voltar'] ?? '') === 'carrinho'
    ? '/clubhawkings/app/carrinho.php'
    : '/clubhawkings/app/produtos.php';

if ($id <= 0) {
    set_flash('error', 'Produto inválido.');
    redirecionar($voltar);
}

$resultado = carrinho_adicionar($conexao, $id, $quantidade);

if (!$resultado['ok']) {
    set_flash('error', $resultado['erro']);
} else {
    $nome = $resultado['produto']['nome'] ?? 'Produto';
    $mensagem = "Adicionado ao carrinho: {$resultado['quantidade']} x $nome.";

    if (!empty($resultado['aviso'])) {
        $mensagem .= ' ' . $resultado['aviso'];
    }

    set_flash('success', $mensagem);
}

redirecionar($voltar);
