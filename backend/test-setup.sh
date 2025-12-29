#!/bin/bash

echo "🧪 Testing TeemOps Backend Setup"
echo "================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Check if .env exists
echo "1. Checking environment file..."
if [ -f .env ]; then
    echo -e "${GREEN}✓${NC} .env file exists"
    if grep -q "DATABASE_URL" .env; then
        echo -e "${GREEN}✓${NC} DATABASE_URL is set"
    else
        echo -e "${RED}✗${NC} DATABASE_URL is missing from .env"
    fi
else
    echo -e "${RED}✗${NC} .env file not found"
    echo "   Create one from .env.example"
fi
echo ""

# Test 2: Check if dependencies are installed
echo "2. Checking dependencies..."
if [ -d "node_modules" ]; then
    echo -e "${GREEN}✓${NC} node_modules exists"
else
    echo -e "${YELLOW}⚠${NC} node_modules not found - run 'npm install'"
fi
echo ""

# Test 3: Check if Prisma client is generated
echo "3. Checking Prisma client..."
if [ -d "node_modules/.prisma/client" ]; then
    echo -e "${GREEN}✓${NC} Prisma client generated"
else
    echo -e "${YELLOW}⚠${NC} Prisma client not generated - run 'npm run prisma:generate'"
fi
echo ""

# Test 4: TypeScript compilation
echo "4. Testing TypeScript compilation..."
if npm run build > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} TypeScript compilation successful"
else
    echo -e "${RED}✗${NC} TypeScript compilation failed"
    npm run build
fi
echo ""

# Test 5: Running unit tests
echo "5. Running unit tests..."
if npm test -- --passWithNoTests > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} All unit tests passed"
else
    echo -e "${RED}✗${NC} Some tests failed"
    npm test -- --passWithNoTests
fi
echo ""

# Test 6: Check if database connection string is valid format
echo "6. Checking DATABASE_URL format..."
if [ -f .env ]; then
    DB_URL=$(grep "DATABASE_URL" .env | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    if [[ $DB_URL == mysql://* ]]; then
        echo -e "${GREEN}✓${NC} DATABASE_URL format looks correct"
    else
        echo -e "${YELLOW}⚠${NC} DATABASE_URL format may be incorrect (should start with mysql://)"
    fi
fi
echo ""

echo "================================"
echo "✅ Setup test complete!"
echo ""
echo "Next steps:"
echo "  1. Ensure MySQL is running (docker-compose up -d mysql)"
echo "  2. Run migrations: npm run prisma:migrate"
echo "  3. Start dev server: npm run start:dev"
echo "  4. Test API: curl http://localhost:3000/api/health"

