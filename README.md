# TABEER SPORTZ

Responsive Next.js catalogue frontend with a lightweight, headless WordPress CMS. The site includes Home, Products, 13 product detail pages, About, Contact, responsive navigation, dark mode, catalogue search and filtering, WhatsApp chat, and quote inquiries. Product prices are intentionally hidden.

## Run the frontend locally

```bash
npm install
npm run dev
```

Open `http://localhost:3000`.

Set the WordPress API URL in `.env.local`:

```env
WORDPRESS_API_URL=http://tabeer-sportz.local/wp-json/tabeer/v1
```

For production, replace the local address with the public HTTPS WordPress URL and rebuild the frontend.

## WordPress CMS

The plugin source is in `wordpress/tabeer-sportz-core`, and the ready-to-upload package is `wordpress/tabeer-sportz-core.zip`.

In WordPress:

1. Install and activate **TABEER SPORTZ Core**.
2. Use **Products** to add or edit catalogue items.
3. Use **Products → Catalogue Settings** for the business phone, WhatsApp, email, and address.
4. Use **Products → Demo Catalogue** once on a local or staging site to seed the 13 starter products. Running it again updates those products without making duplicates.
5. Review customer submissions under **Quote Requests**.

The frontend reads WordPress first and uses `lib/products.ts` as a temporary fallback if WordPress is unavailable.

## REST API

- `GET /wp-json/tabeer/v1/products`
- `GET /wp-json/tabeer/v1/products/{slug}`
- `GET /wp-json/tabeer/v1/settings`
- `POST /wp-json/tabeer/v1/inquiries`

The product endpoints return catalogue and manufacturing data without prices. Inquiry submissions are proxied by the Next.js route at `/api/inquiries` so the browser does not need to call WordPress directly.

## Brand assets

- Logo: `public/logo.png`
- Hero image: `public/images/tabeer-hero.png`
- Business phone and WhatsApp: `+92 335 3631555`

See `wordpress/README.md` for plugin details and production notes.
"# tsportz" 
