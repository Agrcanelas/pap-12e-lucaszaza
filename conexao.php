<?php
$servidor = "localhost";
$usuario = "root";
$senha = "lucascaio11";
$dbname = "site_pap";

try {
    $pdo = new PDO("mysql:host=$servidor;dbname=$dbname", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Falha na conexão: " . $e->getMessage());
}
?>
