<?php
/**
 * AJAX: Importar DXF para a planta
 * Parse básico de DXF ASCII — extrai linhas, círculos, polylines
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$planta_id = (int)($_POST['planta_id'] ?? 0);
$dxf_content = $_POST['dxf_content'] ?? '';

if (!$planta_id || !$dxf_content) {
    die(json_encode(['success' => false, 'error' => 'Dados inválidos']));
}

$objects = [];

// Parse simples de DXF
$lines = explode("\n", $dxf_content);
$count = count($lines);
$i = 0;

while ($i < $count) {
    $code = trim($lines[$i]);
    $value = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';

    if ($code === '0') {
        if ($value === 'LINE') {
            // Procurar coordenadas
            $x1 = $y1 = $x2 = $y2 = 0;
            while ($i < $count && trim($lines[$i]) !== '0') {
                $code2 = trim($lines[$i]);
                $val2 = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';
                if ($code2 === '10') $x1 = (float)$val2;
                if ($code2 === '20') $y1 = (float)$val2;
                if ($code2 === '11') $x2 = (float)$val2;
                if ($code2 === '21') $y2 = (float)$val2;
                $i++;
            }
            // Escalar e centrar
            $scale = 5;
            $ox = 500; $oy = 300;
            $objects[] = [
                'type' => 'line',
                'x1' => $x1 * $scale + $ox,
                'y1' => $y1 * $scale + $oy,
                'x2' => $x2 * $scale + $ox,
                'y2' => $y2 * $scale + $oy
            ];
        } elseif ($value === 'CIRCLE') {
            $cx = $cy = $r = 0;
            while ($i < $count && trim($lines[$i]) !== '0') {
                $code2 = trim($lines[$i]);
                $val2 = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';
                if ($code2 === '10') $cx = (float)$val2;
                if ($code2 === '20') $cy = (float)$val2;
                if ($code2 === '40') $r = (float)$val2;
                $i++;
            }
            $scale = 5; $ox = 500; $oy = 300;
            $objects[] = [
                'type' => 'circle',
                'x' => $cx * $scale + $ox,
                'y' => $cy * $scale + $oy,
                'r' => abs($r) * $scale
            ];
        } elseif ($value === 'LWPOLYLINE' || $value === 'POLYLINE') {
            $pts = [];
            while ($i < $count) {
                $code2 = trim($lines[$i]);
                $val2 = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';
                if ($code2 === '10') {
                    $px = (float)$val2;
                    $py = 0;
                    // Procurar Y
                    $j = $i + 1;
                    while ($j < $count && trim($lines[$j]) !== '10' && trim($lines[$j]) !== '0') {
                        if (trim($lines[$j]) === '20') { $py = (float)$lines[$j+1]; break; }
                        $j++;
                    }
                    $scale = 5; $ox = 500; $oy = 300;
                    $pts[] = [$px * $scale + $ox, $py * $scale + $oy];
                }
                if ($code2 === '0' && $val2 !== 'VERTEX') break;
                $i++;
            }
            if (count($pts) >= 2) {
                for ($k = 0; $k < count($pts) - 1; $k++) {
                    $objects[] = [
                        'type' => 'line',
                        'x1' => $pts[$k][0],
                        'y1' => $pts[$k][1],
                        'x2' => $pts[$k+1][0],
                        'y2' => $pts[$k+1][1]
                    ];
                }
            }
        } else {
            $i += 2;
        }
    } else {
        $i += 2;
    }
}

echo json_encode(['success' => true, 'objects' => $objects, 'count' => count($objects)]);
