<?php
/**
 * API для управління каталогом музики та плейлистами (Варіант 14)
 */

// Встановлюємо заголовки відповіді у форматі JSON із кодуванням UTF-8[cite: 7, 8]
header('Content-Type: application/json; charset=utf-8');

// Підключення до бази даних через PDO (коригуйте налаштування під себе)
$host = 'localhost';
$db   = 'music_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Помилка підключення до БД: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;
$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

// Перевірка коректності ресурсу[cite: 8]
if ($resource !== 'tracks') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Невідомий ресурс або метод']);
    exit;
}

try {
    if ($method === 'GET') {
        if ($id === null) {
            // Крок 4: Отримання всього списку треків[cite: 9]
            $stmt = $pdo->query("SELECT * FROM tracks");
            $tracks = $stmt->fetchAll();
            
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $tracks]);
        } else {
            // Крок 4: Отримання одного запису за ID або 404[cite: 9]
            $stmt = $pdo->prepare("SELECT * FROM tracks WHERE id = ?");
            $stmt->execute([$id]);
            $track = $stmt->fetch();
            
            if ($track) {
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $track]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Трек з таким ID не знайдено']);
            }
        }
    } 
    elseif ($method === 'POST') {
        // Отримуємо вхідні дані (підтримка JSON та стандартного x-www-form-urlencoded)[cite: 9]
        $inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if ($action === 'stats') {
            // Крок 6: Доменна дія — повернути сумарну тривалість треків[cite: 10, 11]
            $stmt = $pdo->query("SELECT SUM(duration_sec) as total_duration_sec, COUNT(*) as total_tracks FROM tracks");
            $stats = $stmt->fetch();
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_duration_sec' => (int)($stats['total_duration_sec'] ?? 0),
                    'total_tracks' => (int)$stats['total_tracks']
                ]
            ]);
        } 
        elseif ($action === null) {
            // Крок 5: Створення нового запису
            $title = trim($inputData['title'] ?? '');
            $artist = trim($inputData['artist'] ?? '');
            $duration_sec = $inputData['duration_sec'] ?? null;
            
            // Перевірка обов'язкових полів (400 Bad Request, якщо чогось бракує)[cite: 10]
            if ($title === '' || $artist === '' || $duration_sec === null || !is_numeric($duration_sec)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Помилка валідації: поля title, artist та числове duration_sec є обов’язковими'
                ]);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO tracks (title, artist, duration_sec) VALUES (?, ?, ?)");
            $stmt->execute([$title, $artist, (int)$duration_sec]);
            $newId = $pdo->lastInsertId();
            
            // Повертаємо створений запис із кодом 201 Created[cite: 10]
            $stmt = $pdo->prepare("SELECT * FROM tracks WHERE id = ?");
            $stmt->execute([$newId]);
            $newTrack = $stmt->fetch();
            
            http_response_code(201);
            echo json_encode(['success' => true, 'data' => $newTrack]);
        } 
        else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Невідома дія (action)']);
        }
    } 
    else {
        // Крок 7: Метод не підтримується для цього ресурсу (405 Method Not Allowed)[cite: 10]
        http_response_code(405);
        header('Allow: GET, POST');
        echo json_encode(['success' => false, 'error' => 'HTTP-метод не підтримується']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Помилка виконання запиту: ' . $e->getMessage()]);
}