# Testing OAuth Email Verification

This document explains how to verify that OAuth users are automatically verified when they sign up.

## How OAuth Verification Works

1. **User signs in via OAuth** (Google, GitHub, Microsoft)
2. **Firebase verifies the token** and extracts user info
3. **Email verification status** is checked from Firebase token claims
4. **User is created/updated** with `email_verified_at` set if email is verified
5. **User can access dashboard** immediately (no email verification required)

## Verification Logic

The `FirebaseAuthController` handles OAuth verification:

1. **New OAuth users**: Automatically verified (`email_verified_at = now()`)
2. **Existing users logging in via OAuth**: 
   - If Firebase says email is verified but user isn't verified in our system, they get verified
   - This handles cases where user registered with email/password then logs in via OAuth

## Testing Steps

### Test 1: New OAuth User

1. **Log out** if you're logged in
2. **Click OAuth button** (Google, GitHub, or Microsoft)
3. **Complete OAuth flow**
4. **Check database**:
   ```sql
   SELECT id, email, email_verified_at, firebase_uid FROM users WHERE email = 'your-email@example.com';
   ```
   - `email_verified_at` should be set (not NULL)
   - `firebase_uid` should be set

5. **Access dashboard** - Should work immediately (no verification page)

### Test 2: Existing Email/Password User Logs in via OAuth

1. **Register with email/password** (don't verify email)
2. **Log out**
3. **Log in via OAuth** with the same email
4. **Check database**:
   ```sql
   SELECT id, email, email_verified_at, firebase_uid FROM users WHERE email = 'your-email@example.com';
   ```
   - `email_verified_at` should now be set (was NULL before)
   - `firebase_uid` should be set

5. **Access dashboard** - Should work immediately

### Test 3: Verify Middleware Protection

1. **Register with email/password** (don't verify)
2. **Try to access dashboard** - Should redirect to `/verify-email`
3. **Log out**
4. **Log in via OAuth** with same email
5. **Access dashboard** - Should work (email now verified)

## Database Verification

You can check verification status directly in the database:

```sql
-- Check all users and their verification status
SELECT 
    id,
    name,
    email,
    email_verified_at,
    firebase_uid,
    CASE 
        WHEN firebase_uid IS NOT NULL THEN 'OAuth User'
        ELSE 'Email/Password User'
    END as user_type,
    CASE 
        WHEN email_verified_at IS NOT NULL THEN 'Verified'
        ELSE 'Not Verified'
    END as verification_status
FROM users
ORDER BY created_at DESC;
```

## Expected Behavior

### OAuth Users
- ✅ `email_verified_at` is set immediately
- ✅ `firebase_uid` is set
- ✅ Can access dashboard immediately
- ✅ No email verification required

### Email/Password Users
- ❌ `email_verified_at` is NULL initially
- ✅ `firebase_uid` is NULL
- ❌ Cannot access dashboard until verified
- ✅ Must verify email via link

### OAuth Users (Existing Email/Password Account)
- ✅ `email_verified_at` gets set when logging in via OAuth
- ✅ `firebase_uid` gets set
- ✅ Can access dashboard immediately after OAuth login

## Troubleshooting

### OAuth user not verified

1. **Check Firebase token claims**:
   - Add logging to see what `email_verified` claim is
   - OAuth providers should always return `email_verified: true`

2. **Check database**:
   ```sql
   SELECT * FROM users WHERE firebase_uid = 'your-firebase-uid';
   ```

3. **Check Laravel logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### User can't access dashboard after OAuth

1. **Check if user is verified**:
   ```sql
   SELECT email_verified_at FROM users WHERE id = YOUR_USER_ID;
   ```

2. **Check middleware**:
   - Dashboard route should have `verified` middleware
   - Check `routes/web.php`

3. **Check session**:
   - User might not be logged in properly
   - Check `Auth::check()` in tinker

## Code Verification

The key code is in `FirebaseAuthController::verify()`:

```php
// OAuth users are automatically verified
'email_verified_at' => $emailVerified ? now() : null,

// Existing users get verified if Firebase says email is verified
if ($emailVerified && !$user->hasVerifiedEmail()) {
    $updates['email_verified_at'] = now();
}
```

This ensures:
- New OAuth users are verified immediately
- Existing users get verified when logging in via OAuth
- Email verification status is synced with Firebase

