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
                  {{ $t("city.object_editing") }}
                </h5>
                <div class="d-flex gap-2">
                  <c-button color="secondary" @click="goBackToParcel">
                    <c-icon name="cilArrowLeft" class="me-1" />
                    {{ $t("city.back_to_parcel") }}
                  </c-button>
                </div>
              </div>
            </c-card-header>
            <c-card-body>
              <div v-if="loading" class="text-center">
                <c-spinner />
                <p>{{ $t('city.loading') }}</p>
              </div>

              <div v-else-if="object" class="object-editor-content">
                <!-- Object Grid for editing -->
                <div class="object-grid-container">
                  <div class="object-grid">
                    <div
                      v-for="i in 16"
                      :key="i"
                      class="object-grid-cell"
                      :class="{
                        'cell-empty': !getToolAt(Math.floor((i - 1) / 4), (i - 1) % 4),
                        'move-target': moveMode && !getToolAt(Math.floor((i - 1) / 4), (i - 1) % 4),
                      }"
                      @click="handleCellClick(Math.floor((i - 1) / 4), (i - 1) % 4)"
                    >
                      <!-- Show placed tools -->
                      <div
                        v-if="getToolAt(Math.floor((i - 1) / 4), (i - 1) % 4)"
                        class="placed-tool"
                        @click="handleToolClick(Math.floor((i - 1) / 4), (i - 1) % 4)"
                        @touchstart="handleToolTouch(Math.floor((i - 1) / 4), (i - 1) % 4)"
                      >
                        <img
                          :src="`/images/tools/${getToolAt(Math.floor((i - 1) / 4), (i - 1) % 4)?.tool_type_icon || 'student_materials.png'}`"
                          alt="Tool"
                          style="width: 100%; height: 100%; object-fit: cover"
                        />

                        <!-- Tool actions overlay -->
                        <div
                          v-if="hoveredCell && hoveredCell.x === Math.floor((i - 1) / 4) && hoveredCell.y === (i - 1) % 4"
                          class="tool-actions"
                        >
                          <div
                            @click.stop="startMoveMode(getToolAt(Math.floor((i - 1) / 4), (i - 1) % 4))"
                            class="action-icon move-icon"
                            title="Премести tool"
                          >
                            <c-icon name="cil-move" size="xl" />
                          </div>
                          <div
                            @click.stop="showToolInfo(getToolAt(Math.floor((i - 1) / 4), (i - 1) % 4))"
                            class="action-icon info-icon"
                            title="Информация за tool"
                          >
                            <c-icon name="cil-info" size="xl" />
                          </div>
                          <div
                            @click.stop="hideToolActions()"
                            class="action-icon close-icon"
                            title="Затвори"
                          >
                            <c-icon name="cil-x" size="xl" />
                          </div>
                        </div>
                      </div>
                      <!-- Empty cell, no plus icon -->
                    </div>
                  </div>
                </div>

                <!-- Object info -->
                <div class="object-info mt-4">
                  <h6>{{ $t('city.object_info') }}</h6>
                  <div class="row">
                    <div class="col-md-6">
                      <p class="mb-1">
                        <strong>{{ $t('city.type') }}:</strong>
                        {{ getObjectTypeName(object.object_type) }}
                      </p>
                      <p class="mb-1">
                        <strong>{{ $t('city.level') }}:</strong> {{ object.level || 1 }}
                      </p>
                    </div>
                    <div class="col-md-6">
                      <p v-if="!object.ready_at || remainingTimeText === ''" class="mb-1">
                        <strong>{{ $t('city.status') }}:</strong>
                        <span class="text-success">{{ $t('city.ready') }}</span>
                      </p>
                      <p v-else class="mb-1">
                        <strong>{{ $t('city.status') }}:</strong>
                        <span class="text-danger">{{ $t('city.building') }}</span> -
                        {{ remainingTimeText }}
                      </p>
                    </div>
                  </div>
                  <!-- Show workers info if building -->
                  <div v-if="isBuilding(object) && buildingWorkers" class="mt-2 alert alert-info py-2">
                    <small>
                      <strong>{{ $t('city.workers') }}:</strong>
                      {{ buildingWorkers.count }} х {{ $t('city.level') }}
                      {{ buildingWorkers.level }}
                    </small>
                  </div>
                  <div class="mt-3">
                    <template v-if="object && object.ready_at === null && (!object.workers || !object.workers.count)">
                      <c-button color="primary" @click="showUpgradeModal = true">
                        <c-icon name="cilArrowTop" class="me-1" />
                        {{ $t('city.upgrade_level') }}
                      </c-button>
                      <c-button color="success" class="ms-2" v-if="objectHasProduction()" @click="openProduceModal">
                        <c-icon name="cilFactory" class="me-1" />
                        {{ $t('city.start_production') }}
                      </c-button>
                    </template>
                    <template v-else-if="object && (object.ready_at !== null || (object.workers && object.workers.count))">
                      <div class="alert alert-info py-2 mb-0 d-flex align-items-center gap-2">
                        <c-spinner class="spinner-border-sm" />
                        <span>{{ $t('city.waiting_release_workers') }}</span>
                      </div>
                    </template>
                  </div>
                </div>
              </div>
            </c-card-body>
          </c-card>
        </c-col>
      </c-row>
    </c-container>

    <!-- Upgrade Modal -->
    <div
      v-if="showUpgradeModal"
      class="upgrade-modal-overlay"
      @click="showUpgradeModal = false"
    >
      <div class="upgrade-modal-content" @click.stop>
        <div class="modal-header">
          <h5>{{ $t("city.upgrade_level") }}</h5>
          <button
            type="button"
            class="btn-close"
            @click="showUpgradeModal = false"
          ></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">{{ $t("city.select_workers_upgrade") }}</p>
          <div class="d-flex gap-2 mb-3">
            <div>
              <label class="form-label small mb-1">{{ $t("city.worker_level") }}</label>
              <select class="form-select" v-model="upgradeWorkerLevel">
                <option v-for="(count, lvl) in people.by_level" :key="lvl" :value="lvl">
                  LV {{ lvl }} ({{ count }})
                </option>
              </select>
            </div>
            <div>
              <label class="form-label small mb-1">{{ $t("city.worker_count") }}</label>
              <select class="form-select" v-model.number="upgradeWorkerCount">
                <option :value="0">0</option>
                <option
                  v-for="n in availableCountsForLevel(upgradeWorkerLevel)"
                  :key="n"
                  :value="n"
                >
                  {{ n }}
                </option>
              </select>
            </div>
          </div>
          <div v-if="upgradeWorkerLevel && upgradeWorkerCount" class="alert alert-info">
            <strong>{{ $t("city.upgrade_time") }}:</strong> {{ upgradeTimeMinutes }}
            {{ $t("city.minutes") }}
          </div>
        </div>
        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            @click="showUpgradeModal = false"
          >
            {{ $t("city.cancel") }}
          </button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="
              !upgradeWorkerLevel || !upgradeWorkerCount || upgradeWorkerCount <= 0
            "
            @click="startUpgrade"
          >
            {{ $t("city.start_upgrade") }}
          </button>
        </div>
      </div>
    </div>

    <!-- Production Modal -->
    <div
      v-if="showProduceModal"
      class="upgrade-modal-overlay"
      @click="showProduceModal = false"
    >
      <div class="upgrade-modal-content" @click.stop>
        <div class="modal-header">
          <h5>{{ $t('city.start_production') }}</h5>
          <button type="button" class="btn-close" @click="showProduceModal = false"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">{{ $t('city.select_workers_production') }}</p>
          <div class="d-flex gap-2 mb-3">
            <div>
              <label class="form-label small mb-1">{{ $t('city.worker_level') }}</label>
              <select class="form-select" v-model="produceWorkerLevel" @change="calculateProductionPreview">
                <option v-for="item in availableWorkerLevels" :key="item.level" :value="item.level">
                  LV {{ item.level }} ({{ item.count }})
                </option>
              </select>
            </div>
            <div>
              <label class="form-label small mb-1">{{ $t('city.worker_count') }}</label>
              <select class="form-select" v-model.number="produceWorkerCount" @change="calculateProductionPreview">
                <option :value="0">0</option>
                <option v-for="n in availableCountsForLevel(produceWorkerLevel)" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small mb-1">{{ $t('city.production_length_hours') || 'Production length (hours)' }}</label>
            <div class="d-flex align-items-center gap-3">
              <input type="range" min="1" max="24" v-model.number="produceDurationHours" @input="calculateProductionPreview" class="form-range" />
              <div class="fw-bold">{{ produceDurationHours }}h</div>
            </div>
          </div>

          <div v-if="productionPreview.length === 0" class="text-center">
            {{ $t('city.no_production_materials') }}
          </div>
          <div v-else class="list-group">
            <div v-for="item in productionPreview" :key="item.tool_type_id" class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <strong>
                  {{ getTranslatedName(item.tool_type_name) }}
                  <template v-if="item.produces_tool_type_name">
                    <span class="text-muted"> → </span>
                    <em>{{ getTranslatedName(item.produces_tool_type_name) }}</em>
                  </template>
                </strong>
                <div class="small text-muted">
                  {{ item.fieldsCount }} {{ $t('city.fields') }}
                  <template v-if="item.needRaw > 0">
                    <span class="text-muted"> - </span>
                    <small>{{ $t('city.need') || 'Need' }}: <strong>{{ item.needRaw }}</strong></small>
                  </template>
                </div>
              </div>
                <div class="text-end">
                <div>{{ item.perHour }} / {{ $t('city.hour') }}</div>
                <div><strong>{{ item.totalProduced }}</strong> / {{ produceDurationHours }} {{ $t('city.hours') }}</div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showProduceModal = false">{{ $t('city.cancel') }}</button>
          <button type="button" class="btn btn-success" :disabled="!produceWorkerLevel || produceWorkerCount <= 0 || !produceStartAllowed" @click="startProduction">{{ $t('city.start_production') }}</button>
        </div>
      </div>
    </div>

    <!-- Tool Selection Modal -->
    <div
      v-if="showToolModal"
      class="upgrade-modal-overlay"
      @click="showToolModal = false"
    >
      <div class="upgrade-modal-content" @click.stop>
        <div class="modal-header">
          <h5>{{ $t("tools.select_tool") }}</h5>
          <button type="button" class="btn-close" @click="showToolModal = false"></button>
        </div>
        <div class="modal-body">
          <div v-if="availableTools.length === 0" class="text-center">
            {{ $t("tools.no_tools_available") }}
          </div>
          <div v-else class="row">
            <div v-for="tool in availableTools" :key="tool.id" class="col-md-4 mb-3">
              <div class="card tool-card" @click="addTool(tool)">
                <div class="card-body text-center">
                  <img
                    :src="`/images/tools/${tool.icon || 'student_materials.png'}`"
                    alt="Tool"
                    style="width: 40px; height: 40px"
                    class="mb-2"
                  />
                  <h6 class="card-title">{{ getTranslatedName(tool.name) }}</h6>
                  <p class="card-text small">{{ tool.description }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted, inject } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useGameStore } from "../stores/gameStore";
