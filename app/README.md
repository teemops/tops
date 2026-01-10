# Teemops Laravel Application

This directory contains the Laravel application built with the official Vue Starter Kit.

## Prerequisites

Before setting up, ensure you have:

1. **PHP 8.2 or higher** (8.3 recommended)
   ```bash
   php -v  # Should show PHP 8.2.x or higher
   ```

2. **Composer** (PHP dependency manager)
   ```bash
   composer --version
   ```

3. **Node.js 18+** and npm
   ```bash
   node -v
   npm -v
   ```

## Installation

### Step 1: Install PHP and Composer

Follow the instructions in [Setup Guide](../docs/laravel-app/setup.md) to install PHP 8.2+ and Composer.

### Step 2: Run Setup Script

Once PHP and Composer are installed:

```bash
cd /home/ben/dev/saas/app
./setup-laravel.sh
```

The script will:
- ✅ Verify PHP version and extensions
- ✅ Create Laravel project
- ✅ Install Vue Starter Kit
- ✅ Install all dependencies
- ✅ Set up basic configuration

### Step 3: Configure Environment

Edit `.env` file with your database and application settings:

```bash
cp .env.example .env
php artisan key:generate
# Edit .env with your database credentials
```

### Step 4: Run Migrations

```bash
php artisan migrate
```

### Step 5: Start Development Servers

Terminal 1 (Laravel):
```bash
php artisan serve
```

Terminal 2 (Vite):
```bash
npm run dev
```

Visit: http://localhost:8000

## Project Structure

```
app/
├── app/                    # Laravel application code
├── resources/
│   ├── js/                # Vue components and pages
│   │   ├── Layouts/       # Layout components (Auth, App)
│   │   └── Pages/         # Inertia pages
│   └── css/               # Styles
├── routes/                # Laravel routes
├── database/              # Migrations and seeders
└── public/                # Public assets
```

## Design System

The UI designs are in `../design/ui/` and are compatible with:
- Laravel Starter Kit Vue layouts
- shadcn-vue components
- Tailwind CSS v4

## Documentation

For detailed documentation, see:
- [Documentation Index](../docs/README.md) - Complete documentation index
- [Laravel App Docs](../docs/laravel-app/README.md) - Laravel-specific documentation
- [Quick Reference](./DOCS.md) - Quick links to all docs

## Next Steps

After initial setup:
1. Review UI designs in `../design/ui/html/`
2. Configure environment variables (see [ENV_SETUP.md](../docs/laravel-app/ENV_SETUP.md))
3. Set up Firebase OAuth (see [FIREBASE_OAUTH_SETUP.md](../docs/laravel-app/FIREBASE_OAUTH_SETUP.md))
4. Review feature specifications (see [features-spec.md](../docs/features/features-spec.md))

