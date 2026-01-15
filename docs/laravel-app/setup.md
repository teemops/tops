# Laravel Starter Kit Vue Setup Guide

## PHP Installation

### Check Current PHP Version
```bash
php -v
```

### Install PHP 8.3 (Recommended)

#### Ubuntu/Debian:
```bash
sudo apt update
sudo apt install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.3 php8.3-cli php8.3-common php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring php8.3-curl php8.3-xml php8.3-bcmath
```

#### macOS (Homebrew):
```bash
brew install php@8.3
brew link php@8.3
```

#### Verify Installation:
```bash
php -v
# Should show PHP 8.3.x
```

### Install Composer

```bash
# Download and install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

## Required PHP Extensions

Verify all required extensions are installed:
```bash
php -m | grep -E "bcmath|ctype|curl|dom|fileinfo|json|mbstring|openssl|pcre|pdo|tokenizer|xml"
```

## Next Steps

Once PHP and Composer are installed, run:
```bash
cd /home/ben/dev/saas/app
# The setup script will be run automatically
```

## Laravel Starter Kit Vue Installation

We'll use the official Laravel Starter Kit with Vue:
- Laravel 11.x (requires PHP 8.2+)
- Vue 3 with TypeScript
- Inertia.js 2
- shadcn-vue components
- Tailwind CSS v4

### Quick Install

Once PHP 8.2+ and Composer are installed:

```bash
cd /home/ben/dev/saas/app
./install-deps.sh
```

This will:
1. Create a new Laravel project
2. Install the official Vue Starter Kit
3. Set up all dependencies
4. Configure the project structure

