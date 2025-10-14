<template>
  <div class="parcel-editor-page">
    <c-container fluid>
      <c-row>
        <c-col>
          <c-card>
            <c-card-header>
              <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                  <c-icon name="cilCity" class="me-2" />
                  Редактиране на парцел
                </h5>
                <div class="d-flex gap-2">
                  <c-button color="success" size="sm" @click="saveChanges" :disabled="loading">
                    <c-spinner v-if="loading" size="sm" class="me-1" />
                    <c-icon v-else name="cilSave" class="me-1" />
                    Запази
                  </c-button>
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
                      :class="{ 'cell-selected': isCellSelected(i) }"
                      @click="toggleCellSelection(i)"
                    >
                      <!-- Show placed objects -->
                      <div
                        v-for="obj in getObjectsForParcel(parcel.id)"
                        :key="obj.id"
                        v-show="isCellInObject(i, obj)"
                        class="placed-object-large"
                        @click.stop="selectObject(obj)"
                      >
                        <c-icon :name="getObjectIcon(obj.object_type)" />
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Object Palette -->
                <div class="editor-palette">
                  <h6>Обекти</h6>
                  <div class="object-palette">
                    <div
                      v-for="objType in availableObjects"
                      :key="objType.type"
                      class="palette-object"
                      :class="{ selected: selectedObjectType?.type === objType.type }"
                      @click="selectObjectType(objType)"
                    >
                      <c-icon :name="objType.icon" class="me-2" />
                      {{ objType.name }}
                    </div>
                  </div>

                  <div v-if="selectedObjectType" class="mt-3">
                    <h6>Избран обект: {{ selectedObjectType.name }}</h6>
                    <p>Маркирани клетки: {{ selectedCells.size }}</p>
                    <div class="d-flex gap-2">
                      <c-button color="success" size="sm" @click="confirmPlacement">
                        <c-icon name="cilCheck" class="me-1" />
                        Постави
                      </c-button>
                      <c-button color="secondary" size="sm" @click="clearSelection">
                        <c-icon name="cilX" class="me-1" />
                        Изчисти селекцията
                      </c-button>
                    </div>
                  </div>

                  <div v-if="selectedObject" class="mt-3">
                    <h6>Избран обект</h6>
                    <p>{{ selectedObject.object_type }}</p>
                    <c-button color="danger" size="sm" @click="removeObject(selectedObject)">
                      <c-icon name="cilTrash" class="me-1" />
                      Премахни
                    </c-button>
                  </div>

                  <div v-else class="mt-3">
                    <p class="text-muted small">
                      <c-icon name="cilInfo" class="me-1" />
                      Кликнете върху клетките за да ги маркирате, после изберете обект
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
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
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
    const selectedObject = ref(null);
    const selectedObjectType = ref(null);
    const selectedCells = ref(new Set());

    const parcelId = computed(() => parseInt(route.params.parcelId));
    const parcel = computed(() => {
      return gameStore.parcels?.find(p => p.id === parcelId.value && p.user_id === gameStore.user?.id);
    });

    const availableObjects = [
      { type: 'house', name: 'Къща', icon: 'cilHome', width: 3, height: 3 },
      { type: 'tree', name: 'Дърво', icon: 'cilTree', width: 1, height: 1 },
      { type: 'well', name: 'Кладенец', icon: 'cilDrop', width: 2, height: 2 },
      { type: 'barn', name: 'Хамбар', icon: 'cilStorage', width: 4, height: 2 }
    ];

    const getObjectIcon = (type) => {
      const obj = availableObjects.find(o => o.type === type);
      return obj ? obj.icon : 'cilQuestion';
    };

    const toggleCellSelection = (cellIndex) => {
      if (selectedCells.value.has(cellIndex)) {
        // Allow deselection of any selected cell
        selectedCells.value.delete(cellIndex);
      } else {
        // Check if this cell can be selected (must be adjacent to existing selection or first cell)
        if (selectedCells.value.size === 0 || canSelectCell(cellIndex)) {
          selectedCells.value.add(cellIndex);
        }
      }
    };

    const canSelectCell = (cellIndex) => {
      if (selectedCells.value.has(cellIndex)) return false;
      
      // Check if adjacent to any selected cell
      const x = cellIndex % 10;
      const y = Math.floor(cellIndex / 10);
      
      const adjacentCells = [
        (y - 1) * 10 + x, // top
        (y + 1) * 10 + x, // bottom
        y * 10 + (x - 1), // left
        y * 10 + (x + 1)  // right
      ];
      
      return adjacentCells.some(adj => selectedCells.value.has(adj));
    };

    const isCellSelected = (cellIndex) => {
      return selectedCells.value.has(cellIndex);
    };

    const clearSelection = () => {
      selectedCells.value.clear();
      selectedObjectType.value = null;
    };

    const selectObjectType = (objType) => {
      if (selectedCells.value.size === 0) {
        message.value = 'Първо маркирайте област в града';
        messageType.value = 'warning';
        return;
      }
      selectedObjectType.value = objType;
      selectedObject.value = null;
    };

    const confirmPlacement = () => {
      if (selectedObjectType.value && selectedCells.value.size > 0) {
        const bounds = getSelectionBounds();
        const newObj = {
          parcel_id: parcel.value.id,
          object_type: selectedObjectType.value.type,
          x: bounds.minX,
          y: bounds.minY,
          width: bounds.width,
          height: bounds.height,
          properties: {}
        };
        cityObjects.value.push(newObj);
        clearSelection();
      }
    };

    const getSelectionBounds = () => {
      if (selectedCells.value.size === 0) return null;

      const cells = Array.from(selectedCells.value);
      let minX = 10, minY = 10, maxX = 0, maxY = 0;

      cells.forEach(cellIndex => {
        const x = cellIndex % 10;
        const y = Math.floor(cellIndex / 10);
        minX = Math.min(minX, x);
        minY = Math.min(minY, y);
        maxX = Math.max(maxX, x);
        maxY = Math.max(maxY, y);
      });

      return {
        minX,
        minY,
        maxX,
        maxY,
        width: maxX - minX + 1,
        height: maxY - minY + 1
      };
    };

    const isCellInObject = (cellIndex, obj) => {
      const cellX = cellIndex % 10;
      const cellY = Math.floor(cellIndex / 10);

      return cellX >= obj.x && cellX < obj.x + obj.width &&
             cellY >= obj.y && cellY < obj.y + obj.height;
    };

    const getObjectsForParcel = (parcelId) => {
      return cityObjects.value.filter(obj => obj.parcel_id === parcelId);
    };

    const selectObject = (obj) => {
      selectedObject.value = obj;
      selectedObjectType.value = null;
      clearSelection();
    };

    const removeObject = (obj) => {
      const index = cityObjects.value.findIndex(o => o.id === obj.id);
      if (index > -1) {
        cityObjects.value.splice(index, 1);
      }
      selectedObject.value = null;
    };

    const saveChanges = async () => {
      // Check if there are any objects to save
      if (cityObjects.value.length === 0) {
        message.value = 'Няма обекти за запазване. Първо изберете тип обект и го поставете.';
        messageType.value = 'warning';
        return;
      }

      loading.value = true;
      try {
        const res = await axios.post('/api/city-objects/save', {
          objects: cityObjects.value
        });
        if (res.data.success) {
          cityObjects.value = res.data.objects;
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

    const goBackToCity = () => {
      router.push('/city');
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

    onMounted(async () => {
      if (gameStore.user) {
        await gameStore.fetchParcels();
        await fetchCityObjects();
      }
      loading.value = false;
    });

    return {
      loading,
      message,
      messageType,
      parcel,
      cityObjects,
      selectedObject,
      selectedObjectType,
      selectedCells,
      availableObjects,
      toggleCellSelection,
      canSelectCell,
      isCellSelected,
      clearSelection,
      selectObjectType,
      confirmPlacement,
      getSelectionBounds,
      isCellInObject,
      getObjectsForParcel,
      selectObject,
      removeObject,
      saveChanges,
      goBackToCity,
      getObjectIcon
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
}
</style>