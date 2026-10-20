<?php 
$host = '192.168.10.36';
$dbname = "clubhawkings";
$user = "clubehawkings";
$pass = "clubhawkings";
try {
    $conexao = new PDO(
        "pgsql:host=$host;dbname=$dbname",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    $conexao = null;
        echo "Erro de conexao: " . $e->getMessage();
}
