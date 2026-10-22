<?php
require_once __DIR__ . '/../database/connect.php';
require_once __DIR__ . '/cart.php';

function set_flash($tipo, $mensagem)
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function get_flash()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function redirecionar($caminho)
{
    header('Location: ' . $caminho);
    exit;
}

function excluir_produto($conexao, $id)
{
    $produto = consultar_produto($conexao, $id);

    if (!$produto) {
        return ['ok' => false, 'erro' => 'Produto não encontrado.'];
    }

    $stmt = $conexao->prepare("DELETE FROM produtos WHERE id = :id");
    $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'erro' => 'Não foi possível excluir o produto.'];
    }

    $imagem = __DIR__ . '/../uploads/' . $produto['imagem'];

    if ($produto['imagem'] !== '' && is_file($imagem)) {
        @unlink($imagem);
    }

    return ['ok' => true, 'produto' => $produto];
}

function cadastrar_produto($conexao, $nome, $estoque, $preco, $imagem)
{
    $sql = "INSERT INTO produtos(nome, estoque, preco, imagem) VALUES (:nome, :estoque, :preco, :imagem)";

    $stmt = $conexao->prepare($sql);
    $stmt->bindValue(':nome', trim($nome), PDO::PARAM_STR);
    $stmt->bindValue(':estoque', (int) $estoque, PDO::PARAM_INT);
    $stmt->bindValue(':preco', (float) $preco, PDO::PARAM_STR);
    $stmt->bindValue(':imagem', trim($imagem), PDO::PARAM_STR);
    $stmt->execute();

    return $conexao->lastInsertId();
}

function atualizar_produto($conexao, $id, $nome, $estoque, $preco = null)
{
    if ($preco === null) {
        $sql = "UPDATE produtos SET nome = :nome, estoque = :estoque WHERE id = :id";
        $stmt = $conexao->prepare($sql);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->bindValue(':nome', trim($nome), PDO::PARAM_STR);
        $stmt->bindValue(':estoque', (int) $estoque, PDO::PARAM_INT);
    } else {
        $sql = "UPDATE produtos SET nome = :nome, estoque = :estoque, preco = :preco WHERE id = :id";
        $stmt = $conexao->prepare($sql);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->bindValue(':nome', trim($nome), PDO::PARAM_STR);
        $stmt->bindValue(':estoque', (int) $estoque, PDO::PARAM_INT);
        $stmt->bindValue(':preco', (float) $preco, PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->rowCount();
}

function redimensionar_imagem($caminho, $maxLado = 800)
{
    if (!function_exists('imagecreatefromstring')) {
        return;
    }

    $dados = @file_get_contents($caminho);

    if ($dados === false) {
        return;
    }

    $origem = @imagecreatefromstring($dados);

    if ($origem === false) {
        return;
    }

    $largura = imagesx($origem);
    $altura = imagesy($origem);

    if ($largura <= $maxLado && $altura <= $maxLado) {
        return;
    }

    $escala = min($maxLado / $largura, $maxLado / $altura);
    $novaLargura = max(1, (int) round($largura * $escala));
    $novaAltura = max(1, (int) round($altura * $escala));

    $destino = imagecreatetruecolor($novaLargura, $novaAltura);
    $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

    if (in_array($extensao, ['png', 'gif', 'webp'], true)) {
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        $transparente = imagecolorallocatealpha($destino, 0, 0, 0, 127);
        imagefilledrectangle($destino, 0, 0, $novaLargura, $novaAltura, $transparente);
    }

    imagecopyresampled($destino, $origem, 0, 0, 0, 0, $novaLargura, $novaAltura, $largura, $altura);

    switch ($extensao) {
        case 'png':
            imagepng($destino, $caminho, 6);
            break;
        case 'gif':
            imagegif($destino, $caminho);
            break;
        case 'webp':
            if (function_exists('imagewebp')) {
                imagewebp($destino, $caminho, 85);
            }
            break;
        default:
            imagejpeg($destino, $caminho, 85);
    }
}

function listar_produtos($conexao)
{
    $sql = "SELECT id, nome, estoque, preco, imagem FROM produtos ORDER BY id DESC";

    $stmt = $conexao->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function consultar_produto($conexao, $id)
{
    $sql = "SELECT id, nome, estoque, preco, imagem FROM produtos WHERE id = :id";

    $stmt = $conexao->prepare($sql);
    $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function comprar_produto($conexao, $id, $quantidade = 1)
{
    $produto = consultar_produto($conexao, $id);
    if (!$produto) {
        return ['ok' => false, 'erro' => 'Produto não encontrado.'];
    }
    $quantidade = (int) $quantidade;
    if ($quantidade <= 0) {
        $quantidade = 1;
    }
    if ($produto['estoque'] < $quantidade) {
        return ['ok' => false, 'erro' => 'Estoque insuficiente.'];
    }
    $sql = "UPDATE produtos SET estoque = estoque - :qtd WHERE id = :id AND estoque >= :qtd";
    $stmt = $conexao->prepare($sql);
    $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
    $stmt->bindValue(':qtd', $quantidade, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'erro' => 'Não foi possível realizar a compra.'];
    }
    return ['ok' => true, 'produto' => $produto, 'quantidade' => $quantidade];
}

function cadastrar_user($conexao, $email, $senha)
{
    $sql = "INSERT INTO usuarios (email, senha) VALUES (:email, :senha)";

    $stmt = $conexao->prepare($sql);
    $stmt->bindValue(':email', trim($email), PDO::PARAM_STR);
    $stmt->bindValue(':senha', password_hash($senha, PASSWORD_DEFAULT), PDO::PARAM_STR);

    $stmt->execute();
}

function consultar_user($conexao, $email)
{
    $sql = "SELECT id, email, senha FROM usuarios WHERE email = :email";

    try {
        $stmt = $conexao->prepare($sql);
        $stmt->bindValue(':email', trim($email), PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}