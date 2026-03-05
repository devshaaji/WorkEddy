<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use FastRoute\Dispatcher;
use WorkEddy\Api\Config\Database;
use WorkEddy\Api\Config\Logger;
use WorkEddy\Api\Services\AuthService;
use WorkEddy\Api\Services\BillingService;
use WorkEddy\Api\Services\DashboardService;
use WorkEddy\Api\Services\JwtService;
use WorkEddy\Api\Services\ObserverService;
use WorkEddy\Api\Services\QueueService;
use WorkEddy\Api\Services\RiskScoringService;
use WorkEddy\Api\Services\ScanService;
use WorkEddy\Api\Services\TaskService;
use WorkEddy\Api\Services\UserService;

header('Content-Type: application/json');

function jsonResponse(array $payload, int $status = 200): void { http_response_code($status); echo json_encode($payload); exit; }
function requestJson(): array { $in=file_get_contents('php://input'); $d=json_decode($in?:'[]', true); return is_array($d)?$d:[]; }
function bearerToken(): ?string { $h=getallheaders(); $a=$h['Authorization']??$h['authorization']??''; return str_starts_with($a,'Bearer ')?trim(substr($a,7)):null; }
function requireClaims(JwtService $jwt): array { $t=bearerToken(); if(!$t){jsonResponse(['error'=>'Unauthorized'],401);} return $jwt->parseToken($t); }
function requireRoles(array $claims, array $roles): void { if(!in_array($claims['role']??'', $roles, true)){jsonResponse(['error'=>'Forbidden'],403);} }

$logger = Logger::make();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$jwt = new JwtService();

