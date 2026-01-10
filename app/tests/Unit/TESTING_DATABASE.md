# Testing Database Configuration

## Why SQLite for Tests?

Laravel tests typically use SQLite (in-memory) instead of MySQL for several reasons:

### Advantages of SQLite for Tests

1. **Speed**: In-memory database (`:memory:`) is extremely fast - no disk I/O
2. **No Setup Required**: No need to create/manage a separate MySQL test database
3. **Isolation**: Each test gets a completely fresh database automatically
4. **No Cleanup**: Database is automatically destroyed after tests complete
5. **Portability**: Tests can run anywhere without MySQL server requirements
6. **CI/CD Friendly**: Easier to set up in CI pipelines (no MySQL service needed)

### Trade-offs

- **Different Database Engine**: Some MySQL-specific features might behave differently
- **Type Differences**: Some data types or constraints might differ slightly
- **Performance Characteristics**: Query performance characteristics differ

## Using MySQL for Tests Instead

If you prefer to use MySQL for tests to match production more closely, you can configure it:

### Option 1: Update phpunit.xml

Change the database connection in `phpunit.xml`:

```xml
<php>
    <env name="DB_CONNECTION" value="mysql"/>
    <env name="DB_DATABASE" value="laravel_test"/>
    <env name="DB_HOST" value="127.0.0.1"/>
    <env name="DB_PORT" value="3306"/>
    <env name="DB_USERNAME" value="root"/>
    <env name="DB_PASSWORD" value=""/>
</php>
```

### Option 2: Use .env.testing

Create a `.env.testing` file:

```env
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_test
DB_USERNAME=root
DB_PASSWORD=
```

Then update `phpunit.xml` to use it:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <server name="APP_ENV" value="testing"/>
</php>
```

### Option 3: Environment Variable Override

Set environment variables when running tests:

```bash
DB_CONNECTION=mysql DB_DATABASE=laravel_test php artisan test
```

## Setting Up MySQL Test Database

If you choose MySQL, create a dedicated test database:

```sql
CREATE DATABASE laravel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Or use a script:

```bash
mysql -u root -p -e "CREATE DATABASE laravel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## Recommendation

**For most cases, SQLite is recommended** because:
- Tests should be fast and isolated
- Most Laravel code is database-agnostic
- You can always add MySQL-specific integration tests separately

**Use MySQL for tests when**:
- You're using MySQL-specific features (JSON functions, full-text search, etc.)
- You want to test exact production behavior
- You're testing database performance
- You have complex stored procedures or triggers

## Hybrid Approach

You can use both:
- **Unit tests**: Use SQLite (fast, isolated)
- **Integration tests**: Use MySQL (matches production)

Configure different test suites in `phpunit.xml`:

```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Integration">
        <directory>tests/Integration</directory>
    </testsuite>
</testsuites>
```

Then use different database configs per suite, or use `@group` annotations.

## Current Configuration

Currently, tests are configured to use SQLite in-memory database via `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

This means:
- ✅ No MySQL server needed for tests
- ✅ Tests run very fast
- ✅ Each test gets a fresh database
- ⚠️ Requires PHP SQLite extension (`php-sqlite3`)

## Switching to MySQL

If you want to switch to MySQL, simply update `phpunit.xml` as shown in Option 1 above, and ensure you have a MySQL test database set up.
