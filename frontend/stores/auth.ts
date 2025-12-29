import { defineStore } from 'pinia';
import { 
  signInWithEmailAndPassword, 
  createUserWithEmailAndPassword,
  signInWithPopup,
  GoogleAuthProvider,
  signOut as firebaseSignOut,
  onAuthStateChanged,
  type User
} from 'firebase/auth';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    loading: true,
    error: null as string | null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    userEmail: (state) => state.user?.email || null,
    userName: (state) => state.user?.displayName || null,
  },

  actions: {
    async initialize() {
      const { $auth } = useNuxtApp();
      
      return new Promise<void>((resolve) => {
        onAuthStateChanged($auth, (user) => {
          this.user = user;
          this.loading = false;
          resolve();
        });
      });
    },

    async signIn(email: string, password: string) {
      try {
        this.error = null;
        const { $auth } = useNuxtApp();
        await signInWithEmailAndPassword($auth, email, password);
      } catch (error: any) {
        this.error = error.message;
        throw error;
      }
    },

    async signUp(email: string, password: string, name?: string) {
      try {
        this.error = null;
        const { $auth } = useNuxtApp();
        const userCredential = await createUserWithEmailAndPassword($auth, email, password);
        
        if (name && userCredential.user) {
          // Update display name if provided
          // Note: This would require additional Firebase setup
        }
        
        return userCredential.user;
      } catch (error: any) {
        this.error = error.message;
        throw error;
      }
    },

    async signInWithGoogle() {
      try {
        this.error = null;
        const { $auth } = useNuxtApp();
        const provider = new GoogleAuthProvider();
        await signInWithPopup($auth, provider);
      } catch (error: any) {
        this.error = error.message;
        throw error;
      }
    },

    async signOut() {
      try {
        this.error = null;
        const { $auth } = useNuxtApp();
        await firebaseSignOut($auth);
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

