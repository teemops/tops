# Marketing Site Deployment

## Quick Deploy

This is a static site. Deploy the `html/` folder to any static host:
- **S3 + CloudFront** — Upload contents of `html/` to an S3 bucket, configure CloudFront
- **Netlify** — Connect repo, set publish directory to `design/marketing/web/html`
- **Vercel** — Same as Netlify
- **GitHub Pages** — Point to `design/marketing/web/html`
- **Cloudflare Pages** — Connect repo, set build output to `design/marketing/web/html`

## URLs

| Link | URL |
|------|-----|
| App | `https://app.teem.nz` |
| Contact | `mailto:hello@teem.nz` |

Docs link has been removed for now. To add it back, add `https://docs.teem.nz` (or similar) to the nav in index.html, features.html, and pricing.html.

## File Structure

```
html/
├── index.html      # Homepage
├── features.html   # Features page
├── pricing.html    # Pricing page
└── logo.png       # Brand logo — run design/brand/build-teemops-logo.py (transparent PNG)
```

## SEO (Optional)

- Add `sitemap.xml` listing all pages
- Add `robots.txt`
- Add Open Graph meta tags for social sharing
- Add JSON-LD schema (Organization, Product)
