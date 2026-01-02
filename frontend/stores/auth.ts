import { defineStore } from 'pinia';
import { 
  signInWithEmailAndPassword, 
  createUserWithEmailAndPassword,
  signInWithPopup,
  GoogleAuthProvider,
  signOut as firebaseSignOut,
  onAuthStateChanged,
  type User,
  type Auth
} from 'firebase/auth';

// Store the auth instance once obtained
let authInstance: Auth | null = null;

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    loading: true,
    error: null as string | null,
    _initialized: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    userEmail: (state) => state.user?.email || null,
    userName: (state) => state.user?.displayName || null,
  },

  actions: {
    // Set the auth instance from a composable context
    setAuthInstance(auth: Auth) {
      authInstance = auth;
    },

    // Initialize auth state listener
    async initialize() {
      if (this._initialized) {
        return;
      }

      // If we don't have auth instance yet, wait for it
      if (!authInstance) {
        // Wait up to 5 seconds for auth to be set
        await new Promise<void>((resolve) => {
          const checkInterval = setInterval(() => {
            if (authInstance) {
              clearInterval(checkInterval);
              resolve();
            }
          }, 100);
          
          setTimeout(() => {
            clearInterval(checkInterval);
            resolve();
          }, 5000);
        });
      }

      if (!authInstance) {
        console.error('Firebase auth not available after waiting');
        this.loading = false;
        return;
      }

      this._initialized = true;

      // Listen for auth state changes
      return new Promise<void>((resolve) => {
        onAuthStateChanged(authInstance!, (user) => {
          this.user = user;
          if (this.loading) {
            this.loading = false;
            resolve();
          }
        });
      });
    },

    async signIn(email: string, password: string) {
      if (!authInstance) {
        throw new Error('Firebase auth not available');
      }
      try {
        this.error = null;
        const result = await signInWithEmailAndPassword(authInstance, email, password);
        this.user = result.user;
      } catch (error: any) {
        this.error = error.message;
        throw error;
      }
    },

    async signUp(email: string, password: string, name?: string) {
      if (!authInstance) {
        throw new Error('Firebase auth not available');
      }
      try {
        this.error = null;
        const userCredential = await createUserWithEmailAndPassword(authInstance, email, password);
        
        if (name && userCredential.user) {
          // Update display name if provided
          // Note: This would require additional Firebase setup
        }
        
        this.user = userCredential.user;
        return userCredential.user;
      } catch (error: any) {
        this.error = error.message;
        throw error;
      }
    },

    async signInWithGoogle() {
      if (!authInstance) {
        throw new Error('Firebase auth not available');
      }
      try {
        this.error = null;
        const provider = new GoogleAuthProvider();
        
        // Sign in with popup
        const result = await signInWithPopup(authInstance, provider);
        
        // Update store state immediately
        this.user = result.user;
        
        return result.user;
      } catch (error: any) {
        this.error = error.message;
        throw error;
      }
    },

    async signOut() {
      if (!authInstance) {
        throw new Error('Firebase auth not available');
      }
      try {
        this.error = null;
        await firebaseSignOut(authInstance);
        this.user = null;
      } catch (error: any) {
        this.error = error.message;
        throw error;
      }
    },

    async getIdToken() {
      if (!this.user) return null;
      return await this.user.getIdToken();
    },
  },
});
