<?php
require_once '../db.php';
header('Content-Type: application/json');

// Recebe os dados do formulário (JSON)
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados['email'] || !$dados['senha']) {
    echo json_encode(["erro" => "Preencha todos os campos"]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, nome, email, senha, nivel FROM usuarios WHERE email = ?");
$stmt->execute([$dados['email']]);
$user = $stmt->fetch();

// Verifica se usuário existe e se a senha (hash) bate
if ($user && password_verify($dados['senha'], $user['senha'])) {
    echo json_encode([
        "status" => "sucesso",
        "usuario" => [
            "id" => $user['id'],
            "nome" => $user['nome'],
            "nivel" => $user['nivel'] // Retorna 'admin' ou 'cliente'
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(["status" => "erro", "mensagem" => "E-mail ou senha incorretos."]);
}