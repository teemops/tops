import { initializeApp, getApps, type FirebaseApp } from 'firebase/app';
import { 
    getAuth, 
    signInWithPopup, 
    GoogleAuthProvider, 
    GithubAuthProvider,
    OAuthProvider,
    type Auth,
    type User
} from 'firebase/auth';

// Firebase configuration - should match your Firebase project
const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

// Validate Firebase configuration
const isFirebaseConfigured = () => {
    return !!(
        firebaseConfig.apiKey &&
        firebaseConfig.authDomain &&
        firebaseConfig.projectId &&
        firebaseConfig.storageBucket &&
        firebaseConfig.messagingSenderId &&
        firebaseConfig.appId
    );
};

// Initialize Firebase
let app: FirebaseApp | null = null;
let auth: Auth | null = null;

try {
    if (!isFirebaseConfigured()) {
        console.error('Firebase configuration is missing. Please add VITE_FIREBASE_* variables to your .env file.');
    } else {
        if (getApps().length === 0) {
            app = initializeApp(firebaseConfig);
        } else {
            app = getApps()[0];
        }
        auth = getAuth(app);
    }
} catch (error) {
    console.error('Failed to initialize Firebase:', error);
}

// Export auth instance (may be null if not configured)
export { auth };

// OAuth Providers - created lazily when needed
let googleProvider: GoogleAuthProvider | null = null;
let githubProvider: GithubAuthProvider | null = null;
let microsoftProvider: OAuthProvider | null = null;

function getGoogleProvider(): GoogleAuthProvider {
    if (!googleProvider) {
        googleProvider = new GoogleAuthProvider();
    }
    return googleProvider;
}

function getGithubProvider(): GithubAuthProvider {
    if (!githubProvider) {
        githubProvider = new GithubAuthProvider();
    }
    return githubProvider;
}

function getMicrosoftProvider(): OAuthProvider {
    if (!microsoftProvider) {
        microsoftProvider = new OAuthProvider('microsoft.com');
    }
    return microsoftProvider;
}

// Sign in with OAuth provider
export async function signInWithOAuth(provider: 'google' | 'github' | 'microsoft'): Promise<User> {
    if (!auth) {
        throw new Error('Firebase is not configured. Please add VITE_FIREBASE_* variables to your .env file and restart the dev server.');
    }

    let selectedProvider;
    
    switch (provider) {
        case 'google':
            selectedProvider = getGoogleProvider();
            break;
        case 'github':
            selectedProvider = getGithubProvider();
            break;
        case 'microsoft':
            selectedProvider = getMicrosoftProvider();
            break;
        default:
            throw new Error(`Unsupported provider: ${provider}`);
    }

    try {
        const result = await signInWithPopup(auth, selectedProvider);
        return result.user;
    } catch (error: any) {
        // Handle specific Firebase errors
        if (error.code === 'auth/popup-closed-by-user') {
            throw new Error('Sign-in popup was closed. Please try again.');
        } else if (error.code === 'auth/unauthorized-domain') {
            throw new Error('This domain is not authorized. Please add it to Firebase Console.');
        } else if (error.code === 'auth/configuration-not-found') {
            throw new Error('Firebase configuration not found. Please check your .env file.');
        } else if (error.code === 'auth/operation-not-allowed') {
            throw new Error(`${provider} sign-in is not enabled. Please enable it in Firebase Console.`);
        }
        throw error;
    }
}

// Get current user's ID token
export async function getIdToken(): Promise<string | null> {
    if (!auth) {
        throw new Error('Firebase is not configured.');
    }
    const user = auth.currentUser;
    if (!user) {
        return null;
    }
    return await user.getIdToken();
}

// Get current user
export function getCurrentUser(): User | null {
    if (!auth) {
        return null;
    }
    return auth.currentUser;
}

