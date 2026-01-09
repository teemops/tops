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

// Initialize Firebase
let app: FirebaseApp;
if (getApps().length === 0) {
    app = initializeApp(firebaseConfig);
} else {
    app = getApps()[0];
}

// Get Auth instance
export const auth = getAuth(app);

// OAuth Providers
export const googleProvider = new GoogleAuthProvider();
export const githubProvider = new GithubAuthProvider();
export const microsoftProvider = new OAuthProvider('microsoft.com');

// Sign in with OAuth provider
export async function signInWithOAuth(provider: 'google' | 'github' | 'microsoft'): Promise<User> {
    let selectedProvider;
    
    switch (provider) {
        case 'google':
            selectedProvider = googleProvider;
            break;
        case 'github':
            selectedProvider = githubProvider;
            break;
        case 'microsoft':
            selectedProvider = microsoftProvider;
            break;
        default:
            throw new Error(`Unsupported provider: ${provider}`);
    }

    const result = await signInWithPopup(auth, selectedProvider);
    return result.user;
}

// Get current user's ID token
export async function getIdToken(): Promise<string | null> {
    const user = auth.currentUser;
    if (!user) {
        return null;
    }
    return await user.getIdToken();
}

// Get current user
export function getCurrentUser(): User | null {
    return auth.currentUser;
}

