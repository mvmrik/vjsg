<template>
  <c-row>
    <c-col md="12">
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
    </c-col>
  </c-row>

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

  <!-- Success/Error Messages -->
  <c-alert
    v-if="message"
    :color="messageType === 'success' ? 'success' : 'danger'"
    :visible="true"
    dismissible
    @close="message = ''"
  >
    {{ message }}
  </c-alert>
</template>

<script>
import axios from 'axios';
import { ref, computed, onMounted, inject } from 'vue';
import { useGameStore } from '../stores/gameStore';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default {
  name: 'MapPage',
  setup() {
    const parcels = computed(() => gameStore.parcels || []);
    const loading = ref(false);
    const message = ref('');
    const messageType = ref('');
    const gameStore = useGameStore();
    
    // Local translate function
    const $t = (key) => {
      const [section, actualKey] = key.split('.');
      return window.translations[section]?.[actualKey] || key;
    };

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

    const canClaim = (lat, lng) => {
      const myParcels = parcels.value.filter(p => p.user_id === gameStore.user?.id);
      if (myParcels.length === 0) return true;
      // Check if adjacent to any of my parcels (within 500m range)
      return myParcels.some(p => {
        const dist_lat = Math.abs(p.lat - lat);
        const dist_lng = Math.abs(p.lng - lng);
        const delta_lat = 500 / 111000;
        const cos_lat = Math.cos(lat * Math.PI / 180);
        const delta_lng = 500 / (111000 * cos_lat);
        // Check for adjacency (touching edges)
        return dist_lat < delta_lat * 1.1 && dist_lng < delta_lng * 1.1;
      });
    };

    const claimParcel = async (lat, lng) => {
      loading.value = true;
      try {
        const res = await gameStore.claimParcel(lat, lng);
        if (res.success) {
          await gameStore.fetchParcels(); // Update game store
          updateMap();
          message.value = $t('map.parcel_claimed_successfully');
          messageType.value = 'success';
          // Center on user's parcels
          centerOnUserParcels();
        } else {
          message.value = res.message || $t('map.claim_failed');
          messageType.value = 'error';
        }
      } catch (e) {
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

    onMounted(() => {
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
          const bounds = userParcels.map(p => [p.lat, p.lng]);
          map.fitBounds(L.latLngBounds(bounds), { maxZoom: 18 });
        }
      });
    });

    return {
      parcels,
      loading,
      message,
      messageType,
      userParcels,
      uniqueOwners,
      gameStore,
      centerOnUserParcels,
      refreshMap
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
