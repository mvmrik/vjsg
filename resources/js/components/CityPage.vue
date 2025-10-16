<template>
  <c-row>
    <c-col md="12">
      <c-card class="mb-4">
        <c-card-header>
          <strong>
            <c-icon name="cilCity" class="me-2" />
            Моят град
          </strong>
        </c-card-header>
        <c-card-body>
          <c-row>
            <!-- City Grid -->
            <c-col md="12">
              <div class="city-container">
                <div v-if="userParcels.length === 0" class="text-center py-5">
                  <p>Нямате парцели за показване.</p>
                </div>
                <div v-else class="city-map" :style="{ height: cityMapHeight + 'px', width: cityMapWidth + 'px' }">
                  <div
                    v-for="parcel in userParcels"
                    :key="parcel.id"
                    class="parcel-grid"
                    :style="getParcelPosition(parcel)"
                  >
                    <div class="grid-simple">
                      <div
                        v-for="i in 100"
                        :key="i"
                        class="grid-cell-simple parcel-cell"
                        @click="openParcelEditor(parcel)"
                      >
                        <!-- Show placed objects -->
                        <div
                          v-for="obj in getObjectsForParcel(parcel.id)"
                          :key="obj.id"
                          v-show="isCellInObject(i, obj)"
                          :class="['placed-object', { 'building': isBuilding(obj) }]"
                        >
                          <c-icon :name="getObjectIcon(obj.object_type)" />
                          <div v-if="isBuilding(obj)" class="build-overlay">
                            <div class="build-time">{{ getRemainingTimeText(obj) }}</div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </c-col>
          </c-row>
        </c-card-body>
      </c-card>
    </c-col>
  </c-row>

  <!-- Messages -->
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
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useGameStore } from '../stores/gameStore';
import axios from 'axios';

