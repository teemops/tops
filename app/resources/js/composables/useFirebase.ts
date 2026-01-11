import { initializeApp, getApps, type FirebaseApp } from 'firebase/app';
import { 
    getAuth, 
    signInWithPopup, 
    signInWithEmailAndPassword,
    createUserWithEmailAndPassword,
    updateProfile,
    sendPasswordResetEmail,
    confirmPasswordReset,
    GoogleAuthProvider, 
    GithubAuthProvider,
    OAuthProvider,
    onAuthStateChanged,
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

// Helper to wait for auth state to initialize
export function waitForAuthState(): Promise<User | null> {
    return new Promise((resolve) => {
        if (!auth) {
            resolve(null);
            return;
        }
        
        // If user is already available, return immediately
        if (auth.currentUser) {
            resolve(auth.currentUser);
            return;
        }
        
        // Otherwise wait for auth state change
        const unsubscribe = onAuthStateChanged(auth, (user) => {
            unsubscribe();
            resolve(user);
        });
        
        // Timeout after 2 seconds to avoid hanging
        setTimeout(() => {
            unsubscribe();
            resolve(null);
        }, 2000);
    });
}

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

// Sign in with email and password
export async function signInWithEmailPassword(email: string, password: string): Promise<User> {
    if (!auth) {
        throw new Error('Firebase is not configured. Please add VITE_FIREBASE_* variables to your .env file and restart the dev server.');
    }

    try {
        const result = await signInWithEmailAndPassword(auth, email, password);
        return result.user;
    } catch (error: any) {
        // Handle specific Firebase errors
        if (error.code === 'auth/user-not-found') {
            throw new Error('No account found with this email address.');
        } else if (error.code === 'auth/wrong-password') {
            throw new Error('Incorrect password. Please try again.');
        } else if (error.code === 'auth/invalid-email') {
            throw new Error('Invalid email address.');
        } else if (error.code === 'auth/user-disabled') {
            throw new Error('This account has been disabled.');
        } else if (error.code === 'auth/too-many-requests') {
            throw new Error('Too many failed login attempts. Please try again later.');
        }
        throw error;
    }
}

// Create user with email and password
export async function createUserWithEmailPassword(email: string, password: string, name?: string): Promise<User> {
    if (!auth) {
        throw new Error('Firebase is not configured. Please add VITE_FIREBASE_* variables to your .env file and restart the dev server.');
    }

    try {
        const result = await createUserWithEmailAndPassword(auth, email, password);
        const user = result.user;
        
        // Update the user's display name if provided
        if (name && user) {
            await updateProfile(user, {
                displayName: name,
            });
            // Reload user to get updated profile
            await user.reload();
        }
        
        return user;
    } catch (error: any) {
        // Handle specific Firebase errors
        if (error.code === 'auth/email-already-in-use') {
            throw new Error('An account with this email address already exists.');
        } else if (error.code === 'auth/invalid-email') {
            throw new Error('Invalid email address.');
        } else if (error.code === 'auth/operation-not-allowed') {
            throw new Error('Email/password authentication is not enabled. Please contact support.');
        } else if (error.code === 'auth/weak-password') {
            throw new Error('Password is too weak. Please choose a stronger password.');
        }
        throw error;
    }
}

// Send password reset email
export async function sendPasswordReset(email: string): Promise<void> {
    if (!auth) {
        throw new Error('Firebase is not configured. Please add VITE_FIREBASE_* variables to your .env file and restart the dev server.');
    }

    try {
        await sendPasswordResetEmail(auth, email);
    } catch (error: any) {
        // Handle specific Firebase errors
        if (error.code === 'auth/user-not-found') {
            throw new Error('No account found with this email address.');
        } else if (error.code === 'auth/invalid-email') {
            throw new Error('Invalid email address.');
        } else if (error.code === 'auth/too-many-requests') {
            throw new Error('Too many password reset requests. Please try again later.');
        }
        throw error;
    }
}

// Confirm password reset with action code
export async function resetPasswordWithCode(actionCode: string, newPassword: string): Promise<void> {
    if (!auth) {
        throw new Error('Firebase is not configured. Please add VITE_FIREBASE_* variables to your .env file and restart the dev server.');
    }

    try {
        await confirmPasswordReset(auth, actionCode, newPassword);
    } catch (error: any) {
        // Handle specific Firebase errors
        if (error.code === 'auth/expired-action-code') {
            throw new Error('The password reset link has expired. Please request a new one.');
        } else if (error.code === 'auth/invalid-action-code') {
            throw new Error('The password reset link is invalid. Please request a new one.');
        } else if (error.code === 'auth/weak-password') {
            throw new Error('Password is too weak. Please choose a stronger password.');
        }
        throw error;
    }
}

// Update Firebase user profile (displayName, photoURL, etc.)
export async function updateFirebaseProfile(updates: { displayName?: string; photoURL?: string }): Promise<void> {
    if (!auth) {
        throw new Error('Firebase is not configured. Please add VITE_FIREBASE_* variables to your .env file and restart the dev server.');
    }

    const user = auth.currentUser;
    if (!user) {
        throw new Error('No user is currently signed in.');
    }

    try {
        await updateProfile(user, updates);
        // Reload user to get updated profile
        await user.reload();
    } catch (error: any) {
        // Handle specific Firebase errors
        if (error.code === 'auth/requires-recent-login') {
            throw new Error('Please sign out and sign back in to update your profile.');
        }
        throw error;
    }
}

// Get current user
export function getCurrentUser(): User | null {
    if (!auth) {
        return null;
    }
    return auth.currentUser;
}

