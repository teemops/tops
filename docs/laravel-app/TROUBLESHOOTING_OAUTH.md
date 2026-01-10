# Troubleshooting OAuth (Firebase Auth)

If the OAuth buttons aren't working, follow these steps:

## 1. Check Browser Console

Open your browser's developer console (F12) and look for errors when clicking the OAuth button.

Common errors:
- `Firebase configuration is missing` → See step 2
- `auth/unauthorized-domain` → See step 3
- `auth/operation-not-allowed` → See step 4
- `auth/popup-blocked` → See step 5

## 2. Verify Firebase Configuration

Check that your `.env` file has all Firebase frontend variables:

```env
VITE_FIREBASE_API_KEY=your-api-key
VITE_FIREBASE_AUTH_DOMAIN=your-project-id.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=your-project-id
VITE_FIREBASE_STORAGE_BUCKET=your-project-id.appspot.com
VITE_FIREBASE_MESSAGING_SENDER_ID=your-sender-id
VITE_FIREBASE_APP_ID=your-app-id
```

**Important:** After adding/updating these variables:
1. **Stop your dev server** (Ctrl+C)
2. **Restart it** with `npm run dev`
3. **Hard refresh** your browser (Ctrl+Shift+R or Cmd+Shift+R)

Vite only reads environment variables at startup, so changes require a restart.

## 3. Check Authorized Domains

Go to [Firebase Console](https://console.firebase.google.com/) → Your Project → **Authentication** → **Settings** → **Authorized domains**

Make sure `localhost` is in the list for development.

For production, add your domain.

## 4. Enable OAuth Providers

Go to Firebase Console → **Authentication** → **Sign-in method**

Enable the providers you want to use:
- ✅ **Google** - Just enable it (no additional config needed)
- ✅ **GitHub** - Enable and add Client ID/Secret
- ✅ **Microsoft** - Enable and add Client ID/Secret

## 5. Check Popup Blockers

Firebase Auth uses popups for OAuth. Make sure:
- Your browser allows popups for `localhost:8000`
- No browser extensions are blocking popups
- Try in an incognito/private window

## 6. Check Network Tab

Open browser DevTools → **Network** tab → Click OAuth button

Look for:
- Request to `/auth/firebase/verify` - Should return 302 redirect
- Any 401/403/500 errors
- Check the response for error messages

## 7. Check Laravel Logs

If backend verification fails, check Laravel logs:

```bash
tail -f storage/logs/laravel.log
```

Look for Firebase-related errors.

## 8. Verify Route Exists

Check that the route is registered:

```bash
php artisan route:list | grep firebase
```

Should show:
```
POST  auth/firebase/verify  firebase.verify
```

## 9. Test Firebase Initialization

Add this to your browser console on the login/register page:

```javascript
// Check if Firebase is initialized
console.log('Firebase config:', {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY ? 'Set' : 'Missing',
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN ? 'Set' : 'Missing',
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID ? 'Set' : 'Missing',
});
```

If any show "Missing", the env vars aren't being loaded.

## 10. Common Issues

### Button does nothing when clicked
- Check browser console for JavaScript errors
- Verify the button has `@click="handleOAuth('google')"` handler
- Check that Vue is properly mounted

### "Firebase is not configured" error
- Restart dev server after adding env vars
- Check `.env` file has `VITE_` prefix
- Verify no typos in variable names

### Popup opens then immediately closes
- Check authorized domains in Firebase Console
- Verify OAuth provider is enabled
- Check browser popup blocker settings

### "Authentication failed" after OAuth
- Check Laravel logs for backend errors
- Verify Firebase service account credentials in `.env`
- Check that `firebase.verify` route is accessible

## Still Not Working?

1. **Check all console logs** - Both browser and Laravel
2. **Verify Firebase project** - Make sure you're using the correct project
3. **Test with a different provider** - Try Google first (easiest setup)
4. **Check Firebase Console** - Verify providers are enabled and configured
5. **Restart everything** - Dev server, browser, clear cache

## Quick Test

To test if Firebase is working at all, open browser console and run:

```javascript
import { signInWithOAuth } from '@/composables/useFirebase';
signInWithOAuth('google').then(user => console.log('Success!', user)).catch(err => console.error('Error:', err));
```

If this works, the issue is with the button handler. If it doesn't, the issue is with Firebase configuration.

