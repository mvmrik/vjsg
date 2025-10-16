<template>
  <div class="object-editor-page">
    <c-container fluid>
      <c-row>
        <c-col>
          <c-card>
            <c-card-header>
              <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                  <c-icon name="cilBuilding" class="me-2" />
                  Редактиране на обект
                </h5>
                <div class="d-flex gap-2">
                  <c-button color="secondary" @click="goBackToParcel">
                    <c-icon name="cilArrowLeft" class="me-1" />
                    Обратно към парцел
                  </c-button>
                </div>
              </div>
            </c-card-header>
            <c-card-body>
              <div v-if="loading" class="text-center">
                <c-spinner />
                <p>Зареждане...</p>
              </div>

              <div v-else-if="object" class="object-editor-content">
                <!-- Object Grid for editing -->
                <div class="object-grid-container">
                  <div class="object-grid">
                    <div
                      v-for="i in 100"
                      :key="i"
                      class="object-grid-cell"
                    >
                      <!-- Placeholder for future furniture -->
                      <div class="empty-cell">
                        <c-icon name="cilPlus" class="text-muted" />
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Object info -->
                <div class="object-info mt-4">
                  <h6>Информация за обекта</h6>
                  <div class="row">
                    <div class="col-md-6">
                      <p class="mb-1"><strong>Тип:</strong> {{ getObjectTypeName(object.object_type) }}</p>
                      <p class="mb-1"><strong>Ниво:</strong> {{ object.level || 1 }}</p>
                    </div>
                    <div class="col-md-6">
                      <p v-if="!object.ready_at || remainingTimeText === ''" class="mb-1"><strong>Статус:</strong> Готов</p>
                      <p v-else class="mb-1"><strong>Оставащо време:</strong> {{ remainingTimeText }}</p>
                    </div>
                  </div>
                  <div class="mt-3" v-if="!isBuilding(object)">
                    <c-button color="primary" @click="showUpgradeModal = true">
                      <c-icon name="cilArrowTop" class="me-1" />
                      Обновяване на ниво
                    </c-button>
                  </div>
                </div>
              </div>
            </c-card-body>
          </c-card>
        </c-col>
      </c-row>
    </c-container>

    <!-- Upgrade Modal -->
    <div v-if="showUpgradeModal" class="upgrade-modal-overlay" @click="showUpgradeModal = false">
      <div class="upgrade-modal-content" @click.stop>
        <div class="modal-header">
          <h5>Обновяване на ниво</h5>
          <button type="button" class="btn-close" @click="showUpgradeModal = false"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">Изберете работници за обновяване на нивото на обекта.</p>
          <div class="d-flex gap-2 mb-3">
            <div>
              <label class="form-label small mb-1">Ниво на работниците</label>
              <select class="form-select" v-model="upgradeWorkerLevel">
                <option v-for="(count, lvl) in people.by_level" :key="lvl" :value="lvl">LV {{ lvl }} ({{ count }})</option>
              </select>
            </div>
            <div>
              <label class="form-label small mb-1">Брой работници</label>
              <select class="form-select" v-model.number="upgradeWorkerCount">
                <option :value="0">0</option>
                <option v-for="n in availableCountsForLevel(upgradeWorkerLevel)" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
          </div>
          <div v-if="upgradeWorkerLevel && upgradeWorkerCount" class="alert alert-info">
            <strong>Време за обновяване:</strong> {{ upgradeTimeMinutes }} минути
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showUpgradeModal = false">Откажи</button>
          <button 
            type="button" 
            class="btn btn-primary" 
            :disabled="!upgradeWorkerLevel || !upgradeWorkerCount || upgradeWorkerCount <= 0"
            @click="startUpgrade"
          >
            Започни обновяване
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '../stores/gameStore';
import axios from 'axios';

