# OAuth Setup Guide (DEPRECATED - Use Firebase Auth Instead)

> **Note:** This guide is for Laravel Socialite OAuth, which has been replaced with Firebase Authentication. 
> **Please use [FIREBASE_OAUTH_SETUP.md](./FIREBASE_OAUTH_SETUP.md) instead.**

---

## Why Firebase Auth?

Firebase Authentication is now used for OAuth because:
- ✅ Single authentication system (Firebase)
- ✅ No OAuth credentials needed in Laravel
- ✅ Better security (tokens handled by Firebase)
- ✅ Simpler setup (just enable in Firebase Console)
- ✅ Consistent with existing Firebase setup

---

# OAuth Setup Guide (Legacy - Laravel Socialite)

This guide explains how to configure OAuth providers (Google, GitHub, Microsoft) for authentication in the Teemops application using Laravel Socialite.

## Overview

The application supports OAuth authentication via Laravel Socialite for:
- **Google** - Sign in with Google
- **GitHub** - Sign in with GitHub  
- **Microsoft** - Sign in with Microsoft (Azure AD)

Users can register or log in using their OAuth provider accounts. On first login, a user account and default organization are automatically created.

## Configuration

### 1. Google OAuth Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the **Google+ API**
4. Go to **Credentials** → **Create Credentials** → **OAuth client ID**
5. Choose **Web application**
6. Add authorized redirect URIs:
   - `http://localhost:8000/oauth/google/callback` (development)
   - `https://yourdomain.com/oauth/google/callback` (production)
7. Copy the **Client ID** and **Client Secret**

Add to `.env`:
```env
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/oauth/google/callback
```

### 2. GitHub OAuth Setup

1. Go to [GitHub Developer Settings](https://github.com/settings/developers)
2. Click **New OAuth App**
3. Fill in:
   - **Application name**: Teemops
   - **Homepage URL**: `http://localhost:8000` (or your production URL)
   - **Authorization callback URL**: `http://localhost:8000/oauth/github/callback`
4. Click **Register application**
5. Copy the **Client ID** and generate a **Client Secret**

Add to `.env`:
```env
GITHUB_CLIENT_ID=your-github-client-id
GITHUB_CLIENT_SECRET=your-github-client-secret
GITHUB_REDIRECT_URI=http://localhost:8000/oauth/github/callback
```

### 3. Microsoft OAuth Setup

1. Go to [Azure Portal](https://portal.azure.com/)
2. Navigate to **Azure Active Directory** → **App registrations**
3. Click **New registration**
4. Fill in:
   - **Name**: Teemops
   - **Supported account types**: Accounts in any organizational directory and personal Microsoft accounts
   - **Redirect URI**: `http://localhost:8000/oauth/microsoft/callback` (Web platform)
5. Click **Register**
6. Go to **Certificates & secrets** → **New client secret**
7. Copy the **Application (client) ID** and **Client secret value**

Add to `.env`:
```env
MICROSOFT_CLIENT_ID=your-microsoft-client-id
MICROSOFT_CLIENT_SECRET=your-microsoft-client-secret
MICROSOFT_REDIRECT_URI=http://localhost:8000/oauth/microsoft/callback
MICROSOFT_TENANT_ID=common
```

**Note:** `MICROSOFT_TENANT_ID` can be:
- `common` - Any Microsoft account (personal or work/school)
- `organizations` - Work/school accounts only
- `consumers` - Personal Microsoft accounts only
- `{tenant-id}` - Specific Azure AD tenant

## Routes

OAuth routes are automatically registered:

- **Redirect**: `GET /oauth/{provider}` - Redirects to OAuth provider
- **Callback**: `GET /oauth/{provider}/callback` - Handles OAuth callback

Supported providers: `google`, `github`, `microsoft`

## How It Works

### Registration/Login Flow

1. User clicks OAuth button on login/register page
2. User is redirected to OAuth provider (Google/GitHub/Microsoft)
3. User authorizes the application
4. OAuth provider redirects back to `/oauth/{provider}/callback`
5. Application:
   - Retrieves user information from OAuth provider
   - Finds or creates user account by email
   - Creates default organization if user is new
   - Logs user in
   - Redirects to dashboard

### User Creation

When a new user signs in via OAuth:
- User account is created with:
  - Email (from OAuth provider)
  - Name (from OAuth provider)
  - `email_verified_at` set to current time
  - `firebase_uid` stores the OAuth provider ID (for compatibility)
- Default organization is automatically created:
  - Name: "{User's Name}'s Organization"
  - `is_default`: true

### Existing Users

If a user with the same email already exists:
- User is logged in
- User information is updated if changed
- No new organization is created

## Security Considerations

1. **HTTPS in Production**: Always use HTTPS for OAuth callbacks in production
2. **State Parameter**: Laravel Socialite automatically handles CSRF protection via state parameter
3. **Email Verification**: OAuth users are automatically marked as email verified
4. **Provider Validation**: Only supported providers are allowed (validated in controller)

## Testing

### Test OAuth Flow

1. Start your development server:
   ```bash
   php artisan serve
   ```

2. Navigate to login page: `http://localhost:8000/login`

3. Click an OAuth provider button (Google, GitHub, or Microsoft)

4. Complete OAuth authorization

5. You should be redirected back and logged in

### Troubleshooting

**Error: "Invalid credentials"**
- Check that client ID and secret are correct in `.env`
- Verify redirect URI matches exactly in OAuth provider settings

**Error: "Redirect URI mismatch"**
- Ensure redirect URI in `.env` matches the one configured in OAuth provider
- Check for trailing slashes or protocol differences (http vs https)

**Error: "Provider not supported"**
- Only `google`, `github`, and `microsoft` are supported
- Check the provider name in the URL

**Microsoft-specific issues:**
- Ensure `MICROSOFT_TENANT_ID` is set correctly
- For organization-only access, use the specific tenant ID

## Environment Variables Summary

```env
# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=

# GitHub OAuth
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=

# Microsoft OAuth
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_REDIRECT_URI=
MICROSOFT_TENANT_ID=common
```

## Production Checklist

- [ ] Update all redirect URIs to production URLs
- [ ] Use HTTPS for all OAuth callbacks
- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Verify OAuth provider settings match production URLs
- [ ] Test all three OAuth providers
- [ ] Review OAuth provider security settings
- [ ] Set up proper error logging and monitoring

## Additional Resources

- [Laravel Socialite Documentation](https://laravel.com/docs/socialite)
- [Google OAuth Documentation](https://developers.google.com/identity/protocols/oauth2)
- [GitHub OAuth Documentation](https://docs.github.com/en/apps/oauth-apps)
- [Microsoft Identity Platform Documentation](https://learn.microsoft.com/en-us/azure/active-directory/develop/)

