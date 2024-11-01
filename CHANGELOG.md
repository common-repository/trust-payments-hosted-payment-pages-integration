WooCommerce Trust Payments Gateway - changelog
======================================

version 1.1.3
- Fixed issue with Applepay not displaying in certain cases.
- Fixed issue with Pay for Order page not working with our payment methods.
- Added supported to WooCommerce plugin "All Products for Woo Subscriptions".
- Prevented JSINIT request from being submitted if the module is disabled.
- Added compatibility for WooCommerce version v9.1.2
- Added compatibility for Wordpress version v6.5.5

version 1.1.2
- Added Account 2 Account (Pay by bank) payment type to the checkout
- Fixed issue with payment options not correctly displaying for "Customer payment page" link 
- Fixed PHP warnings
- Added compatibility for WooCommerce v8.5 and v8.6
- Fixed issue with Apple Pay where the wallet verify would not dispay in certain cases
- Added locale support
 
version 1.1.1
- Fixed issue with notifications not being accepting with the hosted checkout flow
- Fixed issue with the API flow where the option wasn't visible to guest users
- Fixed issue with saved cards on the US platform
- Added compatibility for WordPress v6.4.1
 
version 1.1.0
 
- Added new checkout flow that utilises the JavaScript library
- Added recurring subscriptions using the WooCommerce subscription engine (for hosted checkout and API)
- Added Google Pay, Apple Pay and PayPal to the checkout
- Added URL notifications in order to improve order status updates
- Updated plugin to support the newest versions of WooCommerce and WordPress
- Added support for PHP 8.1
- Users can now add a logo to their payment method within the plugin settings
- Fixed iframe redirect issue on hosted checkout which duplicated the page
- Added extra information in the order comments
- Fixed URL notification issue with MOTO transactions
- Updated the flow of saved cards of where now the user does not get redirected to make payment
- Added backwards compatibilty for previous versions
 
version 1.0.7
 
- Resolved issue with incomplete payments showing as successful
- Declined notification now correctly shown when using a saved card
 
version 1.0.6
 
- Fixed decline payments to correctly update order status to 'pending payment'
 
version 1.0.5
 
- Fixed issue with saving card to customers account
 
version 1.0.4
 
- Dont save card if walletsource=applepay/googlepay
- Add threedquery to accountcheck (saving your card on My account)
- Apdate saved card to use v3 JS library
 
version 1.0.2
 
- ApplePay address override
- Sending notifications
- Fix: Saving your card in "Payment Method" flags invalid field
- Fix: Incorrect URL redirect
 
version 1.0.1
 
- Fix: can't save cards when iframe is enabled
- Fix: MOTO + iframe doesn't show success page
- Add address update when using saved card
 
 
version 1.0.0
 
- Integrate your Trust Payments account with the WooCommerce store.
- Allow customers to checkout using credit and debit cards.
- Support of Saved Card functionality, using Tokenisation for security.
- Allows admin to easily track the transaction history.
- Allows logged in customers to pay using credit/debit cards saved on their account.
- Allows logged in customers to manage their saved payment card(s) on the My Account page.
- Allows admin to perform payments from the WooCommerce admin interface (MOTO).
- PayPal, Apple Pay and Visa Checkout are supported.
- First release