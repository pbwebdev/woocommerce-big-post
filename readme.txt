=== WooCommerce - BigPost Integration ===
Contributors: Peter Bui
Tags: woocommerce, shipping, big post, freight, australia
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Live Big Post freight quotes at the WooCommerce cart and checkout.

== Description ==

Adds a Big Post shipping method to WooCommerce. When a customer enters an Australian address, the plugin asks the Big Post API for quotes on the items in their cart and offers home delivery, business delivery and depot collection as shipping options.

* Quotes from Big Post API V2 for home delivery, business delivery and depot collection.
* Shows the cheapest carrier for each delivery type, or every carrier.
* Names the depot closest to the customer for depot collection.
* Adds a fixed or percentage margin to quotes.
* Saves the carrier, service code, depot and estimated delivery date on the order's shipping line.
* Works with shipping zones, the cart and checkout blocks and High-Performance Order Storage.

You need a Big Post account and API token. Request a token from Big Post on 03 9544 5525 or helpdesk@bigpost.com.au.

Quotes are for deliveries within Australia only. The plugin quotes freight. It does not book jobs in Big Post.

== Installation ==

1. Upload the plugin to `wp-content/plugins/woocommerce-big-post` and activate it on the Plugins screen.
2. Go to WooCommerce > Settings > Shipping > Big Post, enter your API token and save. The plugin confirms the token with Big Post and tells you the suburb it will quote from.
3. Go to WooCommerce > Settings > Shipping > Shipping zones, open a zone that covers Australia and add Big Post as a shipping method.

Blank pickup location fields use the store address from WooCommerce > Settings > General.

== Frequently Asked Questions ==

= Where do I get an API token? =

From Big Post, on 03 9544 5525 or helpdesk@bigpost.com.au.

= Why is Big Post not offered at checkout? =

The address is outside Australia, has no postcode yet, no token is saved, or Big Post returned no quote for the items. Problems are logged at WooCommerce > Status > Logs under the big-post source.

= What does "Big Post rejected the API token" mean? =

The token is wrong, or it belongs to the other environment. Staging needs its own token.

= What units are used? =

Product weights and dimensions are converted from the store's units to kilograms and centimetres. Give every product a weight and dimensions for accurate quotes.

= What is sent to Big Post? =

The pickup location, the customer's suburb, state and postcode, and the name, quantity, weight and dimensions of each item. The customer's name and street address are not sent.

== Changelog ==

= 1.0.0 =
* Rewritten for Big Post API V2. Earlier versions requested rates from Australia Post and never called Big Post.
* Home delivery, business delivery and depot collection quotes.
* Account and pickup settings moved to WooCommerce > Settings > Shipping > Big Post. Settings from 0.0.1 are not carried over.
* Margin, tax status, carrier name and delivery estimate options.
* Compatible with High-Performance Order Storage and the cart and checkout blocks.
* Removed the API key that was bundled with 0.0.1.
* Requires WordPress 6.5, WooCommerce 8.0 and PHP 7.4.

= 0.0.1 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Settings from 0.0.1 are not carried over. Enter your Big Post API token at WooCommerce > Settings > Shipping > Big Post after updating.
