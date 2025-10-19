<template>
  <c-row class="justify-content-center">
    <c-col md="12">
      <!-- Language Selector -->
      <div v-if="!gameStore.isAuthenticated" class="d-flex justify-content-end mb-3">
        <div class="btn-group" role="group">
          <button 
            type="button" 
            class="btn btn-outline-primary btn-sm px-2"
            :class="{ active: currentLocale === 'en' }"
            @click="changeLanguage('en')"
          >
            <span class="fi fi-us"></span>
          </button>
          <button 
            type="button" 
            class="btn btn-outline-primary btn-sm px-2"
            :class="{ active: currentLocale === 'bg' }"
            @click="changeLanguage('bg')"
          >
            <span class="fi fi-bg"></span>
          </button>
        </div>
      </div>

      <!-- Map for authenticated users -->
      <div v-if="gameStore.isAuthenticated">
        <c-card class="mb-4">
          <c-card-header class="d-flex justify-content-between align-items-center">
            <strong>
              <c-icon name="cilMap" class="me-2" />
              {{ $t('map.game_map') }}
            </strong>
            <div>
              <c-button 
                color="primary" 
                size="sm" 
                @click="centerOnUserParcels"
                :disabled="!userParcels.length"
                class="me-2"
              >
                <c-icon name="cilLocationPin" class="me-1" />
                {{ $t('map.my_parcels') }}
              </c-button>
              <c-button 
                color="success" 
                size="sm" 
                @click="refreshMap"
                :disabled="loading"
              >
                <c-spinner v-if="loading" size="sm" class="me-1" />
                <c-icon v-else name="cilReload" class="me-1" />
                {{ $t('map.refresh') }}
              </c-button>
            </div>
          </c-card-header>
          <c-card-body class="p-0">
            <div id="map" style="height: 600px; width: 100%;"></div>
          </c-card-body>
        </c-card>

        <c-row>
          <c-col md="4">
            <c-card class="mb-4">
              <c-card-header>
                <strong>{{ $t('map.statistics') }}</strong>
              </c-card-header>
              <c-card-body>
                <c-list-group flush>
                  <c-list-group-item class="d-flex justify-content-between align-items-center">
                    <span>
                      <c-icon name="cilHome" class="me-2 text-primary" />
                      {{ $t('map.my_parcels') }}
                    </span>
                    <c-badge color="primary">{{ userParcels.length }}</c-badge>
                  </c-list-group-item>
                  <c-list-group-item class="d-flex justify-content-between align-items-center">
                    <span>
                      <c-icon name="cilGlobeAlt" class="me-2 text-success" />
                      {{ $t('map.total_parcels') }}
                    </span>
                    <c-badge color="success">{{ parcels.length }}</c-badge>
                  </c-list-group-item>
                  <c-list-group-item class="d-flex justify-content-between align-items-center">
                    <span>
                      <c-icon name="cilUser" class="me-2 text-info" />
                      {{ $t('map.active_players') }}
                    </span>
                    <c-badge color="info">{{ uniqueOwners }}</c-badge>
                  </c-list-group-item>
                </c-list-group>
              </c-card-body>
            </c-card>
          </c-col>

          <c-col md="4">
            <c-card class="mb-4">
              <c-card-header>
                <strong>{{ $t('map.legend') }}</strong>
              </c-card-header>
              <c-card-body>
                <div class="mb-3">
                  <div class="d-flex align-items-center mb-2">
                    <div class="legend-color me-2" style="background: #059669;"></div>
                    <span>{{ $t('map.your_parcels') }}</span>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div class="legend-color me-2" style="background: #dc2626;"></div>
                    <span>{{ $t('map.other_parcels') }}</span>
                  </div>
                  <div class="d-flex align-items-center mb-2">
                    <div class="legend-color dashed me-2" style="border: 1px dashed #cccccc; background: rgba(204,204,204,0.1);"></div>
                    <span>{{ $t('map.available_for_claim') }}</span>
                  </div>
                </div>
              </c-card-body>
            </c-card>
          </c-col>

          <c-col md="4">
            <c-card class="mb-4">
              <c-card-header>
                <strong>{{ $t('map.instructions') }}</strong>
              </c-card-header>
              <c-card-body>
                <ul class="mb-0 ps-3">
                  <li v-if="!userParcels.length" class="mb-2">
                    <strong>{{ $t('map.first_parcel') }}</strong>
                  </li>
                  <li v-else class="mb-2">
                    <strong>{{ $t('map.new_parcels') }}</strong>
                  </li>
                  <li class="mb-2">
                    <strong>{{ $t('map.parcel_info') }}</strong>
                  </li>
                  <li class="mb-0">
                    <strong>{{ $t('map.navigation') }}</strong>
                  </li>
                </ul>
              </c-card-body>
            </c-card>
          </c-col>
        </c-row>
      </div>

      <!-- Login/Register section for non-authenticated users -->
      <c-row v-else>
        <c-col md="8" class="mx-auto">
          <c-card>
            <c-card-body class="text-center py-4">
              <c-icon name="cilUser" size="3xl" class="text-muted mb-3" />
              <h4>{{ $t('home.login_prompt') }}</h4>
              <p class="text-muted mb-4">{{ $t('home.login_prompt') }}</p>
              
              <!-- Tab Navigation -->
              <c-nav variant="tabs" role="tablist" class="mb-4 justify-content-center">
                <c-nav-item>
                  <c-nav-link
                    :active="activeAuthTab === 'login'"
                    @click="activeAuthTab = 'login'"
                    class="cursor-pointer"
                  >
                    <c-icon name="cilAccountLogout" class="me-2" />
                    {{ $t('home.login') }}
                  </c-nav-link>
                </c-nav-item>
                <c-nav-item>
                  <c-nav-link
                    :active="activeAuthTab === 'register'"
                    @click="activeAuthTab = 'register'"
                    class="cursor-pointer"
                  >
                    <c-icon name="cilUserPlus" class="me-2" />
                    {{ $t('home.register') }}
                  </c-nav-link>
                </c-nav-item>
              </c-nav>

              <!-- Login Tab -->
              <div v-show="activeAuthTab === 'login'" class="text-start">
                <c-form @submit.prevent="handleLogin">
                  <c-input-group class="mb-3">
                    <c-input-group-text>
                      <c-icon name="cilLockLocked" />
                    </c-input-group-text>
                    <c-form-input
                      v-model="loginForm.privateKey"
                      :placeholder="$t('home.private_key_placeholder')"
                      type="text"
                      maxlength="64"
                      required
                    />
                  </c-input-group>
                  
                  <div class="form-check mb-3">
                    <input 
                      class="form-check-input" 
                      type="checkbox" 
                      id="rememberMeHome" 
                      v-model="loginForm.rememberMe"
                    >
                    <label class="form-check-label" for="rememberMeHome">
                      {{ $t('global.remember_me') }}
                    </label>
                  </div>
                  
                  <c-form-text class="mb-3 text-muted">
                    <c-icon name="cilInfo" class="me-1" />
                    {{ $t('home.private_key_help') }}
                  </c-form-text>

                  <c-button
                    type="submit"
                    color="primary"
                    class="w-100"
                    :disabled="loading || loginForm.privateKey.length !== 64"
                  >
                    <c-spinner
                      v-if="loading"
                      size="sm"
                      class="me-2"
                    />
                    {{ loading ? $t('home.logging_in') : $t('home.login_button') }}
                  </c-button>
                </c-form>
              </div>

              <!-- Register Tab -->
              <div v-show="activeAuthTab === 'register'" class="text-start">
                <c-form @submit.prevent="handleRegister">
                  <c-input-group class="mb-3">
                    <c-input-group-text>
                      <c-icon name="cilUser" />
                    </c-input-group-text>
                    <c-form-input
                      v-model="registerForm.username"
                      :placeholder="$t('home.username_placeholder')"
                      required
                      :minlength="3"
                    />
                  </c-input-group>
                  
                  <c-form-text class="mb-3 text-muted">
                    <c-icon name="cilInfo" class="me-1" />
                    {{ $t('home.username_help') }}
                  </c-form-text>

                  <c-button
                    type="submit"
                    color="success"
                    class="w-100"
                    :disabled="loading || registerForm.username.length < 3"
                  >
                    <c-spinner
                      v-if="loading"
                      size="sm"
                      class="me-2"
                    />
                    {{ loading ? $t('home.registering') : $t('home.register_button') }}
                  </c-button>
                </c-form>
              </div>
            </c-card-body>
          </c-card>
        </c-col>
      </c-row>
    </c-col>
  </c-row>
