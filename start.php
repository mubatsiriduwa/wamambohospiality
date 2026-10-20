<?php

declare(strict_types=1);
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../contact.html#paynow-payment');
    exit;
}

try {
    $config = wamambo_config();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $reference = wamambo_clean_reference((string)($_POST['reference'] ?? ''));
    $amount = round((float)($_POST['amount'] ?? 0), 2);

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please enter your name and a valid email address.');
    }
    if ($amount < (float)($config['minimum_amount'] ?? 1.00)) {
        throw new RuntimeException('Please enter a valid quoted payment amount.');
    }

    $site = rtrim((string)$config['site_url'], '/');
    $returnUrl = $site . '/payment/return.php?reference=' . rawurlencode($reference);
    $resultUrl = $site . '/payment/callback.php?reference=' . rawurlencode($reference);

    $fields = [
        'id' => (string)$config['integration_id'],
        'reference' => $reference,
        'amount' => number_format($amount, 2, '.', ''),
        'additionalinfo' => 'Wamambo Tourism booking/deposit ' . $reference,
        'returnurl' => $returnUrl,
        'resulturl' => $resultUrl,
        'authemail' => $email,
        'authphone' => $phone,
        'authname' => $name,
        'status' => 'Message',
    ];
    $fields['hash'] = wamambo_hash($fields, (string)$config['integration_key']);

    $responseRaw = wamambo_post_form((string)$config['initiate_url'], $fields);
    $response = wamambo_parse_response($responseRaw);
    if (!wamambo_validate_hash($response, (string)$config['integration_key'])) {
        throw new RuntimeException('Could not verify Paynow response. Payment was not started.');
    }
    if (strtolower((string)($response['status'] ?? '')) !== 'ok' || empty($response['browserurl']) || empty($response['pollurl'])) {
        throw new RuntimeException((string)($response['error'] ?? 'Paynow could not start the payment.'));
    }

    wamambo_store_transaction($reference, [
        'customer_name' => $name,
        'customer_email' => $email,
        'customer_phone' => $phone,
        'amount' => number_format($amount, 2, '.', ''),
        'currency' => 'USD',
        'status' => 'Created',
        'pollurl' => (string)$response['pollurl'],
        'paynowreference' => (string)($response['paynowreference'] ?? ''),
        'created_at' => gmdate('c'),
    ]);

    header('Location: ' . $response['browserurl'], true, 303);
    exit;
} catch (Throwable $e) {
    $message = rawurlencode($e->getMessage());
    header('Location: return.php?state=error&message=' . $message, true, 303);
    exit;
}