export default {
  name: 'ObjectEditor',
  setup() {
    const route = useRoute();
    const router = useRouter();
    const gameStore = useGameStore();

    const loading = ref(true);
    const cityObjects = ref([]);
    const showUpgradeModal = ref(false);
    const upgradeWorkerLevel = ref(null);
    const upgradeWorkerCount = ref(0);
    const people = ref({ total: 0, by_level: {}, groups: [] });
    const currentTime = ref(Date.now()); // For reactive time updates
    let tickInterval = null;

    const parcelId = computed(() => parseInt(route.params.parcelId));
    const objectId = computed(() => parseInt(route.params.objectId));

    const parcel = computed(() => {
      return gameStore.parcels?.find(p => p.id === parcelId.value && p.user_id === gameStore.user?.id);
    });

    const object = computed(() => {
      return cityObjects.value.find(obj => obj.id === objectId.value);
    });

    const getObjectIcon = (type) => {
      // This would need to be implemented if we want icons
      return 'cilQuestion';
    };

    const availableObjects = ref([]);

    const getObjectTypeName = (type) => {
      const obj = availableObjects.value.find(o => o.type === type);
      return obj ? obj.name : 'Непознат';
    };

    const isBuilding = (obj) => {
      if (!obj) return false;
      const ready = getReadyTimestamp(obj);
      if (!ready) return false;
      return ready > Date.now();
    };

    const getReadyTimestamp = (obj) => {
      // ready_at is already a timestamp in milliseconds
      if (!obj || !obj.ready_at) return null;
      return obj.ready_at;
    };

    const remainingTimeText = computed(() => {
      if (!object.value || !object.value.ready_at) return '';
      const ready = object.value.ready_at; // Already in milliseconds
      const remaining = Math.max(0, ready - currentTime.value);
      if (remaining === 0) return '';
      const totalSeconds = Math.ceil(remaining / 1000);
      const minutes = Math.floor(totalSeconds / 60);
      const seconds = totalSeconds % 60;
      return `${minutes}m ${seconds}s`;
    });

    const goBackToParcel = () => {
      router.push(`/city/${parcelId.value}`);
    };

    const fetchCityObjects = async () => {
      try {
        const res = await axios.get('/api/city-objects');
        if (res.data.success) {
          cityObjects.value = res.data.objects;
        }
      } catch (e) {
        console.error('Failed to fetch city objects', e);
      }
    };

    const fetchObjectTypes = async () => {
      try {
        const res = await axios.get('/api/object-types');
        if (res.data.success) {
          availableObjects.value = res.data.types || [];
        }
      } catch (e) {
        console.error('Failed to fetch object types', e);
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

    const availableCountsForLevel = (level) => {
      const count = people.value.by_level?.[level] || 0;
      const arr = [];
      for (let i = 1; i <= count; i++) arr.push(i);
      return arr;
    };

    const upgradeTimeMinutes = computed(() => {
      // Base time is the same as build time of the object type (same as building)
      const objectType = availableObjects.value.find(o => o.type === object.value?.object_type);
      const baseTime = objectType ? objectType.build_time_minutes : 10;
      const lvl = upgradeWorkerLevel.value ? parseInt(upgradeWorkerLevel.value) : 0;
      const cnt = upgradeWorkerCount.value ? parseInt(upgradeWorkerCount.value) : 0;
      if (lvl > 0 && cnt > 0) {
        const reduction = lvl * cnt;
        return Math.max(1, baseTime - reduction);
      }
      return baseTime;
    });

    const startUpgrade = async () => {
      try {
        const res = await axios.post('/api/city-objects/upgrade', {
          object_id: objectId.value,
          worker_level: upgradeWorkerLevel.value,
          worker_count: upgradeWorkerCount.value
        });
        
        if (res.data.success) {
          showUpgradeModal.value = false;
          upgradeWorkerLevel.value = null;
          upgradeWorkerCount.value = 0;
          
          // Update object locally with all data from response
          const objIndex = cityObjects.value.findIndex(obj => obj.id === objectId.value);
          if (objIndex !== -1) {
            cityObjects.value[objIndex].ready_at = res.data.object.ready_at;
            cityObjects.value[objIndex].level = res.data.object.level;
            cityObjects.value[objIndex].build_seconds = res.data.object.build_seconds;
            // Force reactivity update
            cityObjects.value = [...cityObjects.value];
          }
          
          // Refresh people data since workers are now occupied
          await fetchPeople();
        } else {
          console.error('Upgrade failed:', res.data.message);
          alert('Upgrade failed: ' + res.data.message);
        }
      } catch (e) {
        console.error('Failed to start upgrade', e);
        if (e.response && e.response.data && e.response.data.message) {
          alert('Error: ' + e.response.data.message);
        } else {
          alert('Failed to start upgrade. Check console for details.');
        }
      }
    };

    onMounted(async () => {
      if (gameStore.user) {
        await gameStore.fetchParcels();
        await fetchCityObjects();
        await fetchObjectTypes();
        await fetchPeople();
      }
      loading.value = false;

      // Tick every second to update countdown displays
      tickInterval = setInterval(() => {
        currentTime.value = Date.now();
        
        // Clear ready_at when time expires
        cityObjects.value.forEach(obj => {
          if (obj.ready_at) {
            const ready = new Date(obj.ready_at).getTime();
            if (ready <= Date.now()) {
              obj.ready_at = null;
            }
          }
        });
      }, 1000);
    });
    // Clear interval when unmounted
    onUnmounted(() => {
      if (tickInterval) clearInterval(tickInterval);
    });

    return {
      loading,
      parcel,
      object,
      showUpgradeModal,
      upgradeWorkerLevel,
      upgradeWorkerCount,
      people,
      upgradeTimeMinutes,
      goBackToParcel,
      getObjectTypeName,
      isBuilding,
      remainingTimeText,
      availableCountsForLevel,
      startUpgrade
    };
  }
}
</script>

<style scoped>
.object-editor-page {
  padding: 10px;
  max-width: 100vw;
  overflow-x: hidden;
}

.object-editor-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
  align-items: center;
}

.object-grid-container {
  width: 100%;
  max-width: 500px;
}

.object-grid {
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

.object-grid-cell {
  background: white;
  border: 1px solid #dee2e6;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 30px; /* Minimum touch target */
}

.empty-cell {
  color: #6c757d;
  font-size: 1.2rem;
}

.object-info {
  width: 100%;
  max-width: 500px;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .object-editor-page {
    padding: 5px;
  }

  .object-editor-content {
    gap: 15px;
  }

  .object-grid {
    max-width: 100vw;
    gap: 0.5px;
    padding: 0.5px;
  }

  .object-grid-cell {
    min-height: 25px;
  }

  .object-info {
    max-width: 100vw;
  }
}

/* Upgrade Modal Styles */
.upgrade-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
}

.upgrade-modal-content {
  background: white;
  border-radius: 0.375rem;
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid #dee2e6;
}

.modal-header h5 {
  margin: 0;
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  padding: 0;
  width: 1em;
  height: 1em;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-body {
  padding: 1rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 1rem;
  border-top: 1px solid #dee2e6;
}
</style>