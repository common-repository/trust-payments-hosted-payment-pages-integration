=== Trust Payments Gateway for WooCommerce ===
Contributors: magenestjsc, trustpayments
Tags: credit card, debit card, Trust Payments, Trust payment gateway, woocommerce, woocommerce payment gateway, woocommerce Trust Payments, woocommerce payment, woocommerce Trust Payments payment, gateway, hosted payment pages, 3ds2, secure trading, card payments, applepay, apple pay, googlepay, google pay, gpay, paypal, pay pal, subscriptions, recurring, recurring subscriptions, subs, recurring subs, free trial, acquiring, pci compliant, pci, wallets, apms, payments, javascript, js library, js, js payments, js integration, secure payment, js plugin, hpp, hosted checkout, checkout, redirects, redirect payments
Requires at least: 4.7
Tested up to: 6.5.5
Requires PHP: 8.1.20
Stable tag: 1.1.3
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
 
The Trust Payments plugin offers a simple and easy to implement method for merchants to add e-payment capabilities to their WooCommerce online commerce setup.
 
== Description ==
Easily add payment capabilities to your ecommerce website:
 
* Build a seamless, fully customised online payment experience with or without any redirects (hosted payment page or JavaScript plug-in available) 
* Accept multiple payment types - credit/debit cards, Apple Pay/Google Pay and PayPal.
* Easily process Mail Order Telephone Order (MOTO) transactions through the admin panel.
* The Trust Payments extension can integrate with multiple APIs to facilitate typical back-office functions such as transaction updates and refunds.
 
== Features ==
With Trust Payments, merchants can:
 
* Accept all major payment cards (Visa, Mastercard, Amex, Diners/Discover)
* Alternative payment methods supported: ('PayPal' and 'Account to Account (A2A)')
* Offer recurring subscriptions
* Feature the gateway on your existing website by using a JavaScript Library integration
* Process payments securely on dedicated HTTPS server hosted by Trust Payments 
* Reduce your level of PCI DSS compliance to the lowest possible level by not handling sensitive payment data
* Benefit from the “Saved Cards” feature, returning customers can save their card details for faster transactions in the future	
* Customise your checkout page to reflect your brand and maintain great customer experience
* Accept a large variety of currencies and settle in 15 of these
* Deploy other new payment methods quickly with minimal configuration needed
* View and manage all transactions using our online portal including a Virtual Terminal for transactions not processed in-person
* Supports multiple languages for both the hosted payment and API flow. For further information on localisation please see https://help.trustpayments.com/hc/en-us/articles/4402728226321-Localisation
 
== Installation ==
= Using the Wordpress Dashboard =
 
**Please note: This plugin requires WooCommerce to work. Please install WooCommerce before proceeding.**
1. Navigate to Plugins -> Add New
2. Use the search field on the top right and enter "Trust Payments Gateway for WooCommerce"
3. Click the "Install Now" button to install the plugin
4. Click the "Activate" button or navigate to Plugins -> Installed Plugins -> Find the "Trust Payments Gateway for WooCommerce" plugin in the list and click "Activate"
5. Next, you are ready to configure the plugin with your unique account details provided by our Support team
6. (Optional) If you want to support recurring subscriptions you will also need to download and install the WooCommerce subscription engine
 
 
= Manual installation =
 
