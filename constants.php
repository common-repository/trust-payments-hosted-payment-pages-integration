<?php
/**
 * Author: Trust Payments
 * User: Rasamee
 * Date: 29/11/2019
 * @since 1.0.0
 */

define('SECURETRADING_TRANSACTION_TYPE', 'st_transaction');
define('SECURETRADING_ID', 'securetrading_iframe');
define('SECURETRADING_API_ID', 'securetrading_api');
define('SECURETRADING_GOOGLE_PAY', 'securetrading_google_pay');
define('SECURETRADING_APPLE_PAY', 'securetrading_apple_pay');
define('SECURETRADING_PAYPAL', 'securetrading_paypal');
define('SECURETRADING_A2A', 'securetrading_a2a');

// Define Payment Page Domain URL
define('SECURETRADING_EU_DOMAIN_URL', 'https://payments.securetrading.net');
define('SECURETRADING_US_DOMAIN_URL', 'https://payments.securetrading.us');

// Define MyST
define('SECURETRADING_EU_MYST', 'https://myst.securetrading.net');
define('SECURETRADING_US_MYST', 'https://myst.securetrading.us');

// Define Web App
define('SECURETRADING_EU_WEBAPP', 'https://webservices.securetrading.net');
define('SECURETRADING_US_WEBAPP', 'https://webservices.securetrading.us');

// Define Webservices
define('SECURETRADING_EU_WEBSERVICES', 'https://cdn.eu.trustpayments.com/js/latest/st.js');
define('SECURETRADING_US_WEBSERVICES', 'https://cdn.us.trustpayments.us/js/latest/st.js');

// Settle status
define('SECURE_TRADING_PENDING_SETTLEMENT', '0');
define('SECURE_TRADING_MANUAL_SETTLEMENT', '1');
define('SECURE_TRADING_SUSPENDED', '2');
define('SECURE_TRADING_CANCELLED', '3');
define('SECURE_TRADING_SETTLING', '10');
define('SECURE_TRADING_SETTLED', '100');

// Rule Manager
define('SECURE_TRADING_SUCCESS_REDIRECT', 'STR-6');
define('SECURE_TRADING_DECLINED_REDIRECT', 'STR-7');
define('SECURE_TRADING_SUCCESS_NOTIFICATION', 'STR-8');
define('SECURE_TRADING_DECLINED_NOTIFICATION', 'STR-9');
define('SECURE_TRADING_ALL_NOTIFICATION', 'STR-10');

// Webservices
define('SECURE_TRADING_EU_WEBSERVICES_JSON', 'https://webservices.securetrading.net/json/');
define('SECURE_TRADING_US_WEBSERVICES_JSON', 'https://webservices.securetrading.us/json/');
define('SECURE_TRADING_US_WEBSERVICES_JWT', 'https://webservices.securetrading.us/jwt/');