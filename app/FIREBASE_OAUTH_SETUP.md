# Firebase Auth OAuth Setup Guide

This guide explains how to configure OAuth providers in Firebase Authentication for the Teemops application.

## Overview

The application uses **Firebase Authentication** to handle OAuth providers (Google, GitHub, Microsoft). This approach is simpler and more secure than using Laravel Socialite because:

- ✅ Single authentication system (Firebase)
- ✅ Frontend handles OAuth flow securely
- ✅ Backend just verifies Firebase tokens (already implemented)
- ✅ No need for OAuth provider credentials in Laravel
- ✅ Better security (tokens handled by Firebase)

## How It Works

1. **User clicks OAuth button** on login/register page
2. **Firebase Auth handles OAuth** - Opens provider's login page
3. **User authorizes** - Firebase gets OAuth token
4. **Frontend gets Firebase ID token** - After successful OAuth
5. **Frontend sends token to Laravel** - POST to `/auth/firebase/verify`
6. **Laravel verifies token** - Uses existing Firebase middleware
7. **Laravel creates session** - User is logged in
8. **User redirected to dashboard**

## Firebase Console Setup

### 1. Enable OAuth Providers

Go to [Firebase Console](https://console.firebase.google.com/) → Your Project → **Authentication** → **Sign-in method**

#### Google Provider
1. Click on **Google**
2. Enable the provider
3. Add your project's support email
4. Save

**No additional configuration needed** - Firebase handles Google OAuth automatically.

#### GitHub Provider
1. Click on **GitHub**
2. Enable the provider
3. You'll need to create a GitHub OAuth App:
   - Go to [GitHub Developer Settings](https://github.com/settings/developers)
   - Click **New OAuth App**
   - **Application name**: Teemops
   - **Homepage URL**: `https://yourdomain.com` (or `http://localhost:8000` for dev)
   - **Authorization callback URL**: `https://YOUR_PROJECT_ID.firebaseapp.com/__/auth/handler`
   - Copy the **Client ID** and **Client Secret**
4. Paste them into Firebase Console
5. Save

#### Microsoft Provider
1. Click on **Microsoft**
2. Enable the provider
3. You'll need to register an app in Azure:
   - Go to [Azure Portal](https://portal.azure.com/)
   - Navigate to **Azure Active Directory** → **App registrations**
   - Click **New registration**
   - **Name**: Teemops
   - **Supported account types**: Accounts in any organizational directory and personal Microsoft accounts
   - **Redirect URI**: `https://YOUR_PROJECT_ID.firebaseapp.com/__/auth/handler` (Web platform)
   - Copy the **Application (client) ID** and create a **Client secret**
4. Paste them into Firebase Console
5. Save

### 2. Configure Authorized Domains

Go to **Authentication** → **Settings** → **Authorized domains**

Add your domains:
- `localhost` (for development)
- `yourdomain.com` (for production)
- `yourdomain.firebaseapp.com` (Firebase hosting domain)

## Frontend Configuration

Add Firebase configuration to your `.env` file:

```env
VITE_FIREBASE_API_KEY=your-api-key
VITE_FIREBASE_AUTH_DOMAIN=your-project-id.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=your-project-id
VITE_FIREBASE_STORAGE_BUCKET=your-project-id.appspot.com
VITE_FIREBASE_MESSAGING_SENDER_ID=your-sender-id
VITE_FIREBASE_APP_ID=your-app-id
```

**Where to find these values:**
1. Go to Firebase Console → Project Settings
2. Scroll down to "Your apps"
3. Click the web icon (`</>`) or create a new web app
4. Copy the configuration values

## Backend Configuration

The backend is already configured! The `VerifyFirebaseToken` middleware handles token verification.

Make sure your `.env` has Firebase service account credentials:

```env
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY_ID=your-private-key-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=your-service-account@project-id.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=your-client-id
FIREBASE_CLIENT_X509_CERT_URL=https://www.googleapis.com/robot/v1/metadata/x509/...
```

## Testing

1. **Start your development server:**
   ```bash
   php artisan serve
   npm run dev
   ```

2. **Navigate to login page:** `http://localhost:8000/login`

3. **Click an OAuth provider button** (Google, GitHub, or Microsoft)

4. **Complete OAuth authorization**

5. **You should be redirected to dashboard** after successful authentication

## Troubleshooting

### "Firebase: Error (auth/configuration-not-found)"
- Check that Firebase config is in `.env` with `VITE_` prefix
- Restart your dev server after adding env variables
- Verify all Firebase config values are correct

### "Firebase: Error (auth/unauthorized-domain)"
- Add your domain to Firebase Console → Authentication → Settings → Authorized domains
- For localhost, make sure `localhost` is in the list

### "Authentication failed" after OAuth
- Check Firebase service account credentials in Laravel `.env`
- Verify the token is being sent correctly (check browser network tab)
- Check Laravel logs: `storage/logs/laravel.log`

### OAuth provider not working
- **Google**: Should work automatically after enabling
- **GitHub**: Verify callback URL matches exactly: `https://YOUR_PROJECT_ID.firebaseapp.com/__/auth/handler`
- **Microsoft**: Verify callback URL and tenant configuration

## Benefits Over Laravel Socialite

1. **No OAuth credentials in Laravel** - All handled by Firebase
2. **Simpler setup** - Just enable providers in Firebase Console
3. **Better security** - Tokens verified by Firebase, not stored in Laravel
4. **Consistent auth** - Same Firebase tokens for API and web
5. **Less code** - No need for OAuth callback routes in Laravel

## Migration from Laravel Socialite

If you previously set up Laravel Socialite, you can:

1. **Remove Socialite routes** (already done - replaced with Firebase route)
2. **Remove OAuth provider credentials** from `config/services.php` (optional)
3. **Remove Laravel Socialite package** (optional):
   ```bash
   composer remove laravel/socialite
   ```

The OAuth controller (`OAuthController.php`) is no longer needed but can be kept for reference.

## Next Steps

1. ✅ Enable OAuth providers in Firebase Console
2. ✅ Add Firebase config to frontend `.env`
3. ✅ Test OAuth flow
4. ✅ Configure production domains
5. ✅ Update authorized domains for production

## Additional Resources

- [Firebase Authentication Documentation](https://firebase.google.com/docs/auth)
- [Firebase OAuth Providers](https://firebase.google.com/docs/auth/web/federated-auth)
- [Firebase Console](https://console.firebase.google.com/)

