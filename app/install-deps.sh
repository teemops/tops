#!/bin/bash
# Setup script for Laravel Starter Kit Vue application

set -e

echo "🚀 Setting up Laravel Starter Kit Vue..."

# Check PHP version
echo "📋 Checking PHP version..."
PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
REQUIRED_VERSION="8.2"

if [ "$(printf '%s\n' "$REQUIRED_VERSION" "$PHP_VERSION" | sort -V | head -n1)" != "$REQUIRED_VERSION" ]; then
    echo "❌ PHP 8.2 or higher is required. Current version: $PHP_VERSION"
    exit 1
fi

echo "✅ PHP version: $(php -v | head -n 1)"

# Check Composer
echo "📋 Checking Composer..."
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer first."
    exit 1
fi

echo "✅ Composer version: $(composer --version | head -n 1)"

# Check required PHP extensions
echo "📋 Checking PHP extensions..."
REQUIRED_EXTENSIONS=("bcmath" "ctype" "curl" "dom" "fileinfo" "json" "mbstring" "openssl" "pcre" "pdo" "tokenizer" "xml")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m | grep -q "$ext"; then
        MISSING_EXTENSIONS+=("$ext")
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -ne 0 ]; then
    echo "❌ Missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
    echo "Please install them using: sudo apt install php8.3-${MISSING_EXTENSIONS[0]} (or similar)"
    exit 1
fi

echo "✅ All required PHP extensions are installed"

# Install dependencies
echo "📦 Installing PHP dependencies..."
composer install

echo "📦 Installing Node.js dependencies..."
# Use --legacy-peer-deps to handle dependency conflicts
npm install --legacy-peer-deps

# Build assets (skip if node_modules doesn't exist or build fails)
echo "🏗️  Building assets..."
if [ -d "node_modules" ]; then
    npm run build || echo "⚠️  Build failed, but continuing. You can run 'npm run build' manually later."
else
    echo "⚠️  node_modules not found, skipping build. Run 'npm install --legacy-peer-deps' first."
fi

# Generate application key if needed
if [ ! -f ".env" ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
    php artisan key:generate
fi

echo ""
echo "✅ Laravel Starter Kit Vue setup complete!"
echo ""
echo "Next steps:"
echo "1. Configure your .env file with database credentials"
echo "2. Run migrations: php artisan migrate"
echo "3. Start the development server: php artisan serve"
echo "4. In another terminal, start Vite: npm run dev"
echo ""
echo "The application will be available at: http://localhost:8000"