try {
    $db = Database::connection();
    $billing = new BillingService($db);
    $auth = new AuthService($db, $jwt, $billing);
    $users = new UserService($db);
    $tasks = new TaskService($db);
    $scans = new ScanService($db, new RiskScoringService(), new QueueService(), $billing);
    $dashboard = new DashboardService($db);
    $observer = new ObserverService($db);

    if (!function_exists('FastRoute\\simpleDispatcher')) {
        throw new RuntimeException('FastRoute is required. Run composer install in api/.');
    }

    $dispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r): void {
        $r->addRoute('GET', '/health', 'health');
        $r->addRoute('POST', '/auth/signup', 'auth.signup');
        $r->addRoute('POST', '/auth/login', 'auth.login');
        $r->addRoute('GET', '/auth/me', 'auth.me');
        $r->addRoute('GET', '/users', 'users.list');
        $r->addRoute('POST', '/users', 'users.create');
        $r->addRoute('GET', '/tasks', 'tasks.list');
        $r->addRoute('POST', '/tasks', 'tasks.create');
        $r->addRoute('GET', '/tasks/{id:\\d+}', 'tasks.get');
        $r->addRoute('POST', '/scans/manual', 'scans.manual');
        $r->addRoute('POST', '/scans/video', 'scans.video');
        $r->addRoute('GET', '/scans', 'scans.list');
        $r->addRoute('GET', '/scans/{id:\\d+}', 'scans.get');
        $r->addRoute('GET', '/dashboard', 'dashboard.get');
        $r->addRoute('POST', '/observer-rating', 'observer.rate');
        $r->addRoute('GET', '/observer-rating/{scan_id:\\d+}', 'observer.list');
        $r->addRoute('GET', '/billing/usage', 'billing.usage');
        $r->addRoute('GET', '/billing/plans', 'billing.plans');
    });

    $route = $dispatcher->dispatch($method, $path);
    if ($route[0] === Dispatcher::NOT_FOUND) { jsonResponse(['error'=>'Not found','path'=>$path],404); }
    if ($route[0] === Dispatcher::METHOD_NOT_ALLOWED) { jsonResponse(['error'=>'Method not allowed'],405); }

    [, $handler, $vars] = $route;
    $body = requestJson();

    switch ($handler) {
        case 'health': jsonResponse(['status'=>'ok','service'=>'workeddy-api','timestamp'=>gmdate('c')]);
        case 'auth.signup':
            foreach (['name','email','password','organization_name'] as $f) { if (empty($body[$f])) jsonResponse(['error'=>"Missing field: {$f}"],422); }
            jsonResponse($auth->signup($body['name'],$body['email'],$body['password'],$body['organization_name']),201);
        case 'auth.login':
            if (empty($body['email']) || empty($body['password'])) jsonResponse(['error'=>'Missing email or password'],422);
            jsonResponse($auth->login($body['email'],$body['password']));
        case 'auth.me':
            jsonResponse(['user'=>requireClaims($jwt)]);
        case 'users.list':
            $claims=requireClaims($jwt); requireRoles($claims,['admin']); jsonResponse(['data'=>$users->listByOrganization((int)$claims['org'])]);
        case 'users.create':
            $claims=requireClaims($jwt); requireRoles($claims,['admin']);
            foreach (['name','email','password','role'] as $f) { if (empty($body[$f])) jsonResponse(['error'=>"Missing field: {$f}"],422); }
            jsonResponse(['data'=>$users->create((int)$claims['org'],$body['name'],$body['email'],$body['password'],$body['role'])],201);
        case 'tasks.list':
            $claims=requireClaims($jwt); requireRoles($claims,['admin','supervisor','worker','observer']); jsonResponse(['data'=>$tasks->listByOrganization((int)$claims['org'])]);
        case 'tasks.create':
            $claims=requireClaims($jwt); requireRoles($claims,['admin','supervisor']); if(empty($body['name'])) jsonResponse(['error'=>'Missing field: name'],422);
            jsonResponse(['data'=>$tasks->create((int)$claims['org'],$body['name'],$body['description']??null,$body['department']??null)],201);
        case 'tasks.get':
            $claims=requireClaims($jwt); requireRoles($claims,['admin','supervisor','worker','observer']); jsonResponse(['data'=>$tasks->getById((int)$claims['org'],(int)$vars['id'])]);
        case 'scans.manual':
            $claims=requireClaims($jwt); requireRoles($claims,['admin','supervisor','worker']); if(empty($body['task_id'])) jsonResponse(['error'=>'Missing field: task_id'],422);
            $tasks->getById((int)$claims['org'],(int)$body['task_id']);
            jsonResponse(['data'=>$scans->createManualScan((int)$claims['org'],(int)$claims['sub'],(int)$body['task_id'],$body)],201);
        case 'scans.video':
            $claims=requireClaims($jwt); requireRoles($claims,['admin','supervisor','worker']);
            $taskId = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
            if ($taskId <= 0) { jsonResponse(['error' => 'Missing field: task_id'], 422); }
            if (!isset($_FILES['video']) || !is_array($_FILES['video'])) { jsonResponse(['error' => 'Missing video file'], 422); }
            if ((int)($_FILES['video']['error'] ?? 1) !== UPLOAD_ERR_OK) { jsonResponse(['error' => 'Upload failed'], 400); }

            $tasks->getById((int)$claims['org'], $taskId);

            $uploadDir = '/storage/uploads/videos';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                throw new RuntimeException('Could not initialize upload directory');
            }

            $ext = pathinfo((string)$_FILES['video']['name'], PATHINFO_EXTENSION) ?: 'mp4';
            $targetPath = $uploadDir . '/scan_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (!move_uploaded_file((string)$_FILES['video']['tmp_name'], $targetPath)) {
                throw new RuntimeException('Could not persist uploaded file');
            }

            jsonResponse(['data' => $scans->createVideoScan((int)$claims['org'], (int)$claims['sub'], $taskId, $targetPath)], 201);
        case 'scans.list':
            $claims=requireClaims($jwt); requireRoles($claims,['admin','supervisor','worker','observer']); jsonResponse(['data'=>$scans->listByOrganization((int)$claims['org'])]);
        case 'scans.get':
            $claims=requireClaims($jwt); requireRoles($claims,['admin','supervisor','worker','observer']); jsonResponse(['data'=>$scans->getById((int)$claims['org'],(int)$vars['id'])]);
        case 'dashboard.get':
            $claims=requireClaims($jwt); requireRoles($claims,['admin','supervisor']); jsonResponse(['data'=>$dashboard->summary((int)$claims['org'])]);
        case 'observer.rate':
            $claims=requireClaims($jwt); requireRoles($claims,['observer','admin']);
            foreach (['scan_id','observer_score','observer_category'] as $f) { if (!isset($body[$f])) jsonResponse(['error'=>"Missing field: {$f}"],422); }
            jsonResponse(['data'=>$observer->rate((int)$claims['org'], (int)$body['scan_id'], (int)$claims['sub'], (float)$body['observer_score'], (string)$body['observer_category'], $body['notes']??null)],201);
        case 'observer.list':
            $claims=requireClaims($jwt); requireRoles($claims,['admin','supervisor','observer']);
            jsonResponse(['data' => $observer->listByScan((int)$claims['org'], (int)$vars['scan_id'])]);
        case 'billing.usage':
            $claims=requireClaims($jwt); requireRoles($claims,['admin']);
            jsonResponse(['data' => $billing->monthlyUsage((int)$claims['org'])]);
        case 'billing.plans':
            $claims=requireClaims($jwt); requireRoles($claims,['admin']);
            jsonResponse(['data' => $billing->plans()]);
    }

    jsonResponse(['error'=>'Unhandled route'],500);
} catch (Throwable $e) {
    $logger->error('request_failed', ['path' => $path, 'method' => $method, 'error' => $e->getMessage()]);
    jsonResponse(['error' => $e->getMessage()], 400);
}
