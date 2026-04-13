<?php
// Detecta a origem da requisição
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Lista de URLs permitidas (Seu localhost e seu futuro link do Render)
$allowed_origins = [
    'http://localhost:3000',
    'https://seu-front-zetta.onrender.com' // Substitua pela sua URL real do Render quando tiver
];

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Access-Control-Allow-Credentials: true'); // OBRIGATÓRIO para cookies
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Responde imediatamente a requisições OPTIONS (Preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database configuration
$database_url = getenv('DATABASE_URL') ?: 'postgresql://zetta_user:Duh1u4SENDM9BzdHUX6KAiHgQfWf8jvE@dpg-d7d7g87aqgkc73b65r80-a.oregon-postgres.render.com/zetta';
$parsed_url = parse_url($database_url);
$host = $parsed_url['host'];
$port = $parsed_url['port'] ?? 5432;
$dbname = ltrim($parsed_url['path'], '/');
$user = $parsed_url['user'];
$pass = $parsed_url['pass'];

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// JWT Secret
$jwt_secret = getenv('JWT_SECRET') ?: 'your-secret-key';

// Simple JWT functions
function base64UrlEncode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

function base64UrlDecode($data) {
    return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
}

function generateJWT($payload) {
    global $jwt_secret;
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);
    $headerEncoded = base64UrlEncode($header);
    $payloadEncoded = base64UrlEncode($payload);
    $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $jwt_secret, true);
    $signatureEncoded = base64UrlEncode($signature);
    return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
}

function verifyJWT($jwt) {
    global $jwt_secret;
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return false;
    $header = $parts[0];
    $payload = $parts[1];
    $signature = $parts[2];
    $expectedSignature = base64UrlEncode(hash_hmac('sha256', $header . "." . $payload, $jwt_secret, true));
    if ($signature !== $expectedSignature) return false;
    $payloadDecoded = json_decode(base64UrlDecode($payload), true);
    if ($payloadDecoded['exp'] < time()) return false;
    return $payloadDecoded;
}

function getUserFromToken() {
    $token = $_COOKIE['zetta_token'] ?? null;
    if (!$token) return null;
    return verifyJWT($token);
}

// Get request body
function getRequestBody() {
    return json_decode(file_get_contents('php://input'), true);
}

// Routing
$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove o "index.php" ou "api.php" caso eles apareçam na URL para evitar erro 404
$path = str_replace(['/index.php', '/api.php'], '', $path);

// Se o path ficar vazio, define como raiz
if (empty($path)) {
    $path = '/';
}

