# Unit Tests for ProcessSqsMessages Command

This directory contains unit tests for the `ProcessSqsMessages` command that polls SQS queues for CloudFormation messages.

## Test Coverage

The test suite covers the following scenarios:

### Configuration Tests
- ✅ Command fails when SQS configuration is missing
- ⏭️ Command fails when queue URL cannot be retrieved (skipped - requires integration test)

### Create Request Tests
- ✅ Process Create request successfully (updates account to completed status)
- ✅ Process Create request when account is not found (sends FAILED response)
- ✅ Process Create request with missing required fields (sends FAILED response)
- ✅ Process Create request with invalid Role ARN format (sends FAILED response)

### Delete Request Tests
- ✅ Process Delete request successfully (soft deletes account)

### Error Handling Tests
- ✅ Process invalid message format (gracefully handles and deletes from queue)

## Running Tests

### Prerequisites

**Option 1: Use SQLite (Default - Recommended)**
1. **Install SQLite PHP extension** (required for in-memory database):
   ```bash
   # Ubuntu/Debian
   sudo apt-get install php-sqlite3
   
   # Or if using PHP 8.2+
   sudo apt-get install php8.2-sqlite3
   ```

**Option 2: Use MySQL (Alternative)**
1. **Create a test database**:
   ```sql
   CREATE DATABASE laravel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Update `phpunit.xml`** to use MySQL instead of SQLite:
   ```xml
   <env name="DB_CONNECTION" value="mysql"/>
   <env name="DB_DATABASE" value="laravel_test"/>
   ```

See [TESTING_DATABASE.md](./TESTING_DATABASE.md) for more details on why SQLite is used and how to switch to MySQL.

2. **Ensure database migrations are up to date**:
   ```bash
   php artisan migrate
   ```

### Run All Tests

```bash
php artisan test --filter ProcessSqsMessagesTest
```

### Run Specific Test

```bash
php artisan test --filter ProcessSqsMessagesTest::test_process_create_request_successfully
```

### Run with Coverage

```bash
php artisan test --filter ProcessSqsMessagesTest --coverage
```

## Test Structure

### Factories

The tests use Laravel factories to create test data:

- `OrganizationFactory` - Creates test organizations
- `AwsAccountFactory` - Creates test AWS accounts with various states:
  - `pending()` - Account in pending state
  - `completed()` - Account in completed state
  - `error()` - Account in error state

### Mocks

The tests mock the following external dependencies:

1. **SQS Client** (`Aws\Sqs\SqsClient`):
   - `getQueueUrl()` - Retrieves queue URL
   - `receiveMessage()` - Receives messages from queue
   - `deleteMessage()` - Deletes processed messages

2. **HTTP Client** (`Illuminate\Support\Facades\Http`):
   - CloudFormation ResponseURL requests
   - Verifies SUCCESS/FAILED responses are sent correctly

### Test Data

Tests use sample message formats from `references/samples/`:
- `sample-create-sns-message.json` - Create request format
- `sample-delete-sns-message.json` - Delete request format

## What Each Test Verifies

### `test_process_create_request_successfully`
- ✅ Account status changes from `pending` to `completed`
- ✅ AWS Account ID is extracted from Role ARN and stored
- ✅ IAM Role ARN is stored (encrypted)
- ✅ Account name is updated if it was "Pending AWS Account"
- ✅ SUCCESS response is sent to CloudFormation ResponseURL
- ✅ Message is deleted from SQS queue

### `test_process_delete_request_successfully`
- ✅ Account is soft deleted from database
- ✅ SUCCESS response is sent to CloudFormation ResponseURL
- ✅ Message is deleted from SQS queue

### `test_process_create_request_when_account_not_found`
- ✅ FAILED response is sent to CloudFormation with appropriate error message
- ✅ Message is still deleted from queue (prevents reprocessing)

### `test_process_create_request_with_missing_fields`
- ✅ FAILED response is sent when required fields are missing
- ✅ Message is deleted from queue

### `test_process_create_request_with_invalid_role_arn`
- ✅ FAILED response is sent when Role ARN format is invalid
- ✅ Account remains in pending state (not updated)

### `test_process_invalid_message_format`
- ✅ Invalid JSON is handled gracefully
- ✅ Message is deleted from queue

## Integration Tests

For full end-to-end testing, consider creating integration tests that:
- Use actual SQS queue (or LocalStack)
- Test the full command execution flow
- Verify queue polling behavior
- Test error recovery and retry logic

## Troubleshooting

### SQLite Driver Not Found

If you see `could not find driver (Connection: sqlite)`, install the SQLite PHP extension:

```bash
sudo apt-get install php-sqlite3
# Or for specific PHP version
sudo apt-get install php8.2-sqlite3
```

### Factory Not Found

If factories are not found, ensure they're in the correct namespace:
- `Database\Factories\OrganizationFactory`
- `Database\Factories\AwsAccountFactory`

And that models use the `HasFactory` trait.

### Mockery Issues

If Mockery mocks are not working correctly:
- Ensure `Mockery::close()` is called in `tearDown()`
- Check that mocks are set up before method invocation
- Verify method signatures match exactly

## Related Documentation

- [SQS Polling Service Setup](../../docs/laravel-app/SQS_POLLING_SERVICE.md)
- [AWS Account Management](../../docs/features/features-spec.md)
- [Sample Messages](../../references/samples/README.md)
