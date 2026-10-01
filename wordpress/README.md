# TABEER SPORTZ WordPress CMS

`tabeer-sportz-core` is the installable WordPress plugin for the headless catalogue.

## Local installation

1. Copy `tabeer-sportz-core` to `wp-content/plugins/`.
2. Activate **TABEER SPORTZ Core** in WordPress.
3. Open **Products → Catalogue Settings** and enter the public business details.
4. On a local or staging site, open **Products → Demo Catalogue** and create the 13 starter products.
5. Add real product photography through each product's main photo and gallery fields.

The demo action is explicit and idempotent. It never runs automatically and refreshes matching demo products by slug instead of creating duplicates.

## Public API

- `GET /wp-json/tabeer/v1/products`
- `GET /wp-json/tabeer/v1/products/{slug}`
- `GET /wp-json/tabeer/v1/settings`
- `POST /wp-json/tabeer/v1/inquiries`

Product filters include `search`, `category`, `sport`, `subtype`, `featured`, `page`, and `per_page`.

An inquiry accepts JSON fields `name`, `email`, `phone`, `company`, `country`, `message`, `product_id`, `quantity`, plus an empty `website` honeypot. Name, valid email, and message are required. Successful submissions appear under **Quote Requests** in WordPress.

## Production

Prices are disabled and excluded from the TABEER API. Before publishing, configure the notification mailbox, replace demo copy and images, use HTTPS, and limit the allowed frontend origin at the web server or WordPress layer if the API is exposed beyond the production frontend.
