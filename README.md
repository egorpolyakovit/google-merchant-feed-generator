# Google Merchant Feed Generator

**PHP + JSON → Google Merchant Center RSS 2.0 XML**

A small, dependency-free CLI feed generator inspired by a production B2B industrial product catalog. Designed to illustrate safe product eligibility rules rather than to bypass Google Merchant Center policy checks.

## Features

- Reads existing JSON product catalogs without changing their structure.
- Emits Google-namespaced RSS 2.0 XML (`g:id`, `g:title`, `g:description`, `g:link`, `g:image_link`, `g:price`, `g:availability`, `g:brand`, `g:mpn`, `g:product_type`).
- Includes up to five additional product images, when available.
- Uses stable, URL-derived product identifiers and deduplicates by product URL.
- Filters out nonpositive or nonfixed prices, products sold on request, made-to-order items, and entries without locally available images.
- Uses configurable store name, domain, currency, brand, source JSON paths and public image directory.
- Produces a static XML file suitable for periodic publication over HTTPS or as a file upload.

## Quick start

Requirements: **PHP 7.1+** (CLI), and Python 3 for the optional test.

```bash
php generate.php
python3 tests/check_feed.py
```

Output: [`examples/google-products.xml`](examples/google-products.xml).

Use a custom configuration:

```bash
php generate.php --config=config.demo.json --output=examples/google-products.xml
```

The included catalog, illustrations, URLs and brand are **fictitious demonstration data**. `example.com` is not a live storefront: these XML items are not intended for submission to Google Merchant Center.

## Configuration

[`config.demo.json`](config.demo.json):

```json
{
  "store_name": "Sample Industrial Store",
  "base_url": "https://example.com",
  "currency": "RUB",
  "default_brand": "ExampleWorks",
  "public_root": "public",
  "catalog_files": ["data/products.json"]
}
```

Paths are resolved relative to the configuration file. Production integration requires publicly accessible product pages, image URLs, and accurate price and availability on the landing pages.

## Input mapping

| Input JSON | Google feed | Notes |
| --- | --- | --- |
| `item_title` | `g:title` | Plain text, max. 150 characters |
| `short_description` | `g:description` | HTML removed |
| `item_path` | `g:link` | `/catalog/` prefix added |
| `main_photo` | `g:image_link` | Must be an existing file under `public_root` |
| `dop_photo_1…5` | `g:additional_image_link` | Existing files only |
| `item_price` | `g:price` | Positive fixed price only |
| `is_available` | `g:availability` | Only explicit Russian `В наличии` |
| `articul` | `g:mpn` | Optional |
| `category`, `subcategory` | `g:product_type` | Optional |
| `brand` | `g:brand` | Falls back to config default |

## Eligibility & limitations

This intentionally conservative example **does not** support made-to-order products, `preorder`, `backorder`, regional availability or complex variants. An `от 55 000 ₽` starting price is not treated as a fixed sale price. Excluded products remain in the input data, but are absent from the export.

The generator validates only local file presence, **not** public website reachability, Google policy eligibility, GTINs, tax, shipping, landing-page price parity, account configuration, or Merchant Center acceptance. No approval or traffic outcome is guaranteed.

For real catalogs, check Google Merchant Center's current [product data specification](https://support.google.com/merchants/answer/7052112) and merchant eligibility requirements.

## Architecture

```text
JSON catalog(s) + config
          |
          v
  Normalize and filter
          |
          v
  Check local images
          |
          v
    Deduplicate / ID
          |
          v
 Google RSS 2.0 XML
          |
          v
HTTPS hosting or merchant upload
```

## Security

No API keys or credentials are required. Do **not** commit SFTP passwords, merchant account credentials, customer information, or proprietary source data. Do not expose the CLI generator itself as a public HTTP endpoint without additional controls.

## License

MIT. Demo data and generated illustrations are included only as example project assets.