export default {
  name: 'CityPage',
  setup() {
    const gameStore = useGameStore();
    const router = useRouter();
    const route = useRoute();
    const loading = ref(false);
    const message = ref('');
    const messageType = ref('');
    const cityObjects = ref([]);
    
    // Watch for route changes to refresh objects when returning to city
    watch(() => route.path, async (newPath, oldPath) => {
      if (newPath === '/city') {
        await fetchCityObjects();
      }
    });

    const openParcelEditor = (parcel) => {
      router.push(`/city/${parcel.id}`);
    };

    const userParcels = computed(() => {
      const parcels = gameStore.parcels?.filter(p => p.user_id === gameStore.user?.id) || [];
      return parcels;
    });

    const getParcelPosition = (parcel) => {
      const x = parcel.city_x;
      const y = parcel.city_y;

      // Responsive parcel size based on map width
      const parcelSize = Math.max(60, Math.min(110, cityMapWidth.value / 10));
      const streetWidth = 10; // Street width between parcels

      // Find the minimum and maximum x and y coordinates
      const allParcels = userParcels.value;
      let minX = 0, maxY = 0;
      if (allParcels.length > 0) {
        minX = Math.min(...allParcels.map(p => p.city_x));
        maxY = Math.max(...allParcels.map(p => p.city_y)); // Find max Y instead of min
      }

      // Position relative to top-left (minimum X, maximum Y)
      const left = (x - minX) * (parcelSize + streetWidth) + 20; // Add small padding from edge
      const top = (maxY - y) * (parcelSize + streetWidth) + 20; // Invert Y: higher Y values go to top

      return {
        position: 'absolute',
        left: left + 'px',
        top: top + 'px',
        width: parcelSize + 'px',
        height: parcelSize + 'px'
      };
    };

    const cityMapHeight = computed(() => {
      // Calculate height based on parcels
      const allParcels = userParcels.value;
      if (allParcels.length === 0) return 600;
      
      const minY = Math.min(...allParcels.map(p => p.city_y));
      const maxY = Math.max(...allParcels.map(p => p.city_y));
      const parcelSize = Math.max(60, Math.min(110, cityMapWidth.value / 10));
      const streetWidth = 10;
      
      return (maxY - minY + 1) * (parcelSize + streetWidth) + 40; // +40 for padding
    });

    const cityMapWidth = computed(() => {
      // Calculate width based on parcels
      const allParcels = userParcels.value;
      if (allParcels.length === 0) return 600;
      
      const minX = Math.min(...allParcels.map(p => p.city_x));
      const maxX = Math.max(...allParcels.map(p => p.city_x));
      const parcelSize = 100; // Base size
      const streetWidth = 10;
      
      return (maxX - minX + 1) * (parcelSize + streetWidth) + 40; // +40 for padding
    });

    const fetchCityObjects = async () => {
      try {
        const res = await axios.get('/api/city-objects');
        if (res.data.success) {
          // Backend already handles clearing expired ready_at
          cityObjects.value = res.data.objects;
        }
      } catch (e) {
        console.error('Failed to fetch city objects', e);
      }
    };

    const getObjectsForParcel = (parcelId) => {
      return cityObjects.value.filter(obj => obj.parcel_id === parcelId);
    };

    const getTotalBuildSeconds = (obj) => {
      return obj.build_seconds || 60;
    };

    const getReadyTimestamp = (obj) => {
      // return ms timestamp of ready time; if ready_at missing, try to compute from created_at
      if (obj.ready_at) {
        const t = Date.parse(obj.ready_at);
        if (!isNaN(t)) return t;
      }
      if (obj.created_at) {
        const created = Date.parse(obj.created_at);
        const totalSeconds = getTotalBuildSeconds(obj);
        if (!isNaN(created) && totalSeconds) return created + totalSeconds * 1000;
      }
      return null;
    };

    const getProgressPercent = (obj) => {
      const totalSeconds = getTotalBuildSeconds(obj);
      if (!totalSeconds || !obj.ready_at) return 100;
  const ready = getReadyTimestamp(obj);
  if (!ready) return 100;
      const total = totalSeconds * 1000;
      const started = ready - total;
      const now = Date.now();
      const elapsed = Math.max(0, Math.min(now - started, total));
      return Math.floor((elapsed / total) * 100);
    };

    const getRemainingTimeText = (obj) => {
      const totalSeconds = getTotalBuildSeconds(obj);
      if (!totalSeconds || !obj.ready_at) return '';
  const ready = getReadyTimestamp(obj);
  if (!ready) return '';
  const now = Date.now();
  const remainingMs = Math.max(0, ready - now);
      const sec = Math.ceil(remainingMs / 1000);
      const m = Math.floor(sec / 60);
      const s = sec % 60;
      return `${m}m ${s}s`;
    };

    const isBuilding = (obj) => {
  const ready = getReadyTimestamp(obj);
  if (!ready) return false;
  return ready > Date.now();
    };

    // Tick to refresh progress for existing building objects
    let tickInterval = null;
    onMounted(() => {
      // ensure we refresh every second so progress/time shows up for existing items
      tickInterval = setInterval(() => {
        cityObjects.value = cityObjects.value.slice();
      }, 1000);
    });
    onUnmounted(() => {
      if (tickInterval) clearInterval(tickInterval);
    });

    const isCellInObject = (cellIndex, obj) => {
      const cellX = (cellIndex - 1) % 10;
      const cellY = Math.floor((cellIndex - 1) / 10);
      // Check if object occupies this single cell
      return obj.x === cellX && obj.y === cellY;
    };

    const getObjectIcon = (type) => {
      const availableObjects = [
        { type: 'house', name: 'Къща', icon: 'cilHome' },
        { type: 'tree', name: 'Дърво', icon: 'cilTree' },
        { type: 'well', name: 'Кладенец', icon: 'cilDrop' },
        { type: 'barn', name: 'Хамбар', icon: 'cilStorage' }
      ];
      const obj = availableObjects.find(o => o.type === type);
      return obj ? obj.icon : 'cilQuestion';
    };

    onMounted(async () => {
      if (gameStore.user) {
        await gameStore.fetchParcels();
        await fetchCityObjects();
      } else {
        gameStore.checkAuthStatus();
        setTimeout(async () => {
          if (gameStore.user) {
            await gameStore.fetchParcels();
            await fetchCityObjects();
          }
        }, 1000);
      }
    });

    watch(() => gameStore.user, async (newUser) => {
      if (newUser) {
        await gameStore.fetchParcels();
        await fetchCityObjects();
      }
    });

    // Watch for route changes to refresh data when returning from parcel editor
    watch(() => route.path, async (newPath) => {
      if (newPath === '/city' && gameStore.user) {
        await fetchCityObjects();
      }
    });

    // export everything the template needs (including helpers)
    return {
      loading,
      message,
      messageType,
      userParcels,
      getParcelPosition,
      cityMapHeight,
      cityMapWidth,
      cityObjects,
      getObjectsForParcel,
      isCellInObject,
      openParcelEditor,
      getObjectIcon,
      getProgressPercent,
      getRemainingTimeText,
      getTotalBuildSeconds,
      isBuilding
    };
  }
}
</script>

