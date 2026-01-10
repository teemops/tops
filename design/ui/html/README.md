# Teemops UI Design Mockups - HTML Preview

This folder contains standalone HTML files extracted from the design markdown files. These can be opened directly in a browser to preview the designs.

## Viewing the Designs

1. **Start with the index**: Open `index.html` in your browser to see all available designs
2. **Navigate**: Click on any design card to view the full mockup
3. **Back navigation**: Use the "← Back to Index" link in the top-left corner of each page

## File Structure

```
html/
├── index.html                    # Navigation index page
├── auth/                        # Authentication pages
│   ├── login.html
│   ├── register.html
│   ├── password-reset.html
│   └── email-verify.html
├── app/                         # Application pages
│   ├── dashboard.html
│   ├── organizations/
│   ├── aws-accounts/
│   ├── scans/
│   ├── reports/
│   ├── insights/
│   └── settings/
└── extract_html.py              # Script to regenerate HTML files
```

## Regenerating HTML Files

If you update the markdown design files, you can regenerate the HTML files by running:

```bash
cd design/ui/html
python3 extract_html.py
```

## Notes

- All HTML files use Tailwind CSS via CDN
- Dark mode is supported (toggle via browser/system settings)
- Some pages show partial content (modals, components) wrapped in a full page structure
- Logo images reference `/logo.png` - you may need to update paths for local viewing

## Browser Compatibility

These designs work best in modern browsers:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)

## Design System

For full design system documentation, see `../README.md`

