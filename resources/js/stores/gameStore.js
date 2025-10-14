import { defineStore } from 'pinia';
import axios from 'axios';

export const useGameStore = defineStore('auth', {
  state: () => ({
    user: null,
    parcels: [],
    isAuthenticated: false,
    loading: false,
    error: null
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

    async login(privateKey) {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await axios.post('/login', { private_key: privateKey });
        
        if (response.data.success) {
          this.isAuthenticated = true;
          localStorage.setItem('game_logged_in', 'true');
          await this.fetchUserData();
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
      try {
        await axios.post('/logout');
      } catch (error) {
        console.error('Logout error:', error);
      }
      
      this.user = null;
      this.isAuthenticated = false;
      localStorage.removeItem('game_logged_in');
    },

    async fetchUserData() {
      try {
        const response = await axios.get('/api/user-data');
        if (response.data.success) {
          this.user = response.data.user;
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



    checkAuthStatus() {
      const isLoggedIn = localStorage.getItem('game_logged_in') === 'true';
      if (isLoggedIn) {
        this.isAuthenticated = true;
        this.fetchUserData();
      }
    }
  }
});