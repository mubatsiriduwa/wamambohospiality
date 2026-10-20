<?php

declare(strict_types=1);

function wamambo_config(): array {
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) {
        throw new RuntimeException('Paynow is not configured yet. Copy payment/config.example.php to payment/config.php and add the merchant credentials.');
    }
    $config = require $path;
    foreach (['integration_id', 'integration_key', 'site_url', 'initiate_url'] as $key) {
        if (empty($config[$key]) || str_starts_with((string)$config[$key], 'YOUR_')) {
            throw new RuntimeException('Paynow configuration is incomplete.');
        }
    }
    return $config;
}

function wamambo_hash(array $values, string $integrationKey): string {
    $joined = '';
    foreach ($values as $key => $value) {
        if (strtoupper((string)$key) !== 'HASH') {
            $joined .= (string)$value;
        }
    }
    return strtoupper(hash('sha512', $joined . $integrationKey));
}

function wamambo_validate_hash(array $values, string $integrationKey): bool {
    $received = '';
    foreach ($values as $key => $value) {
        if (strtoupper((string)$key) === 'HASH') {
            $received = strtoupper((string)$value);
            break;
        }
    }
    return $received !== '' && hash_equals(wamambo_hash($values, $integrationKey), $received);
}

function wamambo_post_form(string $url, array $fields): string {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required for Paynow payments. Enable the cURL extension on the server.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Unable to contact Paynow: ' . $error);
    }
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException('Paynow returned HTTP ' . $code . '.');
    }
    return (string)$response;
}

function wamambo_parse_response(string $response): array {
    $data = [];
    parse_str($response, $data);
    return $data;
}

function wamambo_store_transaction(string $reference, array $data): void {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $reference);
    $file = $dir . '/' . $safe . '.json';
    $existing = [];
    if (is_file($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) $existing = $decoded;
    }
    $record = array_merge($existing, $data, ['reference' => $reference, 'updated_at' => gmdate('c')]);
    file_put_contents($file, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function wamambo_load_transaction(string $reference): ?array {
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $reference);
    $file = __DIR__ . '/data/' . $safe . '.json';
    if (!is_file($file)) return null;
    $decoded = json_decode((string)file_get_contents($file), true);
    return is_array($decoded) ? $decoded : null;
}

function wamambo_clean_reference(string $reference): string {
    $reference = strtoupper(trim($reference));
    $reference = preg_replace('/[^A-Z0-9_-]/', '-', $reference);
    return substr($reference ?: 'WAM-' . gmdate('Ymd-His'), 0, 50);
}
