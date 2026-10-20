<?php
// Copy this file to config.php on the server and insert Wamambo's real Paynow credentials.
// NEVER commit or share config.php publicly.
return [
    'integration_id' => 'YOUR_PAYNOW_INTEGRATION_ID',
    'integration_key' => 'YOUR_PAYNOW_INTEGRATION_KEY',
    // Must be the public HTTPS URL where this website is hosted, with no trailing slash.
    'site_url' => 'https://www.example.com',
    // Paynow production initiation endpoint.
    'initiate_url' => 'https://www.paynow.co.zw/interface/initiatetransaction',
    // Minimum payment accepted by this payment page. Change if Wamambo requires a higher minimum.
    'minimum_amount' => 1.00,
];
