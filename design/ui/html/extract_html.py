#!/usr/bin/env python3
"""
Extract HTML mockups from markdown design files and create standalone HTML files.
"""
import re
import os
from pathlib import Path

# Base directory
BASE_DIR = Path(__file__).parent.parent
HTML_DIR = Path(__file__).parent

# Mapping of markdown files to HTML output paths
FILE_MAPPINGS = {
    'auth/login.md': 'auth/login.html',
    'auth/login-otp.md': 'auth/login-otp.html',
    'auth/register.md': 'auth/register.html',
    'auth/password-reset.md': 'auth/password-reset.html',
    'auth/email-verify.md': 'auth/email-verify.html',
    'app/dashboard.md': 'app/dashboard.html',
    'app/organizations/list.md': 'app/organizations/list.html',
    'app/organizations/create.md': 'app/organizations/create.html',
    'app/organizations/settings.md': 'app/organizations/settings.html',
    'app/aws-accounts/list.md': 'app/aws-accounts/list.html',
    'app/aws-accounts/add-step1.md': 'app/aws-accounts/add-step1.html',
    'app/aws-accounts/add-step2.md': 'app/aws-accounts/add-step2.html',
    'app/aws-accounts/status-pending.md': 'app/aws-accounts/status-pending.html',
    'app/scans/list.md': 'app/scans/list.html',
    'app/scans/detail.md': 'app/scans/detail.html',
    'app/reports/view.md': 'app/reports/view.html',
    'app/insights/analytics.md': 'app/insights/analytics.html',
    'app/settings/profile.md': 'app/settings/profile.html',
    'app/settings/profile-mfa-enabled.md': 'app/settings/profile-mfa-enabled.html',
    'app/settings/mfa-setup.md': 'app/settings/mfa-setup.html',
    'app/settings/mfa-setup-verify.md': 'app/settings/mfa-setup-verify.html',
    'app/settings/mfa-remove.md': 'app/settings/mfa-remove.html',
    'app/settings/appearance.md': 'app/settings/appearance.html',
}

def extract_html_from_markdown(md_file_path):
    """Extract HTML code block from markdown file."""
    try:
        with open(md_file_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Find HTML code blocks (```html ... ```)
        pattern = r'```html\n(.*?)```'
        matches = re.findall(pattern, content, re.DOTALL)
        
        if matches:
            # Return the first (and usually only) HTML block
            html = matches[0].strip()
            # Fix the JSX-style syntax in some files
            html = html.replace("{' '}", " ")
            return html
        return None
    except Exception as e:
        print(f"Error reading {md_file_path}: {e}")
        return None

def wrap_partial_html(html_content, title):
    """Wrap partial HTML in a full page structure."""
    if html_content.strip().startswith('<!DOCTYPE'):
        return html_content
    
    # Check if it's a modal/dialog
    is_modal = 'fixed z-10 inset-0' in html_content or 'Dialog/Modal' in html_content
    
    if is_modal:
        # Wrap modal in a full page
        return f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{title} - Teemops</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
  <div class="min-h-screen py-12 px-4">
    <div class="max-w-7xl mx-auto">
      <div class="mb-4">
        <a href="../index.html" class="text-blue-600 hover:text-blue-500 dark:text-blue-400">← Back to Index</a>
      </div>
      {html_content}
    </div>
  </div>
</body>
</html>"""
    else:
        # Wrap other partial content
        return f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{title} - Teemops</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
  <div class="min-h-screen py-12 px-4">
    <div class="max-w-7xl mx-auto">
      <div class="mb-4">
        <a href="../index.html" class="text-blue-600 hover:text-blue-500 dark:text-blue-400">← Back to Index</a>
      </div>
      {html_content}
    </div>
  </div>
</body>
</html>"""

def add_navigation_link(html_content, relative_path):
    """Add navigation link to full HTML pages."""
    if not html_content.strip().startswith('<!DOCTYPE'):
        return html_content
    
    # Calculate relative path to index
    depth = relative_path.count('/')
    back_path = '../' * depth + 'index.html'
    
    # Try to insert navigation after body tag
    body_pattern = r'(<body[^>]*>)'
    nav_html = f'\\1\n  <div class="fixed top-4 left-4 z-50"><a href="{back_path}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">← Back to Index</a></div>'
    
    html_content = re.sub(body_pattern, nav_html, html_content, count=1)
    return html_content

def create_html_file(html_content, output_path, title):
    """Create standalone HTML file."""
    output_file = HTML_DIR / output_path
    output_file.parent.mkdir(parents=True, exist_ok=True)
    
    # Wrap if needed and add navigation
    html_content = wrap_partial_html(html_content, title)
    html_content = add_navigation_link(html_content, output_path)
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(html_content)
    print(f"Created: {output_path}")

def get_title_from_path(path):
    """Generate a title from file path."""
    name = Path(path).stem
    # Convert kebab-case to Title Case
    return ' '.join(word.capitalize() for word in name.split('-'))

def main():
    """Main extraction process."""
    for md_path, html_path in FILE_MAPPINGS.items():
        full_md_path = BASE_DIR / md_path
        if not full_md_path.exists():
            print(f"Warning: {md_path} not found, skipping...")
            continue
        
        html_content = extract_html_from_markdown(full_md_path)
        if html_content:
            title = get_title_from_path(html_path)
            create_html_file(html_content, html_path, title)
        else:
            print(f"Warning: No HTML found in {md_path}")

if __name__ == '__main__':
    main()
