<?php

// Configurar respuesta JSON
header('Content-Type: application/json');

// Leer datos enviados
$datos = json_decode(file_get_contents('php://input'), true);

$num1 = floatval($datos['num1'] ?? 0);
$num2 = floatval($datos['num2'] ?? 0);
$operacion = $datos['operacion'] ?? '';

// Realizar la operación
$resultado = 0;
$error = '';

switch ($operacion) {
    case '+':
        $resultado = $num1 + $num2;
        break;
    case '-':
        $resultado = $num1 - $num2;
        break;
    case '*':
        $resultado = $num1 * $num2;
        break;
    case '/':
        if ($num2 == 0) {
            $error = 'No se puede dividir por cero';
        } else {
            $resultado = $num1 / $num2;
        }
        break;
    default:
        $error = 'Operación no válida';
}

// Enviar respuesta
if ($error) {
    echo json_encode(['ok' => false, 'error' => $error]);
} else {
    echo json_encode(['ok' => true, 'resultado' => $resultado]);
}
