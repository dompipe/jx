<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/jx/plugins/AnatomyGLB.php';

use jx\plugins\AnatomyGLBPlugin;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    header('Allow: POST');
    echo json_encode(['error'=>'POST a JX anatomy model to export GLB']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') throw new RuntimeException('Empty export request');
    if (strlen($raw) > 40 * 1024 * 1024) throw new RuntimeException('Export request exceeds 40 MiB');
    $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) throw new RuntimeException('Invalid export payload');
    $model = $payload['model'] ?? null;
    $textures = $payload['textures'] ?? [];
    if (!is_array($model)) throw new RuntimeException('Missing JX anatomy model');
    if (!is_array($textures)) $textures = [];

    $glb = AnatomyGLBPlugin::export($model, array_values(array_filter($textures, 'is_array')));
    $name = preg_replace('/[^a-z0-9._-]+/i', '-', (string)($model['id'] ?? 'jx-anatomy-model')) ?: 'jx-anatomy-model';
    header('Content-Type: model/gltf-binary');
    header('Content-Length: ' . strlen($glb));
    header('Content-Disposition: attachment; filename="' . $name . '.glb"');
    header('Cache-Control: no-store');
    echo $glb;
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_SLASHES);
}
