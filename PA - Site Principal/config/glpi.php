<?php
$_glpi_env_file = __DIR__ . '/../.env';
if (file_exists($_glpi_env_file)) {
    $env = parse_ini_file($_glpi_env_file);
    if (is_array($env)) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}
unset($_glpi_env_file, $env, $key, $value);
