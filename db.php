<?php
// Configurações extraídas da sua URL do Render
$host = 'dpg-d7ejql19rddc73e96t30-a.oregon-postgres.render.com';
$db   = 'zetta_ogvi';
$user = 'zetta_ogvi_user';
$pass = 'Cg1goqOOsJPkDSksSxJ5ncIcvVNzp6oD';
$port = '5432';

try {
    // DSN para PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    
    // Criar a conexão PDO
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Opcional: Remover em produção, apenas para teste inicial
    // echo "Conectado com sucesso à Zetta DB!"; 

} catch (PDOException $e) {
    // Retorna erro em formato JSON caso a conexão falhe
    header('Content-Type: application/json');
    die(json_encode(["status" => "erro", "mensagem" => "Falha na conexão: " . $e->getMessage()]));
}