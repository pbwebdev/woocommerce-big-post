<!--

███╗   ███╗███████╗███████╗██╗  ██╗    ██╗    ██╗██╗████████╗██╗  ██╗    ██╗   ██╗███████╗
████╗ ████║██╔════╝██╔════╝██║  ██║    ██║    ██║██║╚══██╔══╝██║  ██║    ██║   ██║██╔════╝
██╔████╔██║█████╗  ███████╗███████║    ██║ █╗ ██║██║   ██║   ███████║    ██║   ██║███████╗
██║╚██╔╝██║██╔══╝  ╚════██║██╔══██║    ██║███╗██║██║   ██║   ██╔══██║    ██║   ██║╚════██║
██║ ╚═╝ ██║███████╗███████║██║  ██║    ╚███╔███╔╝██║   ██║   ██║  ██║    ╚██████╔╝███████║
╚═╝     ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝     ╚══╝╚══╝ ╚═╝   ╚═╝   ╚═╝  ╚═╝     ╚═════╝ ╚══════╝

Built by Mesh With Us
https://meshwithus.com.au

-->

# WooCommerce - Big Post Integration

Live [Big Post](https://bigpost.com.au) freight quotes at the WooCommerce cart and checkout.

The plugin adds a **Big Post** shipping method. When a customer enters an Australian address, it asks the Big Post API for quotes on the items in their cart and offers home delivery, business delivery and depot collection as shipping options.

* Contributors: Peter Bui (peter@pbwebdev.com.au)
* Tags: woocommerce, shipping, big post, freight, australia
* Requires at least: 6.5
* Tested up to: 7.1
* Requires PHP: 7.4
* WC requires at least: 8.0
* WC tested up to: 11.2
* Stable tag: 1.0.0
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

## Requirements

* WordPress 6.5 or later
* WooCommerce 8.0 or later
* PHP 7.4 or later
* A Big Post account and API token. Request a token from Big Post on 03 9544 5525 or helpdesk@bigpost.com.au.

Quotes are for deliveries within Australia only.

## Installation

1. Download this repository as a zip, or clone it into `wp-content/plugins/woocommerce-big-post`.
2. Activate **Big Post Shipping Plugin for WooCommerce** on the Plugins screen.
3. Go to **WooCommerce > Settings > Shipping > Big Post**, enter your API token and save. The plugin confirms the token with Big Post and tells you the suburb it will quote from.
4. Go to **WooCommerce > Settings > Shipping > Shipping zones**, open a zone that covers Australia and add **Big Post** as a shipping method.

## Settings

### Account and pickup (WooCommerce > Settings > Shipping > Big Post)

| Setting | What it does |
| --- | --- |
| API Token | Your Big Post API token. |
| Environment | Live, or Big Post's staging system. Staging needs its own token. |
| Pickup Location | Business name, street address, suburb, state and postcode that freight is collected from. Blank fields use the store address from WooCommerce > Settings > General. |
| Packaging Type | Carton, Skid, Pallet, Pack, Crate, Roll, Satchel, Stillage, Tube or Bag. |
| Consolidation | Lets Big Post combine the items of an order into fewer packages when it quotes. |
| Default Weight and Dimensions | Used for products that have no weight or dimensions, in kilograms and centimetres. |
| Debug Log | Logs every API request and response. |

### Per shipping zone (edit the Big Post method in a zone)

| Setting | What it does |
| --- | --- |
| Method Title | Shown to customers in front of each delivery option. |
| Delivery Types | Home delivery, Business delivery and Depot collection. |
| Deliver To | Quote for residential addresses, business addresses, or business only when the customer enters a company name. |
| Forklift | Tells Big Post that business addresses can unload with a forklift. |
| Authority To Leave | Includes services that are left without a signature. |
| Rates Shown | The cheapest carrier for each delivery type, or every carrier. |
| Carrier Name | Shows the carrier name to customers. |
| Delivery Estimate | Shows the estimated delivery date to customers. |
| Margin | An amount (`5.50`) or percentage (`10%`) added to every quote. A negative value discounts. |
| Tax Status | Taxable charges the quote excluding GST and lets WooCommerce add tax. None charges the GST inclusive quote. |

## How quotes are built

* Each cart line is sent as one item with its product name, quantity, weight and dimensions. Weights and dimensions are converted from the store's units to kilograms and centimetres. Give every product a weight and dimensions for accurate quotes.
* Virtual products are left out.
* The customer's suburb, state and postcode are matched to a Big Post locality. Their name and street address are not sent.
* For depot collection, the plugin finds the depot closest to the customer and names it in the shipping option.
* The carrier, carrier ID, service code, depot and estimated delivery date of the chosen option are saved on the order's shipping line, ready for booking the job with Big Post.
* Quotes are cached for 15 minutes, and suburb lookups for a week, to keep the checkout fast.

The plugin quotes freight. It does not book jobs in Big Post.

## Troubleshooting

Problems are logged at **WooCommerce > Status > Logs** under the `big-post` source. Turn on **Debug Log** to also record every request and response.

| Symptom | Likely cause |
| --- | --- |
| "Big Post rejected the API token" | The token is wrong, or it belongs to the other environment. |
| "Big Post has no locality matching ..." | The pickup or delivery suburb and postcode are not known to Big Post. Check the pickup location settings. |
| Big Post is not offered at checkout | The address is outside Australia, has no postcode yet, no token is saved, or Big Post returned no quote for the items. |

## Hooks

| Filter | Arguments | Use |
| --- | --- | --- |
| `big_post_shipping_rate` | `$cost, $carrier, $method` | Change the price of a rate. |
| `big_post_quote_request` | `$request, $package, $method` | Change the quote request before it is sent. |
| `big_post_quote_item` | `$item, $product, $cart_item, $method` | Change an item, for example its packaging type. Return an empty value to leave it out. |
| `big_post_buyer_is_business` | `$is_business, $package, $method` | Decide whether the customer is a business. |
| `big_post_quote_cache_ttl` | `$seconds` | How long quotes are cached. `0` turns caching off. |
| `big_post_api_timeout` | `$seconds` | API request timeout. Defaults to 20. |
| `big_post_api_url` | `$url, $environment` | Override the API host. |

## Big Post API

The plugin uses Big Post API V2, documented at https://api.bigpost.com.au/swagger/index.html. Requests are authenticated with the `AccessToken` header.

| Endpoint | Used for |
| --- | --- |
| `GET /api/suburbs` | Matching a suburb and postcode to a Big Post locality. |
| `GET /api/depots` | Finding the depot closest to the customer. |
| `POST /api/getquote` | Quoting the cart. |

## Changelog

### 1.0.0

* Rewritten for Big Post API V2. Earlier versions requested rates from Australia Post and never called Big Post.
* Home delivery, business delivery and depot collection quotes.
* Account and pickup settings moved to WooCommerce > Settings > Shipping > Big Post. Settings from 0.0.1 are not carried over.
* Margin, tax status, carrier name and delivery estimate options.
* Compatible with High-Performance Order Storage and the cart and checkout blocks.
* Removed the API key that was bundled with 0.0.1.
* Requires WordPress 6.5, WooCommerce 8.0 and PHP 7.4.

### 0.0.1

* Initial release.
