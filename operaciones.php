<?php
$expr = $_POST["expr"] ?? "";
$display = $_POST["display"] ?? "0";
$error = "";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function pretty_expr($expr) {
    return strtr($expr, ["*" => "×", "/" => "÷"]);
}

function evaluar_expresion($expr) {
    $expr = str_replace([' ', "\t", "\n", "\r"], '', $expr);
    if ($expr === "") return ["ok" => true, "valor" => 0];

    if (!preg_match('/^[\d.+\-*\/]+$/', $expr)) {
        return ["ok" => false, "error" => "Caracteres inválidos."];
    }

    // Tokenizar
    preg_match_all('/(?:(?<=\d|\.)-)?\d+(?:\.\d+)?|[+\-*\/]/', $expr, $matches);
    $tokens = $matches[0];

    if (empty($tokens)) return ["ok" => false, "error" => "Expresión vacía."];

    // Shunting Yard
    $colaSalida = [];
    $pilaOperadores = [];
    $precedencia = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

    foreach ($tokens as $token) {
        if (is_numeric($token)) {
            $colaSalida[] = (float)$token;
        } elseif (isset($precedencia[$token])) {
            while (!empty($pilaOperadores)) {
                $top = end($pilaOperadores);
                if (isset($precedencia[$top]) && $precedencia[$top] >= $precedencia[$token]) {
                    $colaSalida[] = array_pop($pilaOperadores);
                } else {
                    break;
                }
            }
            $pilaOperadores[] = $token;
        }
    }
    
    while (!empty($pilaOperadores)) {
        $colaSalida[] = array_pop($pilaOperadores);
    }

    // Evaluar RPN
    $pilaEvaluacion = [];
    foreach ($colaSalida as $token) {
        if (is_numeric($token)) {
            $pilaEvaluacion[] = $token;
        } else {
            if (count($pilaEvaluacion) < 2) return ["ok" => false, "error" => "Operación incompleta."];
            $b = array_pop($pilaEvaluacion);
            $a = array_pop($pilaEvaluacion);
            
            switch ($token) {
                case '+': $pilaEvaluacion[] = $a + $b; break;
                case '-': $pilaEvaluacion[] = $a - $b; break;
                case '*': $pilaEvaluacion[] = $a * $b; break;
                case '/': 
                    if ($b == 0) return ["ok" => false, "error" => "División por cero."];
                    $pilaEvaluacion[] = $a / $b; 
                    break;
            }
        }
    }

    if (count($pilaEvaluacion) !== 1) return ["ok" => false, "error" => "Error de sintaxis."];

    return ["ok" => true, "valor" => $pilaEvaluacion[0]];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tecla = $_POST["key"] ?? null;
    
    if ($tecla !== null) {
        $limiteCaracteres = 30;
        $mapaTeclas = ["×" => "*", "÷" => "/"];
        $teclaInterna = $mapaTeclas[$tecla] ?? $tecla;

        if ($tecla === "C") {
            $expr = "";
            $display = "0";
            $error = "";
        } elseif ($tecla === "±") {
            if ($expr === "") {
                $expr = "-";
                $display = "-";
            } else {
                if (preg_match('/(-?[\d.]+)$/', $expr, $m, PREG_OFFSET_CAPTURE)) {
                    $num = $m[0][0];
                    $offset = $m[0][1];
                    $nuevoNum = (str_starts_with($num, "-")) ? substr($num, 1) : "-" . $num;
                    $expr = substr($expr, 0, $offset) . $nuevoNum;
                    $display = $expr;
                }
            }
        } elseif ($tecla === "%") {
            $expr .= "/100";
            $display = $expr;
        } elseif ($tecla === "=") {
            if ($expr === "") {
                $display = "0";
            } else {
                $resultado = evaluar_expresion($expr);
                if ($resultado["ok"]) {
                    $display = (string)(float)$resultado["valor"];
                    $expr = $display;
                } else {
                    $error = $resultado["error"];
                }
            }
        } elseif ($tecla === "⌫") {
             if ($expr !== "") $expr = substr($expr, 0, -1);
             $display = $expr === "" ? "0" : $expr;
        } else {
            $esOperador = in_array($teclaInterna, ["+", "-", "*", "/"]);
            
            if ($esOperador) {
                if ($expr === "" && $teclaInterna !== "-") {
                    // Ignorar
                } else {
                    $ultimo = substr($expr, -1);
                    if (in_array($ultimo, ["+", "-", "*", "/"])) {
                        $expr = substr($expr, 0, -1) . $teclaInterna;
                    } else {
                        if (strlen($expr) < $limiteCaracteres) $expr .= $teclaInterna;
                    }
                }
            } else {
                if (strlen($expr) < $limiteCaracteres) {
                    if ($expr === "0" && $teclaInterna !== ".") {
                        $expr = $teclaInterna;
                    } else {
                        if ($teclaInterna === ".") {
                            if (preg_match('/[\d.]+$/', $expr, $m)) {
                                if (!str_contains($m[0], ".")) $expr .= ".";
                            } elseif ($expr === "" || in_array(substr($expr, -1), ["+", "-", "*", "/"])) {
                                $expr .= "0.";
                            }
                        } else {
                            $expr .= $teclaInterna;
                        }
                    }
                }
            }
            $display = $expr;
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Calculadora PHP</title>
    <link rel="stylesheet" href="./css/styles.css" />
</head>
<body class="calc">
    <div class="wrap">
        <div class="calc-shell">
            <div class="calc-header">
                <div class="brand">PHP Calc</div>
                <div class="mode">Estándar</div>
            </div>
            
            <div class="calc-screen" aria-live="polite">
                <div class="history"><?= h(pretty_expr($expr)) ?></div>
                <div class="current"><?= h($display === "" ? "0" : pretty_expr($display)) ?></div>
            </div>

            <?php if ($error !== ""): ?>
                <div class="calc-alert" role="alert"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="expr" value="<?= h($expr) ?>" />
                <input type="hidden" name="display" value="<?= h($display) ?>" />

                <div class="keypad" role="group" aria-label="Teclado numérico">
                    <button class="btn action" type="submit" name="key" value="C">C</button>
                    <button class="btn action" type="submit" name="key" value="±">±</button>
                    <button class="btn action" type="submit" name="key" value="%">%</button>
                    <button class="btn op" type="submit" name="key" value="÷">÷</button>

                    <button class="btn num" type="submit" name="key" value="7">7</button>
                    <button class="btn num" type="submit" name="key" value="8">8</button>
                    <button class="btn num" type="submit" name="key" value="9">9</button>
                    <button class="btn op" type="submit" name="key" value="×">×</button>

                    <button class="btn num" type="submit" name="key" value="4">4</button>
                    <button class="btn num" type="submit" name="key" value="5">5</button>
                    <button class="btn num" type="submit" name="key" value="6">6</button>
                    <button class="btn op" type="submit" name="key" value="-">−</button>

                    <button class="btn num" type="submit" name="key" value="1">1</button>
                    <button class="btn num" type="submit" name="key" value="2">2</button>
                    <button class="btn num" type="submit" name="key" value="3">3</button>
                    <button class="btn op" type="submit" name="key" value="+">+</button>

                    <button class="btn num zero" type="submit" name="key" value="0">0</button>
                    <button class="btn num" type="submit" name="key" value=".">.</button>
                    <button class="btn eq" type="submit" name="key" value="=">=</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
