# Koko Payment Gateway for Ultimate POS

Allows you to accept installments based payments on your Ultimate POS store via Koko accounts.

## Installation

1. Create a folder named `Koko` in the `Modules` directory of your Ultimate POS installation.
2. Upload the module files into the `Modules/Koko` directory.
3. Go to **Settings > Modules** in Ultimate POS and install the Koko module.
4. Go to **Koko > Settings** to configure your API credentials.

## Configuration

You will need the following credentials from your Koko merchant dashboard:
- **Merchant ID**
- **API Key**
- **Public Key** (for webhook signature verification)
- **Private Key** (for signing payment requests)

## Sandbox Mode

You can enable Sandbox mode in the settings to test payments using Koko's staging environment.

## License

GPLv2 or later

![CodeRabbit Pull Request Reviews](https://img.shields.io/coderabbit/prs/github/cshawanaS/Paykoko-ultimatepos?utm_source=oss&utm_medium=github&utm_campaign=cshawanaS%2FPaykoko-ultimatepos&labelColor=171717&color=FF570A&link=https%3A%2F%2Fcoderabbit.ai&label=CodeRabbit+Reviews)
