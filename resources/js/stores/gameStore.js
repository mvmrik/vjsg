import { defineStore } from 'pinia';
import axios from 'axios';

export const useGameStore = defineStore('auth', {
  state: () => ({
    user: null,
    parcels: [],
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
          
          // Use cookies for remember me, localStorage for session
          if (rememberMe) {
            // Set cookie that expires in 30 days
            document.cookie = 'game_logged_in=true; path=/; max-age=' + (30 * 24 * 60 * 60);
            document.cookie = 'game_private_key=' + encodeURIComponent(privateKey) + '; path=/; max-age=' + (30 * 24 * 60 * 60);
          } else {
            localStorage.setItem('game_logged_in', 'true');
          }
          
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

    async logout() {
      // Stop polling
      this.stopPolling();
      
      try {
        await axios.post('/logout');
      } catch (error) {
        console.error('Logout error:', error);
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
          // Also fetch unread notifications count
          await this.fetchUnreadNotificationsCount();
        }
      } catch (error) {
        console.error('Failed to fetch user data:', error);
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

    async claimParcel(x, y) {
      try {
        const res = await axios.post('/api/parcels/claim', { x, y });
        return res.data;
      } catch (e) {
        console.error('Claim failed', e);
        throw e;
      }
    },



    async checkAuthStatus() {
      // Check cookies first (remember me)
      const cookies = document.cookie.split(';').reduce((acc, cookie) => {
        const [key, value] = cookie.trim().split('=');
        acc[key] = decodeURIComponent(value || '');
        return acc;
      }, {});
      
      if (cookies.game_logged_in === 'true' && cookies.game_private_key) {
        // Auto-login with remembered private key
        try {
          await this.login(cookies.game_private_key, true);
        } catch (error) {
          console.error('Auto-login failed:', error);
          // Clear invalid cookies
          document.cookie = 'game_logged_in=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
          document.cookie = 'game_private_key=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        }
        return;
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