<?php
$expr = $_POST["expr"] ?? "";
$display = $_POST["display"] ?? "0";
$error = "";

function h($value)
{
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function format_number($value)
{
  if (!is_finite($value)) {
    return "Error";
  }

  $rounded = round($value, 12);
  if (abs($rounded - round($rounded)) < 1e-12) {
    return (string)(int)round($rounded);
  }

  $str = number_format($rounded, 12, ".", "");
  $str = rtrim($str, "0");
  $str = rtrim($str, ".");
  if ($str === "-0") {
    $str = "0";
  }
  return $str;
}

function tokenize($expr)
{
  $tokens = [];
  $len = strlen($expr);
  $i = 0;
  $prevWasOp = true;

  while ($i < $len) {
    $ch = $expr[$i];
    if ($ch === " " || $ch === "\t" || $ch === "\n" || $ch === "\r") {
      $i++;
      continue;
    }

    if (($ch >= "0" && $ch <= "9") || $ch === "." || ($ch === "-" && $prevWasOp)) {
      $start = $i;
      $i++;
      $dotCount = ($ch === ".") ? 1 : 0;

      while ($i < $len) {
        $c = $expr[$i];
        if (($c >= "0" && $c <= "9")) {
          $i++;
          continue;
        }
        if ($c === ".") {
          $dotCount++;
          if ($dotCount > 1) {
            return null;
          }
          $i++;
          continue;
        }
        break;
      }

      $numStr = substr($expr, $start, $i - $start);
      if ($numStr === "-" || $numStr === "." || $numStr === "-.") {
        return null;
      }
      $num = filter_var($numStr, FILTER_VALIDATE_FLOAT);
      if ($num === false) {
        return null;
      }
      $tokens[] = ["type" => "num", "value" => (float)$num];
      $prevWasOp = false;
      continue;
    }

    if ($ch === "+" || $ch === "-" || $ch === "*" || $ch === "/") {
      $tokens[] = ["type" => "op", "value" => $ch];
      $i++;
      $prevWasOp = true;
      continue;
    }

    return null;
  }
}

function eval_expr($expr)
{
  $tokens = tokenize($expr);
  if ($tokens === null || count($tokens) === 0) {
    return ["ok" => false, "error" => "Expresión inválida."];
  }

  if ($tokens[count($tokens) - 1]["type"] === "op") {
    return ["ok" => false, "error" => "Completa la operación."];
  }

  $prec = ["+" => 1, "-" => 1, "*" => 2, "/" => 2];
  $out = [];
  $ops = [];

  foreach ($tokens as $t) {
    if ($t["type"] === "num") {
      $out[] = $t;
      continue;
    }

    $op = $t["value"];
    while (count($ops) > 0) {
      $top = $ops[count($ops) - 1];
      if ($prec[$top] >= $prec[$op]) {
        $out[] = ["type" => "op", "value" => array_pop($ops)];
        continue;
      }
      break;
    }
    $ops[] = $op;
  }

  while (count($ops) > 0) {
    $out[] = ["type" => "op", "value" => array_pop($ops)];
  }

  $stack = [];
  foreach ($out as $t) {
    if ($t["type"] === "num") {
      $stack[] = $t["value"];
      continue;
    }
    if (count($stack) < 2) {
      return ["ok" => false, "error" => "Expresión inválida."];
    }
    $b = array_pop($stack);
    $a = array_pop($stack);
    switch ($t["value"]) {
      case "+":
        $stack[] = $a + $b;
        break;
      case "-":
        $stack[] = $a - $b;
        break;
      case "*":
        $stack[] = $a * $b;
        break;
      case "/":
        if ((float)$b === 0.0) {
          return ["ok" => false, "error" => "No se puede dividir para cero."];
        }
        $stack[] = $a / $b;
        break;
    }
  }

  if (count($stack) !== 1) {
    return ["ok" => false, "error" => "Expresión inválida."];
  }

  return ["ok" => true, "value" => (float)$stack[0]];
}

function last_number_span($expr)
{
  if ($expr === "") {
    return null;
  }

  if (preg_match('/(-?\d+(?:\.\d+)?|-?\.\d+)$/', $expr, $m, PREG_OFFSET_CAPTURE)) {
    $match = $m[0][0];
    $offset = $m[0][1];
    return ["start" => $offset, "len" => strlen($match), "value" => $match];
  }
  return null;
}

function pretty_expr($expr)
{
  return strtr($expr, ["*" => "×", "/" => "÷"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $key = $_POST["key"] ?? null;
  if ($key !== null) {
    $maxLen = 60;
    $ops = ["+", "-", "*", "/"];
    $isOp = in_array($key, ["+", "-", "×", "÷"], true);
    $mappedKey = $key;
    if ($key === "×") {
      $mappedKey = "*";
    } elseif ($key === "÷") {
      $mappedKey = "/";
    }

    if ($key === "C") {
      $expr = "";
      $display = "0";
      $error = "";
    } elseif ($key === "⌫") {
      if ($expr !== "") {
        $expr = substr($expr, 0, -1);
      }
      $display = $expr === "" ? "0" : $display;
      $error = "";
    } elseif ($key === "=") {
      if ($expr === "") {
        $display = "0";
        $error = "";
      } else {
        $res = eval_expr($expr);
        if (!$res["ok"]) {
          $error = $res["error"];
        } else {
          $display = format_number($res["value"]);
          $expr = $display;
          $error = "";
        }
      }
    } elseif ($key === "±") {
      $span = last_number_span($expr);
      if ($span !== null) {
        $numStr = $span["value"];
        if (str_starts_with($numStr, "-")) {
          $new = substr($numStr, 1);
        } else {
          $new = "-" . $numStr;
        }
        $expr = substr($expr, 0, $span["start"]) . $new;
        $display = $new;
        $error = "";
      } elseif ($expr === "") {
        $expr = "-";
        $display = "-";
        $error = "";
      }
    } elseif ($key === "%") {
      $span = last_number_span($expr);
      if ($span !== null) {
        $num = filter_var($span["value"], FILTER_VALIDATE_FLOAT);
        if ($num !== false) {
          $new = format_number(((float)$num) / 100.0);
          $expr = substr($expr, 0, $span["start"]) . $new;
          $display = $new;
          $error = "";
        }
      }
    } elseif ($isOp) {
      $opChar = $mappedKey;
      if ($expr === "" && $opChar === "-") {
        $expr = "-";
        $display = "-";
        $error = "";
      } elseif ($expr !== "") {
        $last = substr($expr, -1);
        if (in_array($last, $ops, true)) {
          $expr = substr($expr, 0, -1) . $opChar;
        } else {
          if (strlen($expr) < $maxLen) {
            $expr .= $opChar;
          }
        }
        $display = $display === "0" ? "0" : $display;
        $error = "";
      }
    } else {
      if (preg_match('/^[0-9]$/', $key) === 1 || $key === ".") {
        $last = $expr === "" ? "" : substr($expr, -1);
        if ($key === "." && last_number_span($expr) !== null) {
          $span = last_number_span($expr);
          if ($span !== null && str_contains($span["value"], ".")) {
            $key = "";
          }
        }

        if ($key !== "") {
          if ($expr === "" && $key === ".") {
            $expr = "0.";
          } elseif ($expr === "0" && $key !== ".") {
            $expr = $key;
          } elseif (in_array($last, ["+" , "-" , "*" , "/"], true) && $key === ".") {
            if (strlen($expr) < $maxLen) {
              $expr .= "0.";
            }
          } else {
            if (strlen($expr) < $maxLen) {
              $expr .= $key;
            }
          }

          $span = last_number_span($expr);
          $display = $span ? $span["value"] : $expr;
          $error = "";
        }
      }
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
        <div class="calc-top">
          <div class="calc-title">
            <div>Calculadora</div>
            <div>PHP</div>
          </div>
          <div class="calc-display" aria-live="polite">
            <div class="expr"><?= h(pretty_expr($expr)) ?></div>
            <div class="value"><?= h($display) ?></div>
          </div>
          <?php if ($error !== ""): ?>
            <div class="calc-error" role="alert"><?= h($error) ?></div>
          <?php endif; ?>
        </div>

        <form method="post" action="">
          <input type="hidden" name="expr" value="<?= h($expr) ?>" />
          <input type="hidden" name="display" value="<?= h($display) ?>" />

          <div class="keys" role="group" aria-label="Teclado de calculadora">
            <button class="key fn" type="submit" name="key" value="C">C</button>
            <button class="key fn" type="submit" name="key" value="±">±</button>
            <button class="key fn" type="submit" name="key" value="%">%</button>
            <button class="key op" type="submit" name="key" value="÷">÷</button>

            <button class="key" type="submit" name="key" value="7">7</button>
            <button class="key" type="submit" name="key" value="8">8</button>
            <button class="key" type="submit" name="key" value="9">9</button>
            <button class="key op" type="submit" name="key" value="×">×</button>

            <button class="key" type="submit" name="key" value="4">4</button>
            <button class="key" type="submit" name="key" value="5">5</button>
            <button class="key" type="submit" name="key" value="6">6</button>
            <button class="key op" type="submit" name="key" value="-">-</button>

            <button class="key" type="submit" name="key" value="1">1</button>
            <button class="key" type="submit" name="key" value="2">2</button>
            <button class="key" type="submit" name="key" value="3">3</button>
            <button class="key op" type="submit" name="key" value="+">+</button>

            <button class="key zero" type="submit" name="key" value="0">0</button>
            <button class="key" type="submit" name="key" value=".">.</button>
            <button class="key eq" type="submit" name="key" value="=">=</button>
          </div>
        </form>
      </div>
    </div>
  </body>
</html>