Manual installation method requires downloading the WooCommerce plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](https://wordpress.org/support/article/managing-plugins/).
 
= Updating =
 
Automatic updates should work smoothly, but we still recommend you backup your site.
 
If you encounter issues with the shop/category pages after an update, flush the permalinks by going to WordPress > Settings > Permalinks and hitting “Save.” That should return things to normal.
 
== Screenshots ==
 
1.  Additional payments/wallets
2.  Hosted Trust Payments checkout page
3.  Hosted checkout with an Iframe
4.  Embedded payment form on checkout page with wallets
 
 
== Frequently Asked Questions ==
 
= Where can I get support? =
If you get stuck, you can contact us [here](https://www.trustpayments.com/contact-us/)
 
= How our service works? =
Find out more about [our services](https://www.trustpayments.com/)
 
= What are our Terms of Use? =
Please follow our terms of use at [Trust Payment - Terms of Use](https://www.trustpayments.com/legal-terms-of-use/)
 
= Where can I find the user guide of the WooCommerce Trust Payments Gateway plugin? = 
You can find more information on the WooCommerce Trust Payments plugin [here](https://help.trustpayments.com/hc/en-us/sections/9682549422353-WooCommerce-using-Payment-Pages)
 
= Why am I getting an 'Invalid details' message on my page? =
Please ensure you have correctly entered in your Webservices details into the plugin settings. If you are still experiencing this issue, please contact our support team to get this checked.
 
= How can I offer recurring subscriptions? =
Firstly you will need to install the WooCommerce supscription engine [here](https://woo.com/products/woocommerce-subscriptions/?quid=92aa6bb884a316a4a8f03fe0747c1486) to your WordPress setup, create an account with Trust Payments and confirm with your acquirer that they support recurring, enable the plugin and provide your details within the configuration page, setup a recurring product and you should be all set. Trust Payments provide the gateway and can be your acquirer.
 
= What is an acquirer? =
An acquirer is licensed to process and settle payments by shoppers on behalf of its merchants. Trust Payments is a regulated financial institution and international payment processor which allows us to do this.
 
= I am using the hosted checkout flow and I want to support wallets, how can I do that? =
Please contact our support team asking them to enable wallets, and include your site reference in the request. Please note that some wallets aren't supported in an Iframe, if you are experiencing this, please disable "Use Iframe" in the plugin settings.
= Do I need to setup a Url Notification on my WooCommerce setup? =
We strongly recommend you using URL Notifications with your WooCommerce setup to correctly update the order after payment has been completed. The plugin will work with URL notifications not enabled but if the customer experiences any browser issues, this may cause the order to not update correctly. 
= I have enabled "Url Notification" but my orders are not updating? = 
Please ensure that you have reached out to our support team asking them to setup a URL Notification on your site reference, more information can be found [here](https://help.trustpayments.com/hc/en-us/articles/19827934081809-Being-written-Notifications-for-WooCommerce). 
Also ensure that our IP's have been whitelisted or any restrictions have been removed, [EU platform](https://webapp.securetrading.net/ips.html) [US platform](https://webapp.securetrading.us/ips.html). If you are still experiencing this problem, please contact our support team.
 
= How do I add Apple Pay and Google Pay? =
If you are using our hosted payment pages, this should be enabled for you already when you open an account with Trust Payments.
 
If you are using our API, you will need to enable this within your integration. More information can be found [here](https://help.trustpayments.com/hc/en-us/articles/4413290345361-Getting-started-with-WooCommerce)
 
= Why are my payments soft declining? = 
Please check that the option "3D Secure" is enabled in the plugin settings.


 
 
== Changelog ==

### version 1.1.3
- Fixed issue with Applepay not displaying in certain cases.
- Fixed issue with Pay for Order page not working with our payment methods.
- Added supported to WooCommerce plugin "All Products for Woo Subscriptions".
- Prevented JSINIT request from being submitted if the module is disabled.
- Added compatibility for WooCommerce version v9.1.2
- Added compatibility for Wordpress version v6.5.5

### version 1.1.2
- Added Account 2 Account (Pay by bank) payment type to the checkout
- Fixed issue with payment options not correctly displaying for "Customer payment page" link 
- Fixed PHP warnings
- Added compatibility for WooCommerce v8.5 and v8.6
- Fixed issue with Apple Pay where the wallet verify would not dispay in certain cases
- Added locale support
 
### version 1.1.1
- Fixed issue with notifications not being accepting with the hosted checkout flow
- Fixed issue with the API flow where the option wasn't visible to guest users
- Fixed issue with saved cards on the US platform
- Added compatibility for WordPress v6.4.1
 
### version 1.1.0
 
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
 
### version 1.0.7
 
- Resolved issue with incomplete payments showing as successful
- Declined notification now correctly shown when using a saved card
 
### version 1.0.6
 
- Fixed decline payments to correctly update order status to 'pending payment'
 
### version 1.0.5
 
- Fixed issue with saving card to customers account
 
### version 1.0.4
 
- Dont save card if walletsource=applepay/googlepay
- Add threedquery to accountcheck (saving your card on My account)
- Apdate saved card to use v3 JS library
 
### version 1.0.2
 
- ApplePay address override
- Sending notifications
- Fix: Saving your card in "Payment Method" flags invalid field
- Fix: Incorrect URL redirect
 
### version 1.0.1
 
- Fix: can't save cards when iframe is enabled
- Fix: MOTO + iframe doesn't show success page
- Add address update when using saved card
 
 
### version 1.0.0
 
- Integrate your Trust Payments account with the WooCommerce store.
- Allow customers to checkout using credit and debit cards.
- Support of Saved Card functionality, using Tokenisation for security.
- Allows admin to easily track the transaction history.
- Allows logged in customers to pay using credit/debit cards saved on their account.
- Allows logged in customers to manage their saved payment card(s) on the My Account page.
- Allows admin to perform payments from the WooCommerce admin interface (MOTO).
- PayPal, Apple Pay and Visa Checkout are supported.
- First release