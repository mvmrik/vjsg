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
                      v-for="i in gridCellCount"
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
                        <!-- Render image when available filename is present; otherwise fallback to CoreUI icon -->
                        <template v-if="hasImageForType(obj.object_type) && !obj._imgError">
                          <img
                            :src="`/images/objects/${encodeURIComponent(getIconForType(obj.object_type))}`"
                            :alt="obj.object_type"
                            class="object-image-large"
                            @error="onObjectImageError(obj)"
                          />
                        </template>
                        <template v-else>
                          <c-icon 
                            :name="getObjectIcon(obj.object_type)" 
                            :class="{ 'icon-building': isBuilding(obj) }"
                          />
                        </template>
                        <!-- Show time when building, level when ready (visible on all screen sizes) -->
                        <div v-if="isBuilding(obj)" class="object-info-badge building-badge">
                          {{ getRemainingTimeText(obj) }}
                        </div>
                        <div v-else class="object-info-badge level-badge">
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
        <!-- Workers selection -->
        <div class="mb-4">
          <h6 class="mb-3">{{ $t('city.workers') }}</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small">{{ $t('city.worker_level') }}</label>
              <select class="form-select" v-model="selectedWorkerLevel" @change="onSelectedWorkerLevelChange">
                <option :value="null">{{ $t('city.no_workers') }}</option>
                <option v-for="(count, lvl) in people.by_level" :key="lvl" :value="lvl">LV {{ lvl }} ({{ count }} {{ $t('city.available') }})</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small">{{ $t('city.worker_count') }}</label>
              <div class="d-flex align-items-center gap-2">
                <input
                  type="range"
                  :min="0"
                  :max="availableCountForLevel(selectedWorkerLevel)"
                  v-model.number="selectedWorkerCount"
                  class="form-range flex-grow-1"
                />
                <span class="badge bg-primary">{{ selectedWorkerCount }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Object selection -->
        <h6 class="mb-3">{{ $t('city.select_object_type') }}</h6>
        <div class="object-grid-selection">
          <div
            v-for="objType in availableObjects"
            :key="objType.type"
            class="object-card"
            :class="{ 
              'selected': modalSelectedObjectType?.type === objType.type,
              'insufficient': !canAfford(objType)
            }"
            @click="modalSelectedObjectType = objType"
          >
            <div class="object-card-header">
              <div class="d-flex align-items-center gap-2">
                <template v-if="isImageFilename(objType.icon)">
                  <img :src="`/images/objects/${encodeURIComponent(objType.icon)}`" class="object-icon" alt="obj" @error="(e)=>{ e.target.style.display='none' }" />
                </template>
                <template v-else>
                  <c-icon :name="objType.icon" size="xl" />
                </template>
                <div>
                  <div class="fw-bold">{{ translateObjectLabel(objType.type, objType.name) }}</div>
                  <div class="text-muted small">
                    <c-icon name="cilClock" size="sm" /> {{ formatBuildTime(displayedTimes[objType.type] || objType.build_time_minutes) }}
                  </div>
                </div>
              </div>
              <c-icon v-if="modalSelectedObjectType?.type === objType.type" name="cilCheckCircle" class="text-success" size="xl" />
            </div>
            
            <div v-if="objType.recipe" class="object-card-materials mt-2">
              <div class="small fw-bold mb-1">{{ $t('city.required_materials') }}:</div>
              <div v-for="(qty, tid) in objType.recipe" :key="tid" class="material-item">
                <span class="material-name">{{ toolTypes[tid] ? translateToolName(toolTypes[tid].name) : ('ID:' + tid) }}</span>
                <span class="material-count" :class="{ 'text-danger': ((inventories[tid]?.count || 0) - (inventories[tid]?.reserved_count || 0)) < qty }">
                  <span class="needed">{{ qty }}</span>
                  <span class="separator">/</span>
                  <span class="available">{{ Math.max(0, (inventories[tid]?.count || 0) - (inventories[tid]?.reserved_count || 0)) }}</span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Selected object summary -->
        <div v-if="modalSelectedObjectType" class="alert alert-info mt-3">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <strong>{{ translateObjectLabel(modalSelectedObjectType.type, modalSelectedObjectType.name) }}</strong>
              <span v-if="!canAfford(modalSelectedObjectType)" class="text-danger ms-2">
                <c-icon name="cilWarning" /> {{ $t('city.insufficient_materials') }}
              </span>
            </div>
          </div>
        </div>
      </c-modal-body>
      <c-modal-footer>
        <c-button color="secondary" @click="(modalSelectedObjectType = null, showObjectModal = false)">{{ $t('city.cancel') }}</c-button>
        <c-button 
          color="primary" 
          :disabled="!canStartBuild" 
          @click="confirmModalPlacement"
        >
          {{ $t('city.confirm') }}
        </c-button>
      </c-modal-footer>
    </c-modal>

    <!-- Fallback modal (simple overlay) -->
    <div v-if="showObjectModal && useFallbackModal" class="fallback-backdrop" @click.self="(modalSelectedObjectType = null, showObjectModal = false)">
      <div class="fallback-modal">
        <div class="modal-header-custom mb-3">
          <h5 class="mb-0">{{ $t('city.select_object_type') }}</h5>
          <button class="btn-close" @click="(modalSelectedObjectType = null, showObjectModal = false)"></button>
        </div>

        <!-- Workers selection -->
        <div class="mb-4">
          <h6 class="mb-2 small fw-bold text-uppercase">{{ $t('city.workers') }}</h6>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small mb-1">{{ $t('city.worker_level') }}</label>
              <select class="form-select form-select-sm" v-model="selectedWorkerLevel" @change="onSelectedWorkerLevelChange">
                <option :value="null">{{ $t('city.no_workers') }}</option>
                <option v-for="(count, lvl) in people.by_level" :key="lvl + '-fb'" :value="lvl">
                  LV {{ lvl }} ({{ count }} {{ $t('city.available') }})
                </option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small mb-1">{{ $t('city.worker_count') }}</label>
              <div class="d-flex align-items-center gap-2">
                <input
                  type="range"
                  :min="0"
                  :max="availableCountForLevel(selectedWorkerLevel)"
                  v-model.number="selectedWorkerCount"
                  class="form-range flex-grow-1"
                />
                <span class="badge bg-primary">{{ selectedWorkerCount }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Object selection -->
        <h6 class="mb-2 small fw-bold text-uppercase">{{ $t('city.select_object_type') }}</h6>
        <div class="fallback-object-grid">
          <div
            v-for="objType in availableObjects"
            :key="objType.type + '-fb'"
            class="fallback-object-card"
            :class="{ 
              'selected': modalSelectedObjectType?.type === objType.type,
              'insufficient': !canAfford(objType)
            }"
            @click="modalSelectedObjectType = objType"
          >
            <div class="d-flex align-items-start gap-2 mb-2">
              <template v-if="isImageFilename(objType.icon)">
                <img :src="`/images/objects/${encodeURIComponent(objType.icon)}`" class="fallback-obj-icon" alt="obj" @error="(e)=>{ e.target.style.display='none' }" />
              </template>
              <template v-else>
                <c-icon :name="objType.icon" size="lg" />
              </template>
              <div class="flex-grow-1">
                <div class="fw-bold small">{{ translateObjectLabel(objType.type, objType.name) }}</div>
                <div class="text-muted" style="font-size: 0.75rem;">
                  <c-icon name="cilClock" size="sm" /> {{ formatBuildTime(displayedTimes[objType.type] || objType.build_time_minutes) }}
                </div>
              </div>
              <c-icon v-if="modalSelectedObjectType?.type === objType.type" name="cilCheckCircle" class="text-success" />
            </div>
            
            <div v-if="objType.recipe" class="fallback-materials" style="font-size: 0.75rem;">
              <div class="fw-bold mb-1">{{ $t('city.required_materials') }}:</div>
              <div v-for="(qty, tid) in objType.recipe" :key="tid" class="d-flex justify-content-between">
                <span class="text-truncate me-2">{{ toolTypes[tid] ? translateToolName(toolTypes[tid].name) : ('ID:' + tid) }}</span>
                <span :class="{ 'text-danger fw-bold': ((inventories[tid]?.count || 0) - (inventories[tid]?.reserved_count || 0)) < qty, 'text-success': ((inventories[tid]?.count || 0) - (inventories[tid]?.reserved_count || 0)) >= qty }">
                  {{ qty }}/{{ Math.max(0, (inventories[tid]?.count || 0) - (inventories[tid]?.reserved_count || 0)) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Selected summary -->
        <div v-if="modalSelectedObjectType" class="alert alert-info mt-3 mb-0 py-2 small">
          <strong>{{ translateObjectLabel(modalSelectedObjectType.type, modalSelectedObjectType.name) }}</strong>
          <span v-if="!canAfford(modalSelectedObjectType)" class="text-danger ms-2">
            <c-icon name="cilWarning" /> {{ $t('city.insufficient_materials') }}
          </span>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
          <button class="btn btn-secondary btn-sm" @click="(modalSelectedObjectType = null, showObjectModal = false)">
            {{ $t('city.cancel') }}
          </button>
          <button 
            class="btn btn-primary btn-sm" 
            :disabled="!modalSelectedObjectType || !selectedWorkerLevel || selectedWorkerCount === 0 || !canAfford(modalSelectedObjectType)"
            @click="confirmModalPlacement"
          >
            {{ $t('city.confirm') }}
          </button>
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
    
    // Grid size for parcel editor
    const GRID_SIZE = 5;
    const gridCellCount = GRID_SIZE * GRID_SIZE;

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

    const getIconForType = (type) => {
      const obj = availableObjects.value.find(o => o.type === type);
      return obj ? obj.icon : null;
    };

    const isImageFilename = (name) => {
      if (!name || typeof name !== 'string') return false;
      return /\.(png|jpe?g|svg|webp|gif)$/i.test(name) || name.includes('.');
    };

    const hasImageForType = (type) => {
      const icon = getIconForType(type);
      return isImageFilename(icon);
    };

    const onObjectImageError = (obj) => {
      try {
        // mark to avoid retrying image and fall back to icon
        obj._imgError = true;
        console.warn('Failed to load object image for', obj.object_type, 'icon=', getIconForType(obj.object_type));
      } catch (e) {
        console.warn('onObjectImageError handler failed', e);
      }
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

    // Format minutes to human-friendly string: minutes, hours or days+hours
    const formatBuildTime = (minutes) => {
      minutes = parseInt(minutes) || 0;
      if (minutes <= 0) return '0m';
      const minsInDay = 1440; // 60*24
      if (minutes >= minsInDay) {
        const days = Math.floor(minutes / minsInDay);
        const rem = minutes % minsInDay;
        const hours = Math.floor(rem / 60);
        if (hours > 0) return `${days}d ${hours}h`;
        return `${days}d`;
      }
      if (minutes >= 60) {
        const hrs = Math.floor(minutes / 60);
        const rem = minutes % 60;
        if (rem > 0) return `${hrs}h ${rem}m`;
        return `${hrs}h`;
      }
      return `${minutes}m`;
    };

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
  const cellX = (cellIndex - 1) % GRID_SIZE;
  const cellY = Math.floor((cellIndex - 1) / GRID_SIZE);
      
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
      // Load inventories so we can display available counts next to required materials
      try {
        const inv = await axios.get('/api/inventories');
        inventories.value = (inv.data.items || []).reduce((acc, it) => {
          acc[it.tool_type_id] = it;
          return acc;
        }, {});
      } catch (e) {
        inventories.value = {};
      }
      // Load tool types to resolve ids -> names
      try {
        const res = await axios.get('/api/tool-types');
        if (res.data && res.data.success && Array.isArray(res.data.tool_types)) {
          toolTypes.value = res.data.tool_types.reduce((acc, t) => {
            acc[t.id] = t;
            return acc;
          }, {});
        } else if (res.data && Array.isArray(res.data)) {
          // fallback: some endpoints return plain arrays
          toolTypes.value = res.data.reduce((acc, t) => { acc[t.id] = t; return acc; }, {});
        }
      } catch (e) {
        toolTypes.value = {};
      }
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
          // Backend returns the authoritative list of objects; merge them with local list
          // so transient client-side objects (e.g., pending new ones) are replaced with
          // server versions that include ids and timestamps, but we avoid briefly
          // emptying the array which caused the UI to flicker.
          const serverObjs = (res.data.objects || []).map(o => {
            const copy = Object.assign({}, o);
            if (copy.id && /^\d+$/.test(String(copy.id))) copy.id = Number(copy.id);
            if (copy.parcel_id && /^\d+$/.test(String(copy.parcel_id))) copy.parcel_id = Number(copy.parcel_id);
            if (copy.user_id && /^\d+$/.test(String(copy.user_id))) copy.user_id = Number(copy.user_id);
            if (copy.x != null && /^\d+$/.test(String(copy.x))) copy.x = Number(copy.x);
            if (copy.y != null && /^\d+$/.test(String(copy.y))) copy.y = Number(copy.y);
            if (copy.ready_at != null && /^\d+$/.test(String(copy.ready_at))) copy.ready_at = Number(copy.ready_at);
            if (copy.level == null) copy.level = 1; // ensure level exists
            return copy;
          });

          // Replace only objects for this parcel to avoid touching other parcels in memory
          cityObjects.value = cityObjects.value.filter(o => o.parcel_id !== parcel.value.id).concat(serverObjs);

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
      const cellX = (cellIndex - 1) % GRID_SIZE;
      const cellY = Math.floor((cellIndex - 1) / GRID_SIZE);

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
            meta: t.meta || null,
            recipe: t.recipe || null
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
  const inventories = ref({});
    const toolTypes = ref({});

    const translateToolName = (name) => {
      try {
        const translated = $t ? $t(`tools.types.${name}`) : name;
        if (!translated || translated === `tools.types.${name}`) return name;
        return translated;
      } catch (e) {
        return name;
      }
    };

    // Check if user can afford the recipe materials
    const canAfford = (objType) => {
      if (!objType || !objType.recipe) return true;
      for (const [toolTypeId, qty] of Object.entries(objType.recipe)) {
        const inv = inventories.value[toolTypeId];
        const available = inv ? Math.max(0, (parseInt(inv.count) || 0) - (parseInt(inv.reserved_count) || 0)) : 0;
        if (available < qty) return false;
      }
      return true;
    };

    // Check if user can start build
    const canStartBuild = computed(() => {
      if (!modalSelectedObjectType.value) return false;
      if (!selectedWorkerLevel.value || selectedWorkerCount.value <= 0) return false;
      return canAfford(modalSelectedObjectType.value);
    });

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
      getIconForType,
      isImageFilename,
      hasImageForType,
      onObjectImageError,
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
  formatBuildTime,
      $t,
      gridCellCount,
      tr,
      translateObjectLabel
      ,
      inventories,
      toolTypes,
      translateToolName,
      canAfford,
      canStartBuild
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
  grid-template-columns: repeat(5, 1fr);
  grid-template-rows: repeat(5, 1fr);
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

/* Reserve space above the badge so the image is visually centered relative to the level strip */
.placed-object-large {
  padding-bottom: 18px;
}

/* Images inside parcel cells should fit the cell */
.object-image-large {
  max-width: 55%;
  max-height: 55%;
  object-fit: contain;
  display: block;
}

/* Small preview in palette */
.object-palette-image {
  width: 28px;
  height: 28px;
  object-fit: contain;
  display: inline-block;
}

/* Red icon when building */
.icon-building {
  color: #dc3545 !important;
}

/* Info badge for level/time */
.object-info-badge {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 11px;
  font-weight: 600;
  pointer-events: none;
  z-index: 5;
  padding: 3px 6px; /* height just enough for text */
  /* full-width strip background; default light so it's readable */
  background: rgba(255,255,255,0.95);
  color: #222;
}

.building-badge {
  background: rgba(220,53,69,0.95) !important; /* solid red strip */
  color: #fff !important;
}

.level-badge {
  background: rgba(40,167,69,0.95) !important; /* solid green strip */
  color: #fff !important;
}

.build-overlay-large { position:absolute; inset:0; display:flex; align-items:flex-end; }
.build-time-small { position:absolute; bottom:2px; right:6px; font-size:10px; color:#004085 }

.editor-palette {
  width: 100%;
  max-width: 500px;
}

/* Modern object selection grid */
.object-grid-selection {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
  max-height: 400px;
  overflow-y: auto;
  padding: 4px;
}

.object-card {
  border: 2px solid #dee2e6;
  border-radius: 8px;
  padding: 12px;
  cursor: pointer;
  transition: all 0.2s;
  background: white;
}

.object-card:hover {
  border-color: #0d6efd;
  box-shadow: 0 2px 8px rgba(13, 110, 253, 0.2);
  transform: translateY(-2px);
}

.object-card.selected {
  border-color: #198754;
  background: rgba(25, 135, 84, 0.05);
  box-shadow: 0 2px 12px rgba(25, 135, 84, 0.3);
}

.object-card.insufficient {
  border-color: #dc3545;
  background: rgba(220, 53, 69, 0.05);
  opacity: 0.75;
}

.object-card.insufficient .object-card-header {
  opacity: 0.7;
}

.object-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.object-icon {
  width: 32px;
  height: 32px;
  object-fit: contain;
}

.object-card-materials {
  padding-top: 8px;
  border-top: 1px solid #e9ecef;
}

.material-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
  font-size: 0.875rem;
}

.material-name {
  color: #495057;
  flex: 1;
}

.material-count {
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
  color: #198754;
}

.material-count.text-danger {
  color: #dc3545 !important;
}

.material-count .needed {
  color: #495057;
}

.material-count .separator {
  color: #adb5bd;
  font-weight: normal;
}

.material-count .available {
  min-width: 30px;
  text-align: right;
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

  .object-grid-selection {
    grid-template-columns: 1fr;
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
  padding: 20px;
  border-radius: 8px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.modal-header-custom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 12px;
  border-bottom: 1px solid #e9ecef;
}

.fallback-object-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 12px;
  max-height: 400px;
  overflow-y: auto;
  padding: 4px;
}

.fallback-object-card {
  border: 2px solid #dee2e6;
  border-radius: 6px;
  padding: 10px;
  cursor: pointer;
  transition: all 0.2s;
  background: white;
}

.fallback-object-card:hover {
  border-color: #0d6efd;
  box-shadow: 0 2px 6px rgba(13, 110, 253, 0.2);
  transform: translateY(-1px);
}

.fallback-object-card.selected {
  border-color: #198754;
  background: rgba(25, 135, 84, 0.05);
  box-shadow: 0 2px 10px rgba(25, 135, 84, 0.3);
}

.fallback-object-card.insufficient {
  border-color: #dc3545;
  background: rgba(220, 53, 69, 0.03);
  opacity: 0.75;
}

.fallback-obj-icon {
  width: 28px;
  height: 28px;
  object-fit: contain;
}

.fallback-materials {
  padding-top: 8px;
  border-top: 1px solid #e9ecef;
  margin-top: 8px;
}

@media (max-width: 768px) {
  .fallback-modal {
    width: 95%;
    padding: 15px;
  }
  
  .fallback-object-grid {
    grid-template-columns: 1fr;
  }
}
</style>