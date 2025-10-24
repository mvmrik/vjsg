<template>
  <div class="parcel-editor-page">
    <c-container fluid>
      <c-row>
        <c-col>
          <c-card>
            <c-card-header>
              <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                  <c-icon name="cilCity" class="me-2" />
                  {{ $t('city.parcel_editing') }}
                </h5>
                <div class="d-flex gap-2">
                  <c-button color="secondary" @click="goBackToCity">
                    <c-icon name="cilArrowLeft" class="me-1" />
                    {{ $t('city.back_to_city') }}
                  </c-button>
                </div>
              </div>
            </c-card-header>
            <c-card-body>
              <div v-if="loading" class="text-center">
                <c-spinner />
                <p>{{ $t('city.loading') }}</p>
              </div>

              <div v-else-if="parcel" class="parcel-editor-content">
                <!-- Large Grid for editing -->
                <div class="large-grid-container">
                  <div class="large-grid">
                    <div
                      v-for="i in 100"
                      :key="i"
                      class="large-grid-cell"
                      @click="toggleCellSelection(i)"
                    >
                      <!-- Show placed objects -->
                      <div
                        v-for="obj in getObjectsForParcel(parcel.id)"
                        :key="obj.id"
                        v-show="isCellInObject(i, obj)"
                        :class="['placed-object-large', { 'building-large': isBuilding(obj) }]"
                        @click.stop="selectObject(obj)"
                      >
                        <c-icon 
                          :name="getObjectIcon(obj.object_type)" 
                          :class="{ 'icon-building': isBuilding(obj) }"
                        />
                        <!-- Show time when building, level when ready (desktop only) -->
                        <div v-if="isBuilding(obj)" class="object-info-badge d-none d-md-flex building-badge">
                          {{ getRemainingTimeText(obj) }}
                        </div>
                        <div v-else class="object-info-badge d-none d-md-flex level-badge">
                          {{ $t('city.level') }} {{ obj.level || 1 }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Object selection moved to modal: keep selection instructions -->
                <div class="editor-palette">
                  <h6>{{ $t('city.objects') }}</h6>
                  <div class="mt-3">
                    <p class="text-muted small">
                      <c-icon name="cilInfo" class="me-1" />
                      {{ $t('city.click_to_select_object') }}
                    </p>
                  </div>
                </div>
              </div>
            </c-card-body>
          </c-card>
        </c-col>
      </c-row>
    </c-container>

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
  
  <!-- Object type chooser modal -->
  <c-modal v-model="showObjectModal" size="lg">
      <c-modal-header>
        <c-modal-title>{{ $t('city.select_object_type') }}</c-modal-title>
      </c-modal-header>
      <c-modal-body>
        <div class="mb-3 d-flex gap-2">
          <div style="flex:1">
            <label class="form-label small mb-1">{{ $t('city.worker_level') }}</label>
            <select class="form-select" v-model="selectedWorkerLevel" @change="onSelectedWorkerLevelChange">
              <option :value="null">{{ $t('city.no_workers') }}</option>
              <option v-for="(count, lvl) in people.by_level" :key="lvl" :value="lvl">LV {{ lvl }} ({{ count }})</option>
            </select>
          </div>
        </div>

        <!-- Worker count slider (full-width) -->
        <div class="mb-3">
          <label class="form-label small mb-1">{{ $t('city.worker_count') }}</label>
          <div class="d-flex align-items-center gap-3">
            <input
              type="range"
              :min="0"
              :max="availableCountForLevel(selectedWorkerLevel)"
              v-model.number="selectedWorkerCount"
              class="form-range"
            />
            <div class="fw-bold">{{ selectedWorkerCount }}</div>
          </div>
          <div class="small text-muted">{{ tr('city.available','Available') }}: {{ availableCountForLevel(selectedWorkerLevel) }}</div>
        </div>

        <div class="object-palette d-flex flex-column">
          <div
            v-for="objType in availableObjects"
            :key="objType.type + '-' + selectedWorkerLevel + '-' + selectedWorkerCount"
            class="palette-object p-2 d-flex justify-content-between align-items-center"
            :class="{ selected: modalSelectedObjectType?.type === objType.type }"
            style="cursor: pointer;"
            @click="modalSelectedObjectType = objType"
          >
              <div class="d-flex align-items-center">
              <c-icon :name="objType.icon" class="me-2" />
              <div>{{ translateObjectLabel(objType.type, objType.name) }}</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="d-flex flex-column align-items-end">
                <div class="text-muted small">{{ objType.build_time_minutes }}m</div>
                <div class="text-success small fw-bold">{{ displayedTimes[objType.type] || objType.build_time_minutes }}m</div>
              </div>
              <c-icon v-if="modalSelectedObjectType?.type === objType.type" name="cilCheck" class="text-primary" />
            </div>
          </div>
        </div>
      </c-modal-body>
      <c-modal-footer>
        <c-button color="secondary" @click="(modalSelectedObjectType = null, showObjectModal = false)">{{ $t('city.cancel') }}</c-button>
        <c-button 
          color="primary" 
          :disabled="!modalSelectedObjectType || !selectedWorkerLevel || selectedWorkerCount === 0" 
          @click="confirmModalPlacement"
        >
          {{ $t('city.confirm') }}
        </c-button>
      </c-modal-footer>
    </c-modal>

    <!-- Fallback modal (simple overlay) -->
    <div v-if="showObjectModal && useFallbackModal" class="fallback-backdrop" @click.self="(modalSelectedObjectType = null, showObjectModal = false)">
      <div class="fallback-modal">
        <h5>{{ $t('city.select_object_type') }}</h5>
        <div class="mb-3 d-flex gap-2">
          <div style="flex:1">
            <label class="form-label small mb-1">{{ $t('city.worker_level') }}</label>
            <select class="form-select" v-model="selectedWorkerLevel" @change="onSelectedWorkerLevelChange">
              <option :value="null">{{ $t('city.no_workers') }}</option>
              <option v-for="(count, lvl) in people.by_level" :key="lvl + '-fb'" :value="lvl">LV {{ lvl }} ({{ count }})</option>
            </select>
          </div>
        </div>

        <!-- Fallback: worker count slider -->
        <div class="mb-3">
          <label class="form-label small mb-1">{{ $t('city.worker_count') }}</label>
          <div class="d-flex align-items-center gap-3">
            <input
              type="range"
              :min="0"
              :max="availableCountForLevel(selectedWorkerLevel)"
              v-model.number="selectedWorkerCount"
              class="form-range"
            />
            <div class="fw-bold">{{ selectedWorkerCount }}</div>
          </div>
          <div class="small text-muted">{{ tr('city.available','Available') }}: {{ availableCountForLevel(selectedWorkerLevel) }}</div>
        </div>

        <div class="object-palette d-flex flex-column mt-2">
          <div
            v-for="objType in availableObjects"
            :key="objType.type + '-fb-' + selectedWorkerLevel + '-' + selectedWorkerCount"
            class="palette-object p-2 d-flex justify-content-between align-items-center"
            :class="{ selected: modalSelectedObjectType?.type === objType.type }"
            style="cursor: pointer;"
            @click="modalSelectedObjectType = objType"
          >
              <div class="d-flex align-items-center">
              <c-icon :name="objType.icon" class="me-2" />
              <div>{{ translateObjectLabel(objType.type, objType.name) }}</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="d-flex flex-column align-items-end">
                <div class="text-muted small">{{ objType.build_time_minutes }}m</div>
                <div class="text-success small fw-bold">{{ displayedTimes[objType.type] || objType.build_time_minutes }}m</div>
              </div>
              <c-icon v-if="modalSelectedObjectType?.type === objType.type" name="cilCheck" class="text-primary" />
            </div>
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3">
          <c-button color="secondary" size="sm" @click="(modalSelectedObjectType = null, showObjectModal = false)">{{ $t('city.cancel') }}</c-button>
          <c-button 
            color="primary" 
            size="sm" 
            :disabled="!modalSelectedObjectType || !selectedWorkerLevel || selectedWorkerCount === 0" 
            @click="confirmModalPlacement"
          >
            {{ $t('city.confirm') }}
          </c-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch, onUnmounted, inject } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '../stores/gameStore';