switch ($path) {
    case '/':
        echo json_encode(['status' => 'API Zetta Online', 'db' => 'PostgreSQL Conectado']);
        break;
    case '/auth/login':
        if ($method === 'POST') {
            handleLogin();
        }
        break;
    case '/auth/logout':
        if ($method === 'POST') {
            handleLogout();
        }
        break;
    case '/auth/register':
        if ($method === 'POST') {
            handleRegister();
        }
        break;
    case '/cart':
        if ($method === 'GET') {
            handleGetCart();
        } elseif ($method === 'POST') {
            handleAddToCart();
        } elseif ($method === 'DELETE') {
            handleRemoveFromCart();
        } elseif ($method === 'PATCH') {
            handleUpdateCart();
        }
        break;
    case '/checkout':
        if ($method === 'POST') {
            handleCheckout();
        }
        break;
    case '/products':
        if ($method === 'GET') {
            handleGetProducts();
        } elseif ($method === 'POST') {
            handleCreateProduct();
        } elseif ($method === 'PUT') {
            handleUpdateProduct();
        } elseif ($method === 'DELETE') {
            handleDeleteProduct();
        }
        break;
    case '/user/profile':
        if ($method === 'GET') {
            handleGetProfile();
        } elseif ($method === 'PUT') {
            handleUpdateProfile();
        }
        break;
    case '/':
        if ($method === 'GET') {
            echo json_encode(['message' => 'API is running']);
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

function handleLogin() {
    global $pdo;
    $data = getRequestBody();
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciais inválidas']);
        return;
    }

    $token = generateJWT([
        'id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'exp' => time() + (3 * 24 * 60 * 60) // 3 days
    ]);

    setcookie('zetta_token', $token, [
        'httponly' => true,
        'secure' => false, // Set to true in production with HTTPS
        'path' => '/',
        'maxage' => 3 * 24 * 60 * 60
    ]);

    echo json_encode([
        'message' => 'Login realizado!',
        'user' => ['name' => $user['name'], 'role' => $user['role']]
    ]);
}

function handleLogout() {
    setcookie('zetta_token', '', [
        'httponly' => true,
        'expires' => time() - 3600,
        'path' => '/'
    ]);
    echo json_encode(['message' => 'Logout realizado com sucesso!']);
}

function handleRegister() {
    global $pdo;
    $data = getRequestBody();
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $street = $data['street'] ?? '';
    $number = $data['number'] ?? '';
    $complement = $data['complement'] ?? null;
    $city = $data['city'] ?? '';
    $state = $data['state'] ?? '';

    if (!$name || !$email || !$password || !$street || !$number || !$city || !$state) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados insuficientes']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'E-mail já cadastrado']);
        return;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $userId = uniqid();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password, role) VALUES (?, ?, ?, ?, 'CLIENT')");
        $stmt->execute([$userId, $name, $email, $hashedPassword]);

        $stmt = $pdo->prepare("INSERT INTO addresses (id, street, number, complement, city, state, userId) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([uniqid(), $street, $number, $complement, $city, $state, $userId]);

        $pdo->commit();
        http_response_code(201);
        echo json_encode(['message' => 'Usuário criado!', 'userId' => $userId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno no servidor']);
    }
}

function handleGetCart() {
    global $pdo;
    $user = getUserFromToken();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuário não autenticado']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT ci.id, ci.quantity, p.id as product_id, p.name, p.description, p.image, p.price, p.stock
        FROM cart_items ci
        JOIN products p ON ci.productId = p.id
        WHERE ci.userId = ?
    ");
    $stmt->execute([$user['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($items);
}

function handleAddToCart() {
    global $pdo;
    $user = getUserFromToken();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuário não autenticado']);
        return;
    }

    $data = getRequestBody();
    $productId = $data['productId'] ?? '';
    $quantity = $data['quantity'] ?? 1;

    if (!$productId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do produto é obrigatório']);
        return;
    }

    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product || $product['stock'] <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Produto indisponível']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE productId = ? AND userId = ?");
    $stmt->execute([$productId, $user['id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $newQuantity = $existing['quantity'] + $quantity;
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
        $stmt->execute([$newQuantity, $existing['id']]);
        echo json_encode(['id' => $existing['id'], 'quantity' => $newQuantity]);
    } else {
        $id = uniqid();
        $stmt = $pdo->prepare("INSERT INTO cart_items (id, productId, quantity, userId) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id, $productId, $quantity, $user['id']]);
        echo json_encode(['id' => $id, 'productId' => $productId, 'quantity' => $quantity, 'userId' => $user['id']]);
    }
}

function handleRemoveFromCart() {
    global $pdo;
    $user = getUserFromToken();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuário não autenticado']);
        return;
    }

    $data = getRequestBody();
    $id = $data['id'] ?? '';

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do item é obrigatório']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM cart_items WHERE id = ? AND userId = ?");
    $stmt->execute([$id, $user['id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Item não encontrado ou não pertence ao usuário']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['message' => 'Produto removido com sucesso']);
}

function handleUpdateCart() {
    global $pdo;
    $user = getUserFromToken();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuário não autenticado']);
        return;
    }

    $data = getRequestBody();
    $id = $data['id'] ?? '';
    $quantity = $data['quantity'] ?? 0;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do item é obrigatório']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM cart_items WHERE id = ? AND userId = ?");
    $stmt->execute([$id, $user['id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Item não encontrado ou não pertence ao usuário']);
        return;
    }

    if ($quantity <= 0) {
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Removido por quantidade zero']);
    } else {
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
        $stmt->execute([$quantity, $id]);
        echo json_encode(['id' => $id, 'quantity' => $quantity]);
    }
}

function handleCheckout() {
    global $pdo;
    $user = getUserFromToken();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT ci.productId, ci.quantity, p.price, p.stock, p.name
        FROM cart_items ci
        JOIN products p ON ci.productId = p.id
        WHERE ci.userId = ?
    ");
    $stmt->execute([$user['id']]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cartItems)) {
        http_response_code(400);
        echo json_encode(['error' => 'Carrinho vazio']);
        return;
    }

    $total = 0;
    foreach ($cartItems as $item) {
        if ($item['stock'] < $item['quantity']) {
            http_response_code(400);
            echo json_encode(['error' => 'Estoque insuficiente para ' . $item['name']]);
            return;
        }
        $total += $item['price'] * $item['quantity'];
    }

    $pdo->beginTransaction();
    try {
        $orderId = uniqid();
        $stmt = $pdo->prepare("INSERT INTO orders (id, total, status) VALUES (?, ?, 'Finalizado')");
        $stmt->execute([$orderId, $total]);

        foreach ($cartItems as $item) {
            $stmt = $pdo->prepare("INSERT INTO order_items (id, orderId, productId, quantity, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([uniqid(), $orderId, $item['productId'], $item['quantity'], $item['price']]);

            $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['productId']]);
        }

        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE userId = ?");
        $stmt->execute([$user['id']]);

        $pdo->commit();
        echo json_encode(['id' => $orderId, 'total' => $total, 'status' => 'Finalizado']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleGetProducts() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM products ORDER BY name ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($products);
}

function handleCreateProduct() {
    global $pdo;
    $data = getRequestBody();
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $image = $data['image'] ?? '';
    $price = (float)($data['price'] ?? 0);
    $stock = (int)($data['stock'] ?? 0);

    $id = uniqid();
    $stmt = $pdo->prepare("INSERT INTO products (id, name, description, image, price, stock) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id, $name, $description, $image, $price, $stock]);
    http_response_code(201);
    echo json_encode(['id' => $id, 'name' => $name, 'description' => $description, 'image' => $image, 'price' => $price, 'stock' => $stock]);
}

function handleUpdateProduct() {
    global $pdo;
    $data = getRequestBody();
    $id = $data['id'] ?? '';
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $image = $data['image'] ?? '';
    $price = (float)($data['price'] ?? 0);
    $stock = (int)($data['stock'] ?? 0);

    $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, image = ?, price = ?, stock = ? WHERE id = ?");
    $stmt->execute([$name, $description, $image, $price, $stock, $id]);
    echo json_encode(['id' => $id, 'name' => $name, 'description' => $description, 'image' => $image, 'price' => $price, 'stock' => $stock]);
}

function handleDeleteProduct() {
    global $pdo;
    $id = $_GET['id'] ?? '';
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID não fornecido']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['message' => 'Produto removido']);
}

function handleGetProfile() {
    global $pdo;
    $user = getUserFromToken();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado']);
        return;
    }

    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT street, number, complement, city, state FROM addresses WHERE userId = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'name' => $userData['name'],
        'email' => $userData['email'],
        'addresses' => [$address]
    ]);
}

function handleUpdateProfile() {
    global $pdo;
    $user = getUserFromToken();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado']);
        return;
    }

    $data = getRequestBody();
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $street = $data['street'] ?? '';
    $number = $data['number'] ?? '';
    $complement = $data['complement'] ?? null;
    $city = $data['city'] ?? '';
    $state = $data['state'] ?? '';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $user['id']]);

        $stmt = $pdo->prepare("SELECT id FROM addresses WHERE userId = ?");
        $stmt->execute([$user['id']]);
        $addr = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($addr) {
            $stmt = $pdo->prepare("UPDATE addresses SET street = ?, number = ?, complement = ?, city = ?, state = ? WHERE id = ?");
            $stmt->execute([$street, $number, $complement, $city, $state, $addr['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO addresses (id, street, number, complement, city, state, userId) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([uniqid(), $street, $number, $complement, $city, $state, $user['id']]);
        }

        $pdo->commit();
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao salvar']);
    }
}
?></content>
<parameter name="filePath">c:\Users\Pedro\Desktop\Fatec\Desenvolvimento Web 1\dsw1-ecommerce\zetta\api.php