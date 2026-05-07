<?php
declare(strict_types=1);

// Mini parser .env senza dipendenze.
// - righe vuote e righe che iniziano con # sono ignorate
// - KEY=VALUE; supporta valori tra " " o ' '
// - non sovrascrive variabili già presenti in getenv()/$_ENV

function env_load(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $raw) {
        $line = trim($raw);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));

        // Strip commenti inline (solo se il valore non è quotato)
        if ($val !== '' && $val[0] !== '"' && $val[0] !== "'") {
            $hash = strpos($val, ' #');
            if ($hash !== false) {
                $val = rtrim(substr($val, 0, $hash));
            }
        }

        $len = strlen($val);
        if ($len >= 2) {
            $first = $val[0];
            $last  = $val[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }

        if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
            continue;
        }

        if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string)$_ENV[$key];
    }
    return $default;
}
