<?php

declare(strict_types=1);
require __DIR__ . '/lib.php';

$reference = wamambo_clean_reference((string)($_GET['reference'] ?? ''));
$state = (string)($_GET['state'] ?? '');
$message = (string)($_GET['message'] ?? '');
$status = 'Pending verification';
$amount = '';
$paynowReference = '';
$error = '';

try {
    if ($state === 'error') throw new RuntimeException($message ?: 'The payment could not be started.');
    $config = wamambo_config();
    $record = wamambo_load_transaction($reference);
    if (!$record) throw new RuntimeException('We could not find this payment reference. Please contact Wamambo with your booking reference.');
    $amount = (string)($record['amount'] ?? '');
    $paynowReference = (string)($record['paynowreference'] ?? '');
    $pollUrl = (string)($record['pollurl'] ?? '');

    if ($pollUrl !== '') {
        $raw = wamambo_post_form($pollUrl, []);
        $poll = wamambo_parse_response($raw);
        if (!wamambo_validate_hash($poll, (string)$config['integration_key'])) {
            throw new RuntimeException('The Paynow status response could not be verified.');
        }
        $status = (string)($poll['status'] ?? 'Unknown');
        $amount = (string)($poll['amount'] ?? $amount);
        $paynowReference = (string)($poll['paynowreference'] ?? $paynowReference);
        wamambo_store_transaction($reference, [
            'status' => $status,
            'amount' => $amount,
            'paynowreference' => $paynowReference,
            'pollurl' => (string)($poll['pollurl'] ?? $pollUrl),
            'paymentchannel' => (string)($poll['paymentchannel'] ?? ''),
            'last_checked_at' => gmdate('c'),
        ]);
    } else {
        $status = (string)($record['status'] ?? 'Pending verification');
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$paid = in_array(strtolower($status), ['paid', 'awaiting delivery', 'delivered'], true);
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment Status | Wamambo Tourism Afrika</title><link rel="stylesheet" href="../css/style.css"></head>
<body class="bg-background text-foreground"><main class="min-h-screen flex items-center justify-center px-4 py-16"><section class="w-full max-w-2xl bg-card border border-border rounded-2xl p-8 shadow-xl text-center">
<img src="../images/logo.png" alt="Wamambo Tourism Afrika" class="h-16 w-auto mx-auto mb-6">
<p class="text-xs tracking-[0.3em] uppercase text-primary mb-3">Secure Paynow Payment</p>
<h1 class="font-display text-3xl font-bold mb-4"><?php echo $error ? 'Payment could not be verified' : ($paid ? 'Payment received' : 'Payment status'); ?></h1>
<?php if ($error): ?><p class="text-muted-foreground mb-6"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php else: ?>
<div class="text-left max-w-md mx-auto border border-border rounded-xl p-5 space-y-3 mb-6">
<p><strong>Booking reference:</strong> <?php echo htmlspecialchars($reference, ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($amount !== ''): ?><p><strong>Amount:</strong> USD <?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<p><strong>Status:</strong> <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($paynowReference !== ''): ?><p><strong>Paynow reference:</strong> <?php echo htmlspecialchars($paynowReference, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
</div>
<?php if ($paid): ?><p class="text-muted-foreground mb-6">Your payment has been confirmed. Keep your booking reference for your records.</p><?php else: ?><p class="text-muted-foreground mb-6">If you have just paid, the transaction may still be updating. Wamambo can verify it using your booking reference.</p><?php endif; ?>
<?php endif; ?>
<div class="flex flex-col sm:flex-row gap-3 justify-center"><a href="../contact.html" class="gold-gradient px-6 py-3 rounded-lg text-sm font-semibold uppercase tracking-wider text-primary-foreground">Back to booking</a><a href="https://wa.me/263719429403" class="px-6 py-3 rounded-lg border border-border text-sm font-semibold uppercase tracking-wider">Contact Wamambo</a></div>
</section></main></body></html>