import axios from "axios";

export default {
  name: "ObjectEditor",
  setup() {
    const route = useRoute();
    const router = useRouter();
    const gameStore = useGameStore();
    const $t = inject("$t");

      const getTranslatedName = (name) => {
        if (!name) return '';
        const key = `tools.types.${name}`;
        try {
          const translated = $t(key);
          // if translation missing, some i18n implementations return the key unchanged
          if (!translated || translated === key) {
            // fallback: prettify the DB name (replace underscores, title-case)
            return name
              .replace(/_/g, ' ')
              .split(' ')
              .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
              .join(' ');
          }
          return translated;
        } catch (e) {
          return name.replace(/_/g, ' ').split(' ').map((w)=>w.charAt(0).toUpperCase()+w.slice(1)).join(' ');
        }
      };
    const loading = ref(true);
    const cityObjects = ref([]);
    const showUpgradeModal = ref(false);
  const showProduceModal = ref(false);
  const produceWorkerLevel = ref(null);
  const produceWorkerCount = ref(0);
  const productionPreview = ref([]); // [{tool_type_name, fieldsCount, perHour, totalProduced, produces_tool_type_id}]
  const produceDurationHours = ref(12);
  const inventories = ref({});
  const produceStartAllowed = ref(true);
    const upgradeWorkerLevel = ref(null);
    const upgradeWorkerCount = ref(0);
    const people = ref({ total: 0, by_level: {}, groups: [] });
    const currentTime = ref(Date.now()); // For reactive time updates
    let tickInterval = null;
  let pollInterval = null;

    // Tool-related data
    const tools = ref([]);
    const showToolModal = ref(false);
    const availableTools = ref([]);
    const selectedPosition = ref({ x: 0, y: 0 });
    const moveMode = ref(false);
    const selectedTool = ref(null);
    const hoveredCell = ref(null);
    let longPressTimer = null;

    const parcelId = computed(() => parseInt(route.params.parcelId));
    const objectId = computed(() => parseInt(route.params.objectId));

    const parcel = computed(() => {
      return gameStore.parcels?.find(
        (p) => p.id === parcelId.value && p.user_id === gameStore.user?.id
      );
    });

    const object = computed(() => {
      return cityObjects.value.find((obj) => obj.id === objectId.value);
    });

    const getObjectIcon = (type) => {
      // This would need to be implemented if we want icons
      return "cilQuestion";
    };

    const availableObjects = ref([]);

    const getObjectTypeName = (type) => {
      // Prefer language file translation city.{type}, fall back to availableObjects name or prettified type
      try {
        const key = 'city.' + type;
        const translated = $t(key);
        if (translated && translated !== key) return translated;
      } catch (e) {
        // ignore
      }
      const obj = availableObjects.value.find((o) => o.type === type);
      if (obj && obj.name) return obj.name;
      return type ? String(type).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : $t('city.unknown');
    };

    

    const isBuilding = (obj) => {
      if (!obj) return false;
      const ready = getReadyTimestamp(obj);
      if (!ready) return false;
      return ready > Date.now();
    };

    const objectHasProduction = () => {
      if (!object.value) return false;
      // INVERTED: show production if ANY tool is placed (products)
      return tools.value.length > 0;
    };

    const getReadyTimestamp = (obj) => {
      // ready_at is already a timestamp in milliseconds
      if (!obj || !obj.ready_at) return null;
      return obj.ready_at;
    };

    const remainingTimeText = computed(() => {
      if (!object.value || !object.value.ready_at) return "";
      const ready = object.value.ready_at; // Already in milliseconds
      const remaining = Math.max(0, ready - currentTime.value);
      if (remaining === 0) return "";
      const totalSeconds = Math.ceil(remaining / 1000);
      const hours = Math.floor(totalSeconds / 3600);
      const minutes = Math.floor((totalSeconds % 3600) / 60);
      const seconds = totalSeconds % 60;
      const two = (n) => String(n).padStart(2, '0');
      if (hours > 0) {
        return `${hours}:${two(minutes)}:${two(seconds)}`;
      }
      return `${minutes}:${two(seconds)}`;
    });

    // Get workers info from occupied_workers for this object
    const buildingWorkers = computed(() => {
      if (!object.value || !isBuilding(object.value)) return null;
      // Check if object has workers data from API
      if (object.value.workers) {
        return object.value.workers;
      }
      return null;
    });

    const goBackToParcel = () => {
      router.push(`/city/${parcelId.value}`);
    };

    const fetchCityObjects = async () => {
      try {
        const res = await axios.get("/api/city-objects");
        if (res.data.success) {
          // Normalize numeric fields to avoid strict type mismatches between server and client
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
        console.error("Failed to fetch city objects", e);
      }
    };

    // Fetch a single object from server and update local copy. Used by the poller.
    const fetchSingleObject = async () => {
      try {
        const res = await axios.get('/api/city-objects');
        if (res.data.success) {
          const obj = (res.data.objects || []).find(o => o.id === objectId.value);
          if (obj) {
            const idx = cityObjects.value.findIndex(o => o.id === objectId.value);
            if (idx !== -1) {
              cityObjects.value[idx] = obj;
              cityObjects.value = [...cityObjects.value];
            }
            // stop polling when object is finalized (ready_at null and no workers)
            if (obj.ready_at === null && (!obj.workers || !obj.workers.count)) {
              stopObjectPoll();
            }
          }
        }
      } catch (e) {
        console.error('Failed to fetch single object', e);
      }
    };

    const startObjectPoll = () => {
      stopObjectPoll();
      // Poll every 15s while viewing the object and it appears to need finalization
      pollInterval = setInterval(async () => {
        const obj = cityObjects.value.find(o => o.id === objectId.value);
        if (!obj) return;
        const readyTs = obj.ready_at ? new Date(obj.ready_at).getTime() : null;
        const needsCheck = (readyTs !== null && readyTs <= Date.now()) || (obj.workers && obj.workers.count);
        if (needsCheck) {
          await fetchSingleObject();
        }
      }, 15000);
    };

    const stopObjectPoll = () => {
      if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
      }
    };

    const fetchObjectTypes = async () => {
      try {
        const res = await axios.get("/api/object-types");
        if (res.data.success) {
          availableObjects.value = res.data.types || [];
        }
      } catch (e) {
        console.error("Failed to fetch object types", e);
      }
    };

    const fetchPeople = async () => {
      try {
        const res = await axios.get("/api/people");
        if (res.data.success) {
          people.value.total = res.data.total || 0;
          people.value.by_level = res.data.by_level || {};
          people.value.groups = res.data.groups || [];
        }
      } catch (e) {
        console.error("Failed to fetch people", e);
      }
    };

    const availableCountsForLevel = (level) => {
      const count = people.value.by_level?.[level] || 0;
      const arr = [];
      for (let i = 1; i <= count; i++) arr.push(i);
      return arr;
    };

    const availableWorkerLevels = computed(() => {
      const levels = [];
      for (const [lvl, count] of Object.entries(people.value.by_level || {})) {
        if (count > 0) {
          levels.push({ level: lvl, count: count });
        }
      }
      return levels;
    });

    const upgradeTimeMinutes = computed(() => {
      // Base time is the same as build time of the object type (same as building)
      const objectType = availableObjects.value.find(
        (o) => o.type === object.value?.object_type
      );
      const baseTime = objectType ? objectType.build_time_minutes : 10;
      const lvl = upgradeWorkerLevel.value ? parseInt(upgradeWorkerLevel.value) : 0;
      const cnt = upgradeWorkerCount.value ? parseInt(upgradeWorkerCount.value) : 0;
      // Use NEXT level = current + 1 for upgrade calculation
      const currentLevel = object.value?.level || 0;
      const nextLevel = (currentLevel || 0) + 1;
      if (lvl > 0 && cnt > 0) {
        const levelAdjusted = baseTime * Math.max(1, nextLevel);
        let reductionMinutes = (lvl * cnt) - 1;
        if (reductionMinutes < 0) reductionMinutes = 0;
        return Math.max(1, levelAdjusted - reductionMinutes);
      }
    return baseTime * Math.max(1, nextLevel);
    });
    const startUpgrade = async () => {
      try {
        const res = await axios.post("/api/city-objects/upgrade", {
          object_id: objectId.value,
          worker_level: upgradeWorkerLevel.value,
          worker_count: upgradeWorkerCount.value,
        });

        if (res.data.success) {
          showUpgradeModal.value = false;
          upgradeWorkerLevel.value = null;
          upgradeWorkerCount.value = 0;

          // Update object locally with all data from response
          const objIndex = cityObjects.value.findIndex(
            (obj) => obj.id === objectId.value
          );
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
          console.error("Upgrade failed:", res.data.message);
          alert("Upgrade failed: " + res.data.message);
        }
      } catch (e) {
        console.error("Failed to start upgrade", e);
        if (e.response && e.response.data && e.response.data.message) {
          alert("Error: " + e.response.data.message);
        } else {
          alert("Failed to start upgrade. Check console for details.");
        }
      }
    };

    const openProduceModal = async () => {
      // Select first available level with workers > 0
      const availableLevels = Object.entries(people.value.by_level || {}).filter(([lvl, count]) => count > 0);
      produceWorkerLevel.value = availableLevels.length > 0 ? availableLevels[0][0] : null;
      produceWorkerCount.value = produceWorkerLevel.value ? 1 : 0;
      // Try to load user's saved production length from game settings
      try {
        const res = await axios.get('/api/game-settings');
        if (res.data.success && res.data.settings && res.data.settings.production_length_hours) {
          produceDurationHours.value = parseInt(res.data.settings.production_length_hours);
        } else {
          produceDurationHours.value = 12;
        }
      } catch (e) {
        produceDurationHours.value = 12;
      }
      // Load inventories BEFORE calculating preview
      try {
        const inv = await axios.get('/api/inventories');
        inventories.value = (inv.data.items || []).reduce((acc, it) => {
          acc[it.tool_type_id] = it;
          return acc;
        }, {});
      } catch (e) {
        inventories.value = {};
      }
      await calculateProductionPreview();
      showProduceModal.value = true;
    };

    const calculateProductionPreview = async () => {
      productionPreview.value = [];
      if (!object.value) return;

      // INVERTED: group by product (tool_type_id)
      const byProduct = {};
      tools.value.forEach((t) => {
        const key = t.tool_type_id;
        if (!byProduct[key]) {
          byProduct[key] = { tool: t, count: 0 };
        }
        byProduct[key].count++;
      });

      const lvl = produceWorkerLevel.value ? parseInt(produceWorkerLevel.value) : 1;
      const cnt = produceWorkerCount.value ? parseInt(produceWorkerCount.value) : 1;
      const duration = produceDurationHours.value ? parseInt(produceDurationHours.value) : 1;

      for (const key in byProduct) {
        const entry = byProduct[key];
        const fieldsCount = entry.count;
        const tool = entry.tool;

        // Use PRODUCT units_per_hour for production calculation
        const basePerHour = tool.product_units_per_hour 
          ? parseInt(tool.product_units_per_hour) 
          : (tool.units_per_hour ? parseInt(tool.units_per_hour) : 0);
        const perHour = fieldsCount * basePerHour * Math.max(1, lvl) * Math.max(1, cnt);
        const total = perHour * duration;

        // Need raw: 1 per field per hour (if raw exists)
        const rawId = tool.raw_tool_type_id;
        const needRaw = rawId ? (fieldsCount * duration) : 0;
        const availableRaw = rawId && inventories.value[rawId]
          ? parseInt(inventories.value[rawId].count || 0)
          : 0;

        productionPreview.value.push({
          tool_type_id: tool.tool_type_id,
          tool_type_name: tool.raw_name || tool.tool_type_name, // left: raw name (or product if harvest)
          produces_tool_type_name: tool.raw_name ? tool.tool_type_name : null, // right: product name (null if harvest)
          needRaw,
          availableRaw,
          fieldsCount,
          perHour,
          totalProduced: total,
          produces_tool_type_id: tool.tool_type_id,
        });
      }

      // Enable start only if user has enough raw materials
      produceStartAllowed.value = productionPreview.value.every(p => (p.availableRaw || 0) >= (p.needRaw || 0));
    };

    const startProduction = async () => {
      try {
        const res = await axios.post('/api/city-objects/produce', {
          object_id: objectId.value,
          worker_level: produceWorkerLevel.value,
          worker_count: produceWorkerCount.value,
          duration_hours: produceDurationHours.value
        });

        if (res.data.success) {
          showProduceModal.value = false;
          // Update object ready_at locally
          const objIndex = cityObjects.value.findIndex((o) => o.id === objectId.value);
          if (objIndex !== -1) {
            cityObjects.value[objIndex].ready_at = res.data.object.ready_at;
            cityObjects.value = [...cityObjects.value];
          }
          await fetchPeople();
        } else {
          alert('Production failed: ' + res.data.message);
        }
      } catch (e) {
        console.error('Failed to start production', e);
        alert($t('city.production_failed') || 'Failed to start production');
      }
    };

    // Tool methods
    const openToolModal = async (x, y) => {
      selectedPosition.value = { x, y };
      try {
        const res = await axios.get(`/api/objects/${objectId.value}/available-tools`);
        // Normalize available tools (ids/numeric fields) to avoid type mismatch
        availableTools.value = (res.data || []).map(t => {
          const copy = Object.assign({}, t);
          if (copy.id && /^\d+$/.test(String(copy.id))) copy.id = Number(copy.id);
          if (copy.tool_type_id && /^\d+$/.test(String(copy.tool_type_id))) copy.tool_type_id = Number(copy.tool_type_id);
          return copy;
        });
        showToolModal.value = true;
      } catch (e) {
        console.error("Failed to fetch available tools", e);
        alert("Failed to load available tools.");
      }
    };

    const handleCellClick = (x, y) => {
      if (moveMode.value) {
        if (!getToolAt(x, y)) {
          moveToolTo(x, y);
        } else {
          cancelMove();
        }
      } else if (getToolAt(x, y)) {
        // If clicking on a tool, show actions
        hoveredCell.value = { x, y };
      } else {
        // If clicking on empty cell, open tool modal
        openToolModal(x, y);
      }
    };

    const addTool = async (tool) => {
      try {
        await axios.post("/api/objects/add-tool", {
          object_id: objectId.value,
          tool_type_id: tool.id,
          position_x: selectedPosition.value.x,
          position_y: selectedPosition.value.y,
        });
        showToolModal.value = false;
        await loadTools();
      } catch (e) {
        console.error("Failed to add tool", e);
        alert($t("tools.tool_add_failed"));
      }
    };

    const loadTools = async () => {
      try {
        const res = await axios.get(`/api/objects/${objectId.value}/tools`);
        // Normalize tool fields so position_x/position_y and ids are Numbers
        tools.value = (res.data || []).map(tt => {
          const copy = Object.assign({}, tt);
          if (copy.id && /^\d+$/.test(String(copy.id))) copy.id = Number(copy.id);
          if (copy.position_x != null && /^\d+$/.test(String(copy.position_x))) copy.position_x = Number(copy.position_x);
          if (copy.position_y != null && /^\d+$/.test(String(copy.position_y))) copy.position_y = Number(copy.position_y);
          if (copy.tool_type_id && /^\d+$/.test(String(copy.tool_type_id))) copy.tool_type_id = Number(copy.tool_type_id);
          return copy;
        });
      } catch (e) {
        console.error("Failed to load tools", e);
      }
    };

    const getToolAt = (x, y) => {
      return tools.value.find((t) => t.position_x === x && t.position_y === y);
    };

    // Long press for move mode
    const startLongPress = (tool) => {
      longPressTimer = setTimeout(() => {
        moveMode.value = true;
        selectedTool.value = tool;
      }, 500);
    };

    const cancelLongPress = () => {
      if (longPressTimer) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
      }
      // Don't cancel moveMode here - let it stay active until user moves or cancels
    };

    const moveToolTo = async (x, y) => {
      if (!moveMode.value || !selectedTool.value) return;

      try {
        await axios.post("/api/objects/update-tool-position", {
          tool_id: selectedTool.value.id,
          x,
          y,
        });
        selectedTool.value.position_x = x;
        selectedTool.value.position_y = y;
        tools.value = [...tools.value]; // trigger reactivity
        moveMode.value = false;
        selectedTool.value = null;
      } catch (error) {
        console.error("Failed to move tool", error);
        alert("Failed to move tool");
        moveMode.value = false;
        selectedTool.value = null;
      }
    };

    const startMoveMode = (tool) => {
      moveMode.value = true;
      selectedTool.value = tool;
      hoveredCell.value = null; // Hide actions after selecting move
    };

    const showToolInfo = (tool) => {
      alert(
        `Tool: ${getTranslatedName(tool.name)}\nType: ${getTranslatedName(tool.tool_type_name)}\nLevel: ${
          tool.level || 1
        }\nPosition: (${tool.position_x}, ${tool.position_y})`
      );
      hoveredCell.value = null; // Hide actions after showing info
    };

    const hideToolActions = () => {
      hoveredCell.value = null; // Hide actions
    };

    const cancelMove = () => {
      moveMode.value = false;
      selectedTool.value = null;
    };

    const handleToolClick = (x, y) => {
      // Toggle actions for tool on click (desktop)
      if (hoveredCell.value && hoveredCell.value.x === x && hoveredCell.value.y === y) {
        hoveredCell.value = null; // Hide if already shown
      } else {
        hoveredCell.value = { x, y }; // Show if not shown
      }
    };

    const handleToolTouch = (x, y) => {
      // Toggle actions for tool on touch (mobile)
      if (hoveredCell.value && hoveredCell.value.x === x && hoveredCell.value.y === y) {
        hoveredCell.value = null; // Hide if already shown
      } else {
        hoveredCell.value = { x, y }; // Show if not shown
      }
    };

    onMounted(async () => {
      if (gameStore.user) {
        await gameStore.fetchParcels();
        await fetchCityObjects();
        await fetchObjectTypes();
        await fetchPeople();
        await loadTools();
      }
      loading.value = false;

      // Don't hide tool actions automatically - let user control them

      // Tick every second to update countdown displays
      tickInterval = setInterval(() => {
        currentTime.value = Date.now();
      }, 1000);

      // Start object poll (checks every 15s when needed)
      startObjectPoll();

      // Store cleanup function
      onUnmounted(() => {
        if (tickInterval) clearInterval(tickInterval);
        stopObjectPoll();
      });
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
      buildingWorkers,
      goBackToParcel,
      getObjectTypeName,
      getTranslatedName,
      isBuilding,
  remainingTimeText,
      availableCountsForLevel,
      startUpgrade,
      // Tool-related
      tools,
      showToolModal,
      availableTools,
      openToolModal,
      handleCellClick,
      addTool,
      loadTools,
      getToolAt,
      moveMode,
      selectedTool,
      hoveredCell,
      startMoveMode,
      showToolInfo,
      hideToolActions,
      startLongPress,
      cancelLongPress,
      moveToolTo,
      cancelMove,
      handleToolClick,
      handleToolTouch,
  // Production
  showProduceModal,
  produceWorkerLevel,
  produceWorkerCount,
  productionPreview,
  produceDurationHours,
  produceStartAllowed,
  availableWorkerLevels,
  objectHasProduction,
  openProduceModal,
  calculateProductionPreview,
  startProduction,
    };
  },
};
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
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: repeat(4, 1fr);
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
  cursor: pointer;
}

.cell-empty:hover {
  background: rgba(0, 123, 255, 0.1);
}

.move-target {
  background: rgba(40, 167, 69, 0.3) !important;
}

.move-target:hover {
  background: rgba(40, 167, 69, 0.3) !important;
}

.empty-cell {
  color: #6c757d;
  font-size: 1.2rem;
}

.placed-tool {
  color: black;
  font-size: 1.2rem;
  position: relative;
}

.tool-actions {
  position: absolute;
  top: -50px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 4px;
  z-index: 10;
}

.action-icon {
  cursor: pointer;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 50%;
  padding: 8px;
  font-size: 20px;
  color: #495057;
  border: 2px solid #dee2e6;
  transition: all 0.2s;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
}

.action-icon:hover {
  background: rgba(255, 255, 255, 1);
  transform: scale(1.1);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.move-icon:hover {
  color: #007bff;
  border-color: #007bff;
}

.info-icon:hover {
  color: #17a2b8;
  border-color: #17a2b8;
}

.close-icon:hover {
  color: #dc3545;
  border-color: #dc3545;
}

.tool-card {
  cursor: pointer;
  transition: transform 0.2s;
}

.tool-card:hover {
  transform: scale(1.05);
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
