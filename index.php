<?php
header('Content-Type: application/json');

$host = 'dpg-d7ejql19rddc73e96t30-a.oregon-postgres.render.com';
$db   = 'zetta_ogvi';
$user = 'zetta_ogvi_user';
$pass = 'Cg1goqOOsJPkDSksSxJ5ncIcvVNzp6oD';

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$db;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // SQL para criar tudo de uma vez
    $sql = "
    CREATE TABLE IF NOT EXISTS usuarios (
        id SERIAL PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        nivel VARCHAR(20) DEFAULT 'cliente',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS produtos (
        id SERIAL PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        preco DECIMAL(10,2) NOT NULL,
        imagem_url TEXT,
        categoria VARCHAR(50)
    );
    ";

    $pdo->exec($sql);
    
    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Conectado ao Postgres e tabelas criadas/verificadas!"
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => $e->getMessage()
    ]);
}