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
                  Редактиране на парцел
                </h5>
                <div class="d-flex gap-2">
                  <c-button color="secondary" @click="goBackToCity">
                    <c-icon name="cilArrowLeft" class="me-1" />
                    Обратно към града
                  </c-button>
                </div>
              </div>
            </c-card-header>
            <c-card-body>
              <div v-if="loading" class="text-center">
                <c-spinner />
                <p>Зареждане...</p>
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
                        <c-icon :name="getObjectIcon(obj.object_type)" />
                        <div v-if="isBuilding(obj)" class="build-overlay-large">
                          <div class="build-time-small">{{ getRemainingTimeText(obj) }}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Object selection moved to modal: keep selection instructions -->
                <div class="editor-palette">
                  <h6>Обекти</h6>
                  <div class="mt-3">
                    <p class="text-muted small">
                      <c-icon name="cilInfo" class="me-1" />
                      Кликнете върху празна клетка в мрежата, за да изберете тип обект.
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
        <c-modal-title>Изберете тип обект</c-modal-title>
      </c-modal-header>
      <c-modal-body>
        <div class="mb-3 d-flex gap-2">
          <div>
            <label class="form-label small mb-1">Ниво на работниците</label>
            <select class="form-select" v-model="selectedWorkerLevel">
              <option :value="null">(без работници)</option>
              <option v-for="(count, lvl) in people.by_level" :key="lvl" :value="lvl">LV {{ lvl }} ({{ count }})</option>
            </select>
          </div>
          <div>
            <label class="form-label small mb-1">Брой работници</label>
            <select class="form-select" v-model.number="selectedWorkerCount">
              <option :value="0">0</option>
              <option v-for="n in availableCountsForLevel(selectedWorkerLevel)" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
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
              <div>{{ objType.name }}</div>
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
        <c-button color="secondary" @click="(modalSelectedObjectType = null, showObjectModal = false)">Откажи</c-button>
        <c-button 
          color="primary" 
          :disabled="!modalSelectedObjectType || !selectedWorkerLevel || selectedWorkerCount === 0" 
          @click="confirmModalPlacement"
        >
          Потвърди
        </c-button>
      </c-modal-footer>
    </c-modal>

    <!-- Fallback modal (simple overlay) -->
    <div v-if="showObjectModal && useFallbackModal" class="fallback-backdrop" @click.self="(modalSelectedObjectType = null, showObjectModal = false)">
      <div class="fallback-modal">
        <h5>Изберете тип обект</h5>
        <div class="mb-3 d-flex gap-2">
          <div>
            <label class="form-label small mb-1">Ниво на работниците</label>
            <select class="form-select" v-model="selectedWorkerLevel">
              <option :value="null">(без работници)</option>
              <option v-for="(count, lvl) in people.by_level" :key="lvl + '-fb'" :value="lvl">LV {{ lvl }} ({{ count }})</option>
            </select>
          </div>
          <div>
            <label class="form-label small mb-1">Брой работници</label>
            <select class="form-select" v-model.number="selectedWorkerCount">
              <option :value="0">0</option>
              <option v-for="n in availableCountsForLevel(selectedWorkerLevel)" :key="'fb-' + n" :value="n">{{ n }}</option>
            </select>
          </div>
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
              <div>{{ objType.name }}</div>
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
          <c-button color="secondary" size="sm" @click="(modalSelectedObjectType = null, showObjectModal = false)">Откажи</c-button>
          <c-button 
            color="primary" 
            size="sm" 
            :disabled="!modalSelectedObjectType || !selectedWorkerLevel || selectedWorkerCount === 0" 
            @click="confirmModalPlacement"
          >
            Потвърди
          </c-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '../stores/gameStore';
import axios from 'axios';

export default {
  name: 'ParcelEditor',
  setup() {
    const route = useRoute();
    const router = useRouter();
    const gameStore = useGameStore();

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

    const availableCountsForLevel = (level) => {
      const count = people.value.by_level?.[level] || 0;
      const arr = [];
      for (let i = 1; i <= count; i++) arr.push(i);
      return arr;
    };

    const updateDisplayedTimes = () => {
      const lvl = selectedWorkerLevel.value ? parseInt(selectedWorkerLevel.value) : 0;
      const cnt = selectedWorkerCount.value ? parseInt(selectedWorkerCount.value) : 0;
      const newTimes = {};
      availableObjects.value.forEach(o => {
        const base = o.build_time_minutes || 1;
        if (lvl > 0 && cnt > 0) {
          const reduction = lvl * cnt;
          newTimes[o.type] = Math.max(1, base - reduction);
        } else {
          newTimes[o.type] = base;
        }
      });
      displayedTimes.value = newTimes;
    };

    const getAdjustedTime = computed(() => {
      return (objType) => {
        const base = objType.build_time_minutes || 1;
        const lvl = selectedWorkerLevel.value ? parseInt(selectedWorkerLevel.value) : 0;
        const cnt = selectedWorkerCount.value ? parseInt(selectedWorkerCount.value) : 0;
        if (lvl > 0 && cnt > 0) {
          const reduction = lvl * cnt;
          return Math.max(1, base - reduction);
        }
        return base;
      };
    });

    // when level changes, reset selected count (avoid stale selection)
    watch(selectedWorkerLevel, (newVal, oldVal) => {
      selectedWorkerCount.value = 0;
      updateDisplayedTimes();
    });

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
        properties: props
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
        message.value = 'Няма обекти за запазване. Първо изберете тип обект и го поставете.';
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
          // Post-process returned objects: null out ready_at for expired builds
          const objs = res.data.objects.map(o => {
            const ready = getReadyTimestamp(o);
            if (ready && ready <= Date.now()) {
              o.ready_at = null;
            }
            return o;
          });
          cityObjects.value = objs;
          message.value = 'Промените са запазени успешно!';
          messageType.value = 'success';
        }
      } catch (e) {
        message.value = 'Грешка при запазване';
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
        console.log('selectObject called with obj:', obj);
        console.log('parcelId.value:', parcelId.value);
        const url = `/city/${parcelId.value}/object/${obj.id}`;
        console.log('navigating to:', url);
        router.push(url);
        console.log('navigation successful');
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
          const objs = res.data.objects.map(o => {
            const ready = getReadyTimestamp(o);
            if (ready && ready <= Date.now()) {
              o.ready_at = null;
            }
            return o;
          });
          cityObjects.value = objs;
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
      if (obj.ready_at) {
        const t = Date.parse(obj.ready_at);
        if (!isNaN(t)) return t;
      }
      if (obj.created_at) {
        const created = Date.parse(obj.created_at);
        const totalSeconds = obj.build_seconds || 60;
        if (!isNaN(created) && totalSeconds) return created + totalSeconds * 1000;
      }
      return null;
    };

    const getRemainingTimeText = (obj) => {
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

    // Tick every second to update progress displays
    let tickInterval = null;
    onMounted(() => {
      tickInterval = setInterval(() => {
        cityObjects.value = cityObjects.value.slice();
      }, 1000);
    });
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
      getAdjustedTime,
      displayedTimes
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