</template>

<script>
import { ref, computed, onMounted, watch, nextTick, inject } from 'vue';
import { useRouter } from 'vue-router';
import { useGameStore } from '../stores/gameStore';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default {
  name: 'HomePage',
  setup() {
    const router = useRouter();
    const gameStore = useGameStore();
    const $t = inject('$t');
    const $changeLanguage = inject('$changeLanguage');
    const currentLocale = inject('currentLocale');
    
    const loading = ref(false);
    const activeAuthTab = ref('login'); // Default to login tab

    const loginForm = ref({
      privateKey: '',
      rememberMe: false
    });

    const registerForm = ref({
      username: ''
    });

    // Map related
    const parcels = computed(() => gameStore.parcels || []);
    const userParcels = computed(() => 
      parcels.value.filter(p => p.user_id === gameStore.user?.id)
    );
    const uniqueOwners = computed(() => {
      const owners = new Set(parcels.value.map(p => p.user_id));
      return owners.size;
    });
    let map = null;
    let territories = [];
    let ghosts = [];
    
    const showMessage = (msg, type) => {
      message.value = msg;
      messageType.value = type;
      
      setTimeout(() => {
        message.value = '';
      }, 5000);
    };

    const collectResources = () => {
      showMessage('Ресурсите бяха събрани успешно!', 'success');
      gameStats.value.resources += 25;
      gameStats.value.experience += 10;
    };

    const handleLogin = async () => {
      loading.value = true;
      message.value = '';

      try {
        await gameStore.login(loginForm.value.privateKey, loginForm.value.rememberMe);
        // Login successful, redirect to home
        await router.push('/');
      } catch (error) {
        showMessage(gameStore.error || 'Грешка при влизане', 'error');
      } finally {
        loading.value = false;
      }
    };

    const handleRegister = async () => {
      loading.value = true;
      message.value = '';

      try {
        await gameStore.register(registerForm.value.username);
        // Registration successful, redirect handled by gameStore
        registerForm.value.username = '';
      } catch (error) {
        showMessage(gameStore.error || 'Грешка при регистрация', 'error');
      } finally {
        loading.value = false;
      }
    };

    // Map functions
    const fetchParcels = async () => {
      await gameStore.fetchParcels();
      updateMap();
    };

    const updateMap = () => {
      // Clear existing territories
      territories.forEach(terr => terr.remove());
      territories = [];
      ghosts.forEach(ghost => ghost.remove());
      ghosts = [];

      // Draw all parcels
      parcels.value.forEach(parcel => {
        if (!parcel || parcel.lat == null || parcel.lng == null) return;
        const bounds = getSquareBounds(parcel.lat, parcel.lng);
        const color = parcel.user_id === gameStore.user?.id ? '#059669' : '#dc2626'; // green for own, red for others
        const rect = L.rectangle(bounds, {
          color: color,
          weight: 1,
          fillOpacity: 0.5
        }).addTo(map);
        rect.bindPopup(`${$t('map.parcel')}: ${parseFloat(parcel.lat)}, ${parseFloat(parcel.lng)}<br>${$t('map.owner')}: ${parcel.user.username}`);
        territories.push(rect);
      });

      // Generate ghost squares adjacent to user's parcels
      const userParcels = parcels.value.filter(p => p.user_id === gameStore.user?.id);
      if (userParcels.length > 0) {
        const potentialSquares = new Set();
        userParcels.forEach(parcel => {
          if (!parcel || parcel.lat == null || parcel.lng == null) return;
          const lat = parseFloat(parcel.lat);
          const lng = parseFloat(parcel.lng);
          const parcelSize = 500; // 500 meters
          const directions = [
            { dlat: parcelSize / 111000, dlng: 0 }, // North
            { dlat: -parcelSize / 111000, dlng: 0 }, // South
            { dlat: 0, dlng: parcelSize / (111000 * Math.cos(lat * Math.PI / 180)) }, // East
            { dlat: 0, dlng: -parcelSize / (111000 * Math.cos(lat * Math.PI / 180)) }, // West
          ];
          directions.forEach(dir => {
            const newLat = lat + dir.dlat;
            const newLng = lng + dir.dlng;
            const key = `${newLat.toFixed(6)},${newLng.toFixed(6)}`;
            if (!isClaimed(newLat, newLng)) {
              potentialSquares.add(key);
            }
          });
        });

        potentialSquares.forEach(key => {
          const [lat, lng] = key.split(',').map(Number);
          const bounds = getSquareBounds(lat, lng);
          const ghost = L.rectangle(bounds, {
            color: '#cccccc',
            dashArray: '5,5',
            weight: 1,
            fillOpacity: 0.1
          }).addTo(map);
          ghost.bindPopup(`Available: ${lat}, ${lng}`);
          ghosts.push(ghost);

          // Hover effects
          ghost.on('mouseover', function() {
            this.setStyle({ dashArray: null, fillOpacity: 0.3 });
          });
          ghost.on('mouseout', function() {
            this.setStyle({ dashArray: '5,5', fillOpacity: 0.1 });
          });

          // Click to claim
          ghost.on('click', function(e) {
            L.DomEvent.stopPropagation(e);
            claimParcel(lat, lng);
          });
        });
      }
    };

    const getSquareBounds = (lat, lng) => {
      lat = parseFloat(lat);
      lng = parseFloat(lng);
      const delta_lat = 500 / 111000; // approx 500 meters
      const cos_lat = Math.cos(lat * Math.PI / 180);
      const delta_lng = 500 / (111000 * cos_lat);
      const sw = L.latLng(lat - delta_lat / 2, lng - delta_lng / 2);
      const ne = L.latLng(lat + delta_lat / 2, lng + delta_lng / 2);
      return L.latLngBounds(sw, ne);
    };

    const isClaimed = (lat, lng) => {
      // Check if a parcel exists within a 500m range
      const delta = 500 / 111000 / 2;
      return parcels.value.some(p => Math.abs(p.lat - lat) < delta && Math.abs(p.lng - lng) < delta);
    };

    const claimParcel = async (lat, lng) => {
      loading.value = true;
      try {
        const res = await gameStore.claimParcel(lat, lng);
        console.log('Claim result:', res);
        if (res.success) {
          await gameStore.fetchParcels(); // Update game store
          await gameStore.fetchUserData(); // Update user balance
          // Update map after reactivity updates
          await nextTick();
          updateMap();
          // Show success toast
          window.dispatchEvent(new CustomEvent('show-toast', { 
            detail: { 
              message: $t('map.parcel_claimed_successfully'),
              type: 'success'
            }
          }));
          // Center on user's parcels
          centerOnUserParcels();
        } else {
          console.log('Claim failed with message:', res.message);
          // Show toast notification
          window.dispatchEvent(new CustomEvent('show-toast', { 
            detail: { 
              message: res.message,
              type: 'error'
            }
          }));
        }
      } catch (e) {
        console.log('Claim error:', e);
        message.value = e.response?.data?.message || $t('map.claim_error');
        messageType.value = 'error';
      } finally {
        loading.value = false;
      }
    };

    const centerOnUserParcels = () => {
      if (userParcels.value.length > 0) {
        const bounds = L.latLngBounds(userParcels.value.map(p => [p.lat, p.lng]));
        map.fitBounds(bounds.pad(0.1)); // Add small padding to see all parcels
      }
    };    const refreshMap = () => {
      fetchParcels();
    };
    
    // Load user stats on mount
    onMounted(() => {
      if (gameStore.isAuthenticated) {
        // Load real game stats here
        gameStore.checkAuthStatus();
        // Initialize map
        map = L.map('map').setView([42.6977, 23.3219], 13); // Default center
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Handle map click for first claim
        map.on('click', function(e) {
          const latlng = e.latlng;
          if (isClaimed(latlng.lat, latlng.lng)) {
            alert($t('map.area_already_claimed'));
            return;
          }
          const userParcels = parcels.value.filter(p => p.user_id === gameStore.user?.id);
          if (userParcels.length === 0) {
            // First claim anywhere
            claimParcel(latlng.lat, latlng.lng);
          }
        });

        fetchParcels().then(() => {
          // Center on user's parcels if any
          const userParcels = parcels.value.filter(p => p.user_id === gameStore.user?.id);
          if (userParcels.length > 0) {
            const bounds = L.latLngBounds(userParcels.map(p => [p.lat, p.lng]));
            map.fitBounds(bounds.pad(0.1)); // Add small padding to see all parcels
          }
        });
      }
    });

    // Watch for authentication changes to initialize map when user logs in
    watch(() => gameStore.isAuthenticated, async (isAuthenticated) => {
      if (isAuthenticated && !map) {
        // Wait for DOM to update before initializing map
        await nextTick();
        
        // Initialize map when user becomes authenticated
        map = L.map('map').setView([42.6977, 23.3219], 13); // Default center
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Handle map click for first claim
        map.on('click', function(e) {
          const latlng = e.latlng;
          if (isClaimed(latlng.lat, latlng.lng)) {
            alert($t('map.area_already_claimed'));
            return;
          }
          const userParcels = parcels.value.filter(p => p.user_id === gameStore.user?.id);
          if (userParcels.length === 0) {
            // First claim anywhere
            claimParcel(latlng.lat, latlng.lng);
          }
        });

        fetchParcels().then(() => {
          // Center on user's parcels if any
          const userParcels = parcels.value.filter(p => p.user_id === gameStore.user?.id);
          if (userParcels.length > 0) {
            const bounds = L.latLngBounds(userParcels.map(p => [p.lat, p.lng]));
            map.fitBounds(bounds.pad(0.1)); // Add small padding to see all parcels
          }
        });
      }
    });

    const changeLanguage = (lang) => {
      $changeLanguage(lang);
    };
    
    return {
      loading,
      activeAuthTab,
      loginForm,
      registerForm,
      gameStore,
      parcels,
      userParcels,
      uniqueOwners,
      centerOnUserParcels,
      refreshMap,
      currentLocale,
      showMessage,
      collectResources,
      handleLogin,
      handleRegister,
      changeLanguage,
      $t
    };
  }
}
</script>

<style scoped>
.legend-color {
  width: 20px;
  height: 15px;
  border-radius: 2px;
}

.legend-color.dashed {
  background: rgba(204,204,204,0.1) !important;
}

#map {
  border-radius: 0 0 0.375rem 0.375rem;
}
</style>