<style scoped>
.city-container {
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  padding: 10px;
  overflow-x: auto; /* Allow horizontal scroll only if needed */
}

.city-map {
  position: relative;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  margin: 0;
  min-width: 100%;
  min-height: 400px;
}

.parcel-grid {
  position: absolute;
  border: none;
  border-radius: 0.375rem;
  padding: 0;
  background: white;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  overflow: hidden;
}

.parcel-label {
  font-size: 10px;
  color: #666;
  text-align: center;
  margin-bottom: 5px;
  font-weight: bold;
}

.grid-simple {
  display: grid;
  grid-template-columns: repeat(10, 1fr);
  grid-template-rows: repeat(10, 1fr);
  gap: 1px;
  background: #e9ecef;
  padding: 1px;
  width: 100%;
  height: 100%;
}

.grid-cell-simple {
  background: white;
  border: 1px solid #dee2e6;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}

.placed-object {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 123, 255, 0.1);
  border: 1px solid #007bff;
  cursor: pointer;
}

.placed-object.building { background: rgba(220,53,69,0.08); border-color: #dc3545; }
.placed-object .build-progress { background: rgba(220,53,69,0.6); }

.build-overlay { position: absolute; inset: 0; display:flex; align-items:flex-end; }
.build-time { position:absolute; bottom:2px; right:4px; font-size:10px; color:#004085 }

.placement-preview {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0.7;
  pointer-events: none;
}

.parcel-cell {
  cursor: pointer;
}

.parcel-cell:hover {
  background: rgba(0, 123, 255, 0.1);
}

.parcel-editor {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}

.large-grid-container {
  flex: 1;
}

.large-grid {
  display: grid;
  grid-template-columns: repeat(10, 1fr);
  grid-template-rows: repeat(10, 1fr);
  gap: 2px;
  background: #e9ecef;
  padding: 2px;
  width: 400px;
  height: 400px;
  margin: 0 auto;
}

.large-grid-cell {
  background: white;
  border: 1px solid #dee2e6;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.large-grid-cell.cell-selected {
  background: rgba(0, 123, 255, 0.3) !important;
  border-color: #007bff;
}

.placed-object-large {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 123, 255, 0.1);
  border: 1px solid #007bff;
  cursor: pointer;
}

.editor-palette {
  width: 250px;
}

.object-palette .palette-object.selected {
  background: #007bff;
  color: white;
}

.object-palette {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.palette-object {
  padding: 10px;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  background: white;
  cursor: move;
  user-select: none;
}

.palette-object:hover {
  background: #f8f9fa;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .city-container {
    padding: 5px;
    margin: 0;
  }

  .city-map {
    min-width: auto;
    width: 100%;
    max-width: 100vw;
    overflow-x: auto;
  }

  .parcel-grid {
    min-width: 40px;
    min-height: 40px;
  }

  .grid-simple {
    width: 100%;
    height: 100%;
  }
}
</style>