import axios from 'axios';

export default {
  name: 'ParcelEditor',
  setup() {
    const route = useRoute();
    const router = useRouter();
    const gameStore = useGameStore();
    const $t = inject('$t');

    const loading = ref(true);
    const message = ref('');
    const messageType = ref('');
    const cityObjects = ref([]);
    const selectedObjectType = ref(null);
  const showObjectModal = ref(false);
  const pendingSelectionBounds = ref(null);
    const modalSelectedObjectType = ref(null);
    const useFallbackModal = ref(true);

    const parcelId = computed(() => parseInt(route.params.parcelId));
    const parcel = computed(() => {
      return gameStore.parcels?.find(p => p.id === parcelId.value && p.user_id === gameStore.user?.id);
    });
    
    // Watch for route changes to refresh objects when returning to parcel
    watch(() => route.path, async (newPath, oldPath) => {
      if (newPath.includes('/city/') && !newPath.includes('/object/')) {
        await fetchCityObjects();
      }
    });

    const availableObjects = ref([]);
    const people = ref({ total: 0, by_level: {}, groups: [] });
    const selectedWorkerLevel = ref(null);
    const selectedWorkerCount = ref(0);
    const displayedTimes = ref({});

    const getObjectIcon = (type) => {
      const obj = availableObjects.value.find(o => o.type === type);
      return obj ? obj.icon : 'cilQuestion';
    };

    const fetchPeople = async () => {
      try {
        const res = await axios.get('/api/people');
        if (res.data.success) {
          people.value.total = res.data.total || 0;
          people.value.by_level = res.data.by_level || {};
          people.value.groups = res.data.groups || [];
        }
      } catch (e) {
        console.error('Failed to fetch people', e);
      }
    };

    const tr = (key, fallback) => {
      try {
        const v = $t(key);
        if (!v || v === key) return fallback || key;
        return v;
      } catch (e) {
        return fallback || key;
      }
    };

    const availableCountsForLevel = (level) => {
      const count = people.value.by_level?.[level] || 0;
      const arr = [];
      for (let i = 1; i <= count; i++) arr.push(i);
      return arr;
    };

    const availableCountForLevel = (level) => {
      return parseInt(people.value.by_level?.[level] || 0);
    };

    const updateDisplayedTimes = () => {
      const lvl = selectedWorkerLevel.value ? parseInt(selectedWorkerLevel.value) : 0;
      const cnt = selectedWorkerCount.value ? parseInt(selectedWorkerCount.value) : 0;
      const newTimes = {};
      availableObjects.value.forEach(o => {
        const base = o.build_time_minutes || 1;
  // Apply same formula as backend (use NEXT level = current + 1):
  // 1) increase base by next level
  // 2) reductionMinutes = (worker_level * count) - 1 (min 0)
  const objectLevel = (o.level || 0) + 1;
        if (lvl > 0 && cnt > 0) {
          const levelAdjusted = base * Math.max(1, objectLevel);
          let reductionMinutes = (lvl * cnt) - 1;
          if (reductionMinutes < 0) reductionMinutes = 0;
          newTimes[o.type] = Math.max(1, levelAdjusted - reductionMinutes);
        } else {
          newTimes[o.type] = base * Math.max(1, (o.level || 0) + 1);
        }
      });
      displayedTimes.value = newTimes;
    };

    const getAdjustedTime = computed(() => {
      return (objType) => {
        const base = objType.build_time_minutes || 1;
        const lvl = selectedWorkerLevel.value ? parseInt(selectedWorkerLevel.value) : 0;
        const cnt = selectedWorkerCount.value ? parseInt(selectedWorkerCount.value) : 0;
  const objectLevel = (objType.level || 0) + 1;
        if (lvl > 0 && cnt > 0) {
          const levelAdjusted = base * Math.max(1, objectLevel);
          let reductionMinutes = (lvl * cnt) - 1;
          if (reductionMinutes < 0) reductionMinutes = 0;
          return Math.max(1, levelAdjusted - reductionMinutes);
        }
        return base * Math.max(1, objectLevel);
      };
    });

    // when level changes, reset selected count (avoid stale selection)
    watch(selectedWorkerLevel, (newVal, oldVal) => {
      selectedWorkerCount.value = 0;
      updateDisplayedTimes();
    });

    const onSelectedWorkerLevelChange = () => {
      selectedWorkerCount.value = 0;
      updateDisplayedTimes();
    };

    watch(selectedWorkerCount, () => {
      updateDisplayedTimes();
    });

    const toggleCellSelection = async (cellIndex) => {
      // For single-cell buildings: directly open modal for this cell
      const cellX = (cellIndex - 1) % 10;
      const cellY = Math.floor((cellIndex - 1) / 10);
      
      // Check if cell is already occupied
      const existingObject = cityObjects.value.find(obj => 
        obj.parcel_id === parcel.value.id && obj.x === cellX && obj.y === cellY
      );
      
      if (existingObject) {
        // If occupied, select the object instead
        selectObject(existingObject);
        return;
      }
      
      // Open modal for building on this cell
      pendingSelectionBounds.value = { x: cellX, y: cellY };
      await Promise.all([fetchObjectTypes(), fetchPeople()]);
      selectedWorkerLevel.value = null;
      selectedWorkerCount.value = 0;
      updateDisplayedTimes();
      showObjectModal.value = true;
    };

    const clearSelection = () => {
      selectedCells.value.clear();
      selectedObjectType.value = null;
    };

    const isCellSelected = (cellIndex) => {
      return selectedCells.value.has(cellIndex);
    };

    // When user clicks Save and there is a selection, we open the modal and store selection bounds
    const placePendingObject = (objType) => {
      // deprecated: selection is handled via modal confirmed placement
    };

    const confirmModalPlacement = async () => {
      if (!pendingSelectionBounds.value || !modalSelectedObjectType.value) return;
      
      const cellX = pendingSelectionBounds.value.x;
      const cellY = pendingSelectionBounds.value.y;

      const props = {};
      if (selectedWorkerLevel.value && selectedWorkerCount.value) {
        props.workers = { level: parseInt(selectedWorkerLevel.value), count: parseInt(selectedWorkerCount.value) };
      }

      const newObj = {
        parcel_id: parcel.value.id,
        object_type: modalSelectedObjectType.value.type,
        x: cellX,
        y: cellY,
        properties: props  // Keep this for now, backend expects it
      };
      cityObjects.value.push(newObj);

      // close modal and clear modal selection
      modalSelectedObjectType.value = null;
      showObjectModal.value = false;
      pendingSelectionBounds.value = null;

      // Submit the new object to server so it is persisted and returns with ids
      await submitSave();
    };

    const submitSave = async () => {
      if (cityObjects.value.length === 0) {
        message.value = $t('city.no_objects_to_save');
        messageType.value = 'warning';
        return;
      }

      loading.value = true;
      try {
        // Send only objects belonging to the current parcel to avoid touching other parcels
        const objectsForThisParcel = cityObjects.value.filter(o => o.parcel_id === parcel.value.id);
        const res = await axios.post('/api/city-objects/save', {
          objects: objectsForThisParcel
        });
        if (res.data.success) {
          // Backend already handles clearing expired ready_at
          cityObjects.value = res.data.objects;
          message.value = $t('city.changes_saved');
          messageType.value = 'success';
        }
      } catch (e) {
        message.value = $t('city.save_error');
        messageType.value = 'error';
        console.error('Save error:', e.response?.data);
      } finally {
        loading.value = false;
      }
    };

    const isCellInObject = (cellIndex, obj) => {
      const cellX = (cellIndex - 1) % 10;
      const cellY = Math.floor((cellIndex - 1) / 10);

      return obj.x === cellX && obj.y === cellY;
    };

    const getObjectsForParcel = (parcelId) => {
      return cityObjects.value.filter(obj => obj.parcel_id === parcelId);
    };

    const selectObject = (obj) => {
      try {
        const url = `/city/${parcelId.value}/object/${obj.id}`;
        router.push(url);
      } catch (error) {
        console.error('Error in selectObject:', error);
      }
    };

    const removeObject = (obj) => {
      const index = cityObjects.value.findIndex(o => o.id === obj.id);
      if (index > -1) {
        cityObjects.value.splice(index, 1);
      }
      selectedObject.value = null;
    };

    const goBackToCity = () => {
      router.push('/city');
    };

    const fetchCityObjects = async () => {
      try {
        const res = await axios.get('/api/city-objects');
        if (res.data.success) {
          // Backend already handles clearing expired ready_at
          // Normalize numeric fields to avoid strict type mismatches
          cityObjects.value = (res.data.objects || []).map(o => {
            const copy = Object.assign({}, o);
            if (copy.id && /^\d+$/.test(String(copy.id))) copy.id = Number(copy.id);
            if (copy.parcel_id && /^\d+$/.test(String(copy.parcel_id))) copy.parcel_id = Number(copy.parcel_id);
            if (copy.user_id && /^\d+$/.test(String(copy.user_id))) copy.user_id = Number(copy.user_id);
            if (copy.x != null && /^\d+$/.test(String(copy.x))) copy.x = Number(copy.x);
            if (copy.y != null && /^\d+$/.test(String(copy.y))) copy.y = Number(copy.y);
            if (copy.ready_at != null && /^\d+$/.test(String(copy.ready_at))) copy.ready_at = Number(copy.ready_at);
            return copy;
          });
        }
      } catch (e) {
        console.error('Failed to fetch city objects', e);
      }
    };

    const fetchObjectTypes = async () => {
      try {
        const res = await axios.get('/api/object-types');
        if (res.data.success) {
          // expected array of types with fields: type, name, icon, build_time_minutes
          availableObjects.value = res.data.types.map(t => ({
            type: t.type,
            name: t.name,
            icon: t.icon || 'cilQuestion',
            build_time_minutes: t.build_time_minutes || 1,
            meta: t.meta || null
          }));
        }
      } catch (e) {
        console.error('Failed to fetch object types', e);
      }
    };

    const translateObjectLabel = (type, fallback) => {
      try {
        const key = 'city.' + type;
        const translated = $t(key);
        // If $t returns the key unchanged, fall back to provided name
        if (!translated || translated === key) {
          return fallback || key;
        }
        return translated;
      } catch (e) {
        return fallback || type;
      }
    };

    const getProgressPercent = (obj) => {
      const totalSeconds = obj.build_seconds || 60;
      if (!totalSeconds || !obj.ready_at) return 100;
      const ready = getReadyTimestamp(obj);
      if (!ready) return 100;
      const total = totalSeconds * 1000;
      const started = ready - total;
      const now = Date.now();
      const elapsed = Math.max(0, Math.min(now - started, total));
      return Math.floor((elapsed / total) * 100);
    };

    const getReadyTimestamp = (obj) => {
      // ready_at is already a timestamp in milliseconds
      if (obj.ready_at) {
        return obj.ready_at;
      }
      return null;
    };

    // Reactive current time for triggering re-renders
    const currentTime = ref(Date.now());

    const getRemainingTimeText = (obj) => {
      const ready = getReadyTimestamp(obj);
      if (!ready) return '';
      const remainingMs = Math.max(0, ready - currentTime.value);
      const sec = Math.ceil(remainingMs / 1000);
      const hours = Math.floor(sec / 3600);
      const minutes = Math.floor((sec % 3600) / 60);
      const seconds = sec % 60;
      const two = (n) => String(n).padStart(2, '0');
      if (hours > 0) {
        return `${hours}:${two(minutes)}:${two(seconds)}`;
      }
      return `${minutes}:${two(seconds)}`;
    };

    const isBuilding = (obj) => {
      const ready = getReadyTimestamp(obj);
      if (!ready) return false;
      return ready > currentTime.value;
    };
    
    // Tick every second to update progress displays
    let tickInterval = null;
    
    // Clear interval when unmounted
    onUnmounted(() => {
      if (tickInterval) clearInterval(tickInterval);
    });

    onMounted(async () => {
      if (gameStore.user) {
        await gameStore.fetchParcels();
        await fetchCityObjects();
        await fetchObjectTypes();
      }
      loading.value = false;
      
      // Start tick interval after loading
      tickInterval = setInterval(() => {
        currentTime.value = Date.now();
      }, 1000);
    });

    return {
      loading,
      message,
      messageType,
      parcel,
      cityObjects,
      availableObjects,
      toggleCellSelection,
    showObjectModal,
    modalSelectedObjectType,
    confirmModalPlacement,
    useFallbackModal,
      isCellInObject,
      getObjectsForParcel,
      selectObject,
      removeObject,
      goBackToCity,
      getObjectIcon,
      getProgressPercent,
      getRemainingTimeText,
      isBuilding
      ,
      people,
      selectedWorkerLevel,
      selectedWorkerCount,
      availableCountsForLevel,
      availableCountForLevel,
      onSelectedWorkerLevelChange,
      getAdjustedTime,
      displayedTimes,
      $t,
      tr,
      translateObjectLabel
    };
  }
}
</script>

