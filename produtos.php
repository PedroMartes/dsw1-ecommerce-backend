<?php
require_once 'db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Lista todos os produtos para a vitrine
        $stmt = $pdo->query("SELECT * FROM produtos ORDER BY id DESC");
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        // Apenas admin deveria fazer isto (podes validar o nível depois)
        $dados = json_decode(file_get_contents('php://input'), true);
        
        $sql = "INSERT INTO produtos (nome, preco, imagem_url, categoria) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if($stmt->execute([$dados['nome'], $dados['preco'], $dados['imagem_url'], $dados['categoria']])) {
            echo json_encode(["status" => "sucesso", "id" => $pdo->lastInsertId()]);
        }
        break;
}