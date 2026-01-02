// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2024-04-03',
  devtools: { enabled: true },
  ssr: false, // SPA mode - no server-side rendering
  modules: [
    '@pinia/nuxt',
    'nuxt-vuefire',
  ],
  css: [
    'primeicons/primeicons.css',
    '~/assets/main.css'
  ],
  
  // VueFire configuration - handles Firebase initialization automatically
  vuefire: {
    config: {
      apiKey: process.env.NUXT_PUBLIC_FIREBASE_API_KEY || '',
      authDomain: process.env.NUXT_PUBLIC_FIREBASE_AUTH_DOMAIN || '',
      projectId: process.env.NUXT_PUBLIC_FIREBASE_PROJECT_ID || '',
      storageBucket: process.env.NUXT_PUBLIC_FIREBASE_STORAGE_BUCKET || '',
      messagingSenderId: process.env.NUXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID || '',
      appId: process.env.NUXT_PUBLIC_FIREBASE_APP_ID || '',
    },
    auth: {
      enabled: true,
    },
  },

  runtimeConfig: {
    public: {
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || 'http://localhost:3001/api',
    }
  }
})