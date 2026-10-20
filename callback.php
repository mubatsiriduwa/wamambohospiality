<?php

declare(strict_types=1);
require __DIR__ . '/lib.php';

try {
    $config = wamambo_config();
    $incoming = $_POST;
    if (!$incoming || !wamambo_validate_hash($incoming, (string)$config['integration_key'])) {
        http_response_code(400);
        exit('Invalid notification');
    }
    $reference = wamambo_clean_reference((string)($incoming['reference'] ?? $_GET['reference'] ?? ''));
    $record = wamambo_load_transaction($reference);
    $pollUrl = (string)($incoming['pollurl'] ?? ($record['pollurl'] ?? ''));

    // For important updates Paynow recommends confirming the status by polling.
    $confirmed = $incoming;
    if ($pollUrl !== '' && in_array(strtolower((string)($incoming['status'] ?? '')), ['paid', 'awaiting delivery', 'delivered'], true)) {
        $pollRaw = wamambo_post_form($pollUrl, []);
        $poll = wamambo_parse_response($pollRaw);
        if (wamambo_validate_hash($poll, (string)$config['integration_key'])) {
            $confirmed = $poll;
        }
    }

    wamambo_store_transaction($reference, [
        'status' => (string)($confirmed['status'] ?? 'Unknown'),
        'amount' => (string)($confirmed['amount'] ?? ($record['amount'] ?? '')),
        'paynowreference' => (string)($confirmed['paynowreference'] ?? ($record['paynowreference'] ?? '')),
        'pollurl' => (string)($confirmed['pollurl'] ?? $pollUrl),
        'paymentchannel' => (string)($confirmed['paymentchannel'] ?? ''),
        'last_notification_at' => gmdate('c'),
    ]);
    http_response_code(200);
    exit('OK');
} catch (Throwable $e) {
    http_response_code(500);
    exit('Unable to process notification');
}
