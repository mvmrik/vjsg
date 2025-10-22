import { defineStore } from 'pinia';
import axios from 'axios';

export const useGameStore = defineStore('auth', {
  state: () => ({
    user: null,
    parcels: [],
      postRegistration: null,
    isAuthenticated: false,
    loading: false,
    error: null,
    unreadNotificationsCount: 0,
    pollingInterval: null
  }),

  getters: {
    getUsername: (state) => state.user?.username || '',
    getPublicKey: (state) => state.user?.public_key || ''
  },

  actions: {
    async register(username) {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await axios.post('/register', { username });
        
        if (response.data.success) {
          // Save the raw user keys returned once by the backend so the UI can show them
          if (response.data.user) {
            this.postRegistration = response.data.user;
          }
          // Auto-login after registration
          this.isAuthenticated = true;
          localStorage.setItem('game_logged_in', 'true');
          await this.fetchUserData();
          return response.data;
        } else {
          throw new Error(response.data.message);
        }
      } catch (error) {
        this.error = error.response?.data?.message || error.message || 'Registration failed';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    clearPostRegistration() {
      this.postRegistration = null;
    },

    async login(privateKey, rememberMe = false) {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await axios.post('/login', { 
          private_key: privateKey,
          remember_me: rememberMe 
        });
        
        if (response.data.success) {
          this.isAuthenticated = true;

          // Always set local flag so UI is immediately interactive. The server will be
          // the source of truth on following requests; fetchUserData will clear the flag
          // if the server rejects the session.
          localStorage.setItem('game_logged_in', 'true');

          // For remember/me the server will set the httpOnly cookie; keep localStorage
          // as a UI-friendly fallback so menus don't stay disabled.
          await this.fetchUserData();
          // Start polling for notifications
          this.startPolling();
          return response.data;
        } else {
          throw new Error(response.data.message);
        }
      } catch (error) {
        this.error = error.response?.data?.message || error.message || 'Login failed';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async logout(skipServer = false) {
      // Stop polling
      this.stopPolling();
      
      if (!skipServer) {
        try {
          await axios.post('/logout');
        } catch (error) {
          // Log error but continue clearing client state. Server may have already
          // invalidated the session which can cause a 419 CSRF error.
          console.error('Logout error:', error);
        }
      }
      
      this.user = null;
      this.isAuthenticated = false;
      
      // Clear both localStorage and cookies
      localStorage.removeItem('game_logged_in');
      document.cookie = 'game_logged_in=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
      document.cookie = 'game_private_key=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    },

    async fetchUserData() {
      try {
        const response = await axios.get('/api/user-data');
        if (response.data.success) {
          this.user = response.data.user;
          // Emit event to set user language
          if (this.user.locale) {
            window.dispatchEvent(new CustomEvent('set-user-language', { 
              detail: { locale: this.user.locale } 
            }));
          }
          // Also fetch unread notifications count
          await this.fetchUnreadNotificationsCount();
        }
      } catch (error) {
        console.error('Failed to fetch user data:', error);
        // If server reports unauthenticated, clear local auth flags
        if (error.response && error.response.status === 401) {
          this.user = null;
          this.isAuthenticated = false;
          localStorage.removeItem('game_logged_in');
        }
      }
    },

    // Parcels
    async fetchParcels() {
      try {
        const res = await axios.get('/api/parcels');
        if (res.data.success) {
          this.parcels = res.data.parcels;
          return res.data.parcels;
        }
      } catch (e) {
        console.error('Failed to fetch parcels', e);
      }
      return [];
    },

    async claimParcel(lat, lng) {
      try {
        // First call to check balance and get price
        const initialRes = await axios.post('/api/parcels/claim', { lat, lng });
        if (!initialRes.data.success) {
          // Not enough balance or other error
          return initialRes.data;
        }
        
        if (initialRes.data.needsConfirm) {
          // Emit event to show confirm dialog
          return new Promise((resolve) => {
            window.dispatchEvent(new CustomEvent('show-confirm-dialog', { 
              detail: { 
                message: initialRes.data.message,
                onConfirm: async () => {
                  try {
                    // Second call with confirmed
                    const confirmRes = await axios.post('/api/parcels/claim', { lat, lng, confirmed: true });
                    resolve(confirmRes.data);
                  } catch (e) {
                    resolve({ success: false, message: e.response?.data?.message || 'Claim failed' });
                  }
                },
                onCancel: () => {
                  resolve({ success: false, message: 'Claim cancelled' });
                }
              }
            }));
          });
        }
        return initialRes.data;
      } catch (e) {
        // Handle 400 errors as normal responses (insufficient balance, etc.)
        if (e.response && e.response.status === 400) {
          return e.response.data;
        }
        console.error('Claim failed', e);
        throw e;
      }
    },



    async checkAuthStatus() {
        // Try to fetch user data — if server session or remember cookie is valid,
        // the API will return user data. This avoids storing raw private keys on client.
        try {
          const res = await axios.get('/api/user-data');
          if (res.data && res.data.success) {
            this.user = res.data.user;
            this.isAuthenticated = true;
            await this.fetchUnreadNotificationsCount();
            this.startPolling();
            return;
          }
        } catch (e) {
          // Ignore - user not authenticated server-side
        }

        // Fallback to localStorage (session-based)
        const isLoggedIn = localStorage.getItem('game_logged_in') === 'true';
        if (isLoggedIn) {
          this.isAuthenticated = true;
          await this.fetchUserData();
          // Start polling for notifications
          this.startPolling();
        }
    },

    async fetchUnreadNotificationsCount() {
      try {
        const response = await axios.get('/api/notifications/unread-count');
        if (response.data.success) {
          const oldCount = this.unreadNotificationsCount;
          this.unreadNotificationsCount = response.data.count;
          
          // If count increased, show toast with latest notification
          if (this.unreadNotificationsCount > oldCount && this.unreadNotificationsCount > 0) {
            await this.showLatestNotificationToast();
          }
        }
      } catch (error) {
        console.error('Failed to fetch unread notifications count:', error);
      }
    },

    async fetchLatestUnreadNotification() {
      try {
        const response = await axios.get('/api/notifications/latest-unread');
        if (response.data.success && response.data.notification) {
          return response.data.notification;
        }
      } catch (error) {
        console.error('Failed to fetch latest unread notification:', error);
      }
      return null;
    },

    async showLatestNotificationToast() {
      const notification = await this.fetchLatestUnreadNotification();
      if (notification) {
        // Emit event to show toast (will be handled in GameApp.vue)
        window.dispatchEvent(new CustomEvent('show-notification-toast', { 
          detail: notification 
        }));
      }
    },

    startPolling() {
      // Stop existing polling if any
      this.stopPolling();
      
      // Start polling every 30 seconds
      this.pollingInterval = setInterval(async () => {
        if (this.isAuthenticated) {
          // Check for server-side revocation and update notifications
          await this.checkAuthStatus();
          await this.fetchUnreadNotificationsCount();
        }
      }, 30000); // 30 seconds
    },

    stopPolling() {
      if (this.pollingInterval) {
        clearInterval(this.pollingInterval);
        this.pollingInterval = null;
      }
    }
  }
});