<?php
require_once __DIR__ . '/cart.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logado = isset($_SESSION['id']);
$carrinhoQuantidade = carrinho_contagem();
?>
<header>
    <nav>
        <div>
            <a href="/clubhawkings/index.php">Inicio</a>
            <a href="/clubhawkings/app/create.php">Cadastrar</a>
            <a href="/clubhawkings/app/produtos.php">Produtos</a>
            <a href="/clubhawkings/app/delete.php">Excluir</a>
            <a href="/clubhawkings/app/update.php">Atualizar</a>
            <a class="nav-cart" href="/clubhawkings/app/carrinho.php">
                Carrinho
                <span class="nav-cart__count"><?= (int) $carrinhoQuantidade ?></span>
            </a>
        </div>
        <?php if ($logado): ?>
            <a class="botao-sair" href="/clubhawkings/login/logout.php">Sair</a>
        <?php else: ?>
            <a class="botao-sair" href="/clubhawkings/login/login.php">Entrar</a>
        <?php endif; ?>
    </nav>
</header>
