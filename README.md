# Custom Payment Gateway for Easy Digital Downloads

## Overview

This WordPress plugin adds a custom payment gateway to **Easy Digital Downloads (EDD)**. It allows users to integrate their own payment processor into EDD by handling custom API interactions and settings.

## Features

- Custom payment gateway integration for EDD
- Option to set API keys and secret keys via the EDD settings page
- Payment status updates (pending, complete, failed) based on API responses
- Seamless checkout experience for customers

## Requirements

- WordPress 5.0 or higher
- Easy Digital Downloads 2.9 or higher
- PHP 7.0 or higher

## Installation

1. Download the plugin files and upload them to your WordPress installation under the `wp-content/plugins/` directory, or install directly through the WordPress plugin installer.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Downloads > Settings > Payment Gateways** and configure your custom payment gateway by entering your API Key and Secret Key.

## Usage

1. Once the plugin is activated, go to **Downloads > Settings > Payment Gateways** in your WordPress admin panel.
2. Select the "Custom Gateway" option in the list of available gateways.
3. Configure your API Key and Secret Key (provided by your payment processor).
4. When a customer checks out using this payment method, the plugin sends the payment data to your gateway and processes the payment accordingly.

## Code Structure

- `custom-edd-gateway.php` - The main plugin file that registers the gateway and processes payments.
- Functions include:
  - `custom_edd_register_gateway()` – Registers the custom gateway with EDD.
  - `custom_edd_process_payment()` – Handles the payment processing, including interacting with the custom payment API.
  - `custom_edd_gateway_settings()` – Adds custom fields to the EDD settings page for API configuration.

## Example

You can modify the `custom_edd_process_payment()` function to integrate with your payment processor's API. Here's a simplified flow of how the payment processing works:

1. **Payment Data**: Collect data from the EDD checkout.
2. **API Interaction**: Send the data to the custom payment processor's API.
3. **Payment Status**: Based on the API response, update the payment status to `complete`, `pending`, or `failed` in the EDD system.
4. **Success Page**: Redirect the user to the EDD success page if the payment is successful.

## Customization

You can extend or customize the plugin for specific payment processors by editing the API interaction in the `custom_edd_process_payment()` function. Add custom API endpoints, additional security features, or custom error handling based on your payment gateway's requirements.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

If you encounter any issues or have questions about integrating your custom payment gateway, feel free to open an issue on GitHub.