<style scoped>
.parcel-editor-page {
  padding: 10px;
  max-width: 100vw;
  overflow-x: hidden;
}

.parcel-editor-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
  align-items: center;
}

.large-grid-container {
  width: 100%;
  max-width: 500px;
}

.large-grid {
  display: grid;
  grid-template-columns: repeat(10, 1fr);
  grid-template-rows: repeat(10, 1fr);
  gap: 1px;
  background: #e9ecef;
  padding: 1px;
  width: 100%;
  aspect-ratio: 1; /* Square grid */
  max-width: 500px;
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
  min-height: 30px; /* Minimum touch target */
  transition: background-color 0.2s;
}

.large-grid-cell:hover {
  background: rgba(0, 123, 255, 0.1);
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

/* Red icon when building */
.icon-building {
  color: #dc3545 !important;
}

/* Info badge for level/time */
.object-info-badge {
  position: absolute;
  bottom: 2px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 9px;
  font-weight: 600;
  pointer-events: none;
  display: flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
}

.building-badge {
  color: #dc3545;
  text-shadow: 0 0 2px rgba(255, 255, 255, 0.8);
}

.level-badge {
  color: #28a745;
  text-shadow: 0 0 2px rgba(255, 255, 255, 0.8);
}

.build-overlay-large { position:absolute; inset:0; display:flex; align-items:flex-end; }
.build-time-small { position:absolute; bottom:2px; right:6px; font-size:10px; color:#004085 }

.editor-palette {
  width: 100%;
  max-width: 500px;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .parcel-editor-page {
    padding: 5px;
  }

  .parcel-editor-content {
    gap: 15px;
  }

  .large-grid {
    max-width: 100vw;
    gap: 0.5px;
    padding: 0.5px;
  }

  .large-grid-cell {
    min-height: 25px;
  }

  .editor-palette {
    max-width: 100vw;
  }

  .object-palette {
    gap: 8px;
  }

  .palette-object {
    padding: 10px;
  }

  /* Visible selected styling for palette items */
  .palette-object.selected {
    background: rgba(0, 123, 255, 0.12);
    border: 1px solid rgba(0,123,255,0.35);
    border-radius: 6px;
  }

  .palette-object.selected div:first-child { font-weight: 600; }
}

/* Fallback modal styles */
.fallback-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}
.fallback-modal {
  background: white;
  padding: 16px;
  border-radius: 6px;
  width: 320px;
  max-width: 90%;
}
</style>