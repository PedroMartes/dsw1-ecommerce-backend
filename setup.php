<?php
require_once 'db.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS usuarios (
        id SERIAL PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        nivel VARCHAR(20) DEFAULT 'cliente'
    );
    -- Inserir o Admin inicial (senha: 123456)
    INSERT INTO usuarios (nome, email, senha, nivel) 
    VALUES ('Admin Zetta', 'admin@zetta.com', '$2y$10$8K9pX6mN6mX9pX6mN6mX9OQvIeG3M3M3M3M3M3M3M3M3M3M3', 'admin')
    ON CONFLICT (email) DO NOTHING;
    ";

    $pdo->exec($sql);
    echo "<h1>🚀 Banco de Dados configurado com sucesso!</h1>";
} catch (Exception $e) {
    echo "<h1>❌ Erro: " . $e->getMessage() . "</h1>";
}