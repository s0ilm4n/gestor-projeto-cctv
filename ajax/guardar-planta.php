<?php
/**
 * AJAX: Guardar estado da planta
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['planta_id'])) {
    die(json_encode(['success' => false, 'error' => 'Dados inválidos']));
}

$planta_id = (int)$input['planta_id'];

try {
    $db->beginTransaction();

    // Guardar dados_json (estado do canvas)
    $dados_json = $input['dados_json'] ?? '';
    $stmt = $db->prepare("UPDATE plantas SET dados_json = ? WHERE id = ?");
    $stmt->execute([$dados_json, $planta_id]);

    // Eliminar dados existentes e reinserir
    $db->prepare("DELETE FROM plantas_cameras WHERE planta_id = ?")->execute([$planta_id]);
    $db->prepare("DELETE FROM plantas_acessos WHERE planta_id = ?")->execute([$planta_id]);
    $db->prepare("DELETE FROM plantas_cabos WHERE planta_id = ?")->execute([$planta_id]);

    // Inserir câmaras
    if (isset($input['cameras']) && is_array($input['cameras'])) {
        $stmt = $db->prepare("INSERT INTO plantas_cameras (planta_id, equipamento_id, nome, pos_x, pos_y, orientacao_graus, resolucao_h, resolucao_v, distancia_focal_mm, sensor_largura_mm, objetivo_dori, ppm_calculado, nivel_dori, conforme) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($input['cameras'] as $cam) {
            $stmt->execute([
                $planta_id,
                $cam['equipamento_id'] ?: null,
                $cam['nome'] ?? '',
                $cam['pos_x'] ?? 0,
                $cam['pos_y'] ?? 0,
                $cam['orientacao_graus'] ?? 0,
                $cam['resolucao_h'] ?? 1920,
                $cam['resolucao_v'] ?? 1080,
                $cam['distancia_focal_mm'] ?? 4.0,
                $cam['sensor_largura_mm'] ?? 5.6,
                $cam['objetivo_dori'] ?? 'R',
                $cam['ppm_calculado'] ?? null,
                $cam['nivel_dori'] ?? null,
                $cam['conforme'] ?? null
            ]);
        }
    }

    // Inserir acessos
    if (isset($input['acessos']) && is_array($input['acessos'])) {
        $stmt = $db->prepare("INSERT INTO plantas_acessos (planta_id, tipo, nome, pos_x, pos_y) VALUES (?,?,?,?,?)");
        foreach ($input['acessos'] as $acc) {
            $stmt->execute([
                $planta_id,
                $acc['tipo'] ?? 'leitor',
                $acc['nome'] ?? '',
                $acc['pos_x'] ?? 0,
                $acc['pos_y'] ?? 0
            ]);
        }
    }

    // Inserir cabos
    if (isset($input['cabos']) && is_array($input['cabos'])) {
        $stmt = $db->prepare("INSERT INTO plantas_cabos (planta_id, caminho_json) VALUES (?,?)");
        foreach ($input['cabos'] as $cabo) {
            $stmt->execute([$planta_id, $cabo['caminho_json'] ?? '[]']);
        }
    }

    $db->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
