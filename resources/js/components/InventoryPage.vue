<template>
  <c-row>
    <c-col md="12">
      <c-card class="mb-4">
        <c-card-header class="d-flex justify-content-between align-items-center">
          <strong>
            <c-icon name="cilStorage" class="me-2" />
            Инвентар
          </strong>
          <div>
            <c-button 
              color="success" 
              size="sm" 
              @click="collectAllResources"
              :disabled="loading"
            >
              <c-spinner v-if="loading" size="sm" class="me-1" />
              <c-icon v-else name="cilPlus" class="me-1" />
              Събери всички
            </c-button>
          </div>
        </c-card-header>
        <c-card-body>
          <!-- Resource Categories -->
          <c-nav variant="tabs" role="tablist" class="mb-3">
            <c-nav-item>
              <c-nav-link
                :active="activeCategory === 'all'"
                @click="activeCategory = 'all'"
              >
                <c-icon name="cilStorage" class="me-2" />
                Всички
              </c-nav-link>
            </c-nav-item>
            <c-nav-item>
              <c-nav-link
                :active="activeCategory === 'materials'"
                @click="activeCategory = 'materials'"
              >
                <c-icon name="cilIndustry" class="me-2" />
                Материали
              </c-nav-link>
            </c-nav-item>
            <c-nav-item>
              <c-nav-link
                :active="activeCategory === 'tools'"
                @click="activeCategory = 'tools'"
              >
                <c-icon name="cilWrench" class="me-2" />
                Инструменти
              </c-nav-link>
            </c-nav-item>
            <c-nav-item>
              <c-nav-link
                :active="activeCategory === 'consumables'"
                @click="activeCategory = 'consumables'"
              >
                <c-icon name="cilMedical" class="me-2" />
                Консумативи
              </c-nav-link>
            </c-nav-item>
          </c-nav>

          <!-- Resource Grid -->
          <c-row>
            <c-col
              v-for="resource in filteredResources"
              :key="resource.id"
              md="4"
              lg="3"
              class="mb-3"
            >
              <c-card class="h-100 resource-card" :class="{ 'border-primary': resource.selected }">
                <c-card-body class="text-center">
                  <div class="resource-icon mb-2">
                    <c-icon :name="resource.icon" size="2xl" :class="resource.iconColor" />
                  </div>
                  <h6 class="mb-1">{{ resource.name }}</h6>
                  <c-badge 
                    :color="getQuantityBadgeColor(resource.quantity)" 
                    class="mb-2"
                  >
                    {{ resource.quantity }}
                  </c-badge>
                  <p class="text-muted small mb-2">{{ resource.description }}</p>
                  
                  <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">{{ resource.category }}</small>
                    <c-dropdown v-if="resource.actions && resource.actions.length">
                      <template #toggler="{ on }">
                        <c-button
                          color="outline-secondary"
                          size="sm"
                          v-on="on"
                        >
                          <c-icon name="cilOptions" />
                        </c-button>
                      </template>
                      <c-dropdown-item 
                        v-for="action in resource.actions"
                        :key="action.name"
                        @click="performAction(action, resource)"
                      >
                        <c-icon :name="action.icon" class="me-2" />
                        {{ action.label }}
                      </c-dropdown-item>
                    </c-dropdown>
                  </div>
                </c-card-body>
              </c-card>
            </c-col>
          </c-row>

          <!-- Empty State -->
          <div v-if="filteredResources.length === 0" class="text-center py-5">
            <c-icon name="cilStorage" size="3xl" class="text-muted mb-3" />
            <h5 class="text-muted">Няма ресурси в тази категория</h5>
            <p class="text-muted">Започнете да събирате ресурси от вашите парцели!</p>
            <c-button color="primary" @click="$router.push('/map')">
              <c-icon name="cilMap" class="me-2" />
              Отиди на картата
            </c-button>
          </div>
        </c-card-body>
      </c-card>
    </c-col>
  </c-row>

  <!-- Resource Details Modal -->
  <c-modal v-model="showResourceModal" size="lg">
    <c-modal-header>
      <c-modal-title>{{ selectedResource?.name }}</c-modal-title>
    </c-modal-header>
    <c-modal-body v-if="selectedResource">
      <c-row>
        <c-col md="4" class="text-center">
          <c-icon :name="selectedResource.icon" size="4xl" :class="selectedResource.iconColor" />
          <h5 class="mt-2">{{ selectedResource.name }}</h5>
          <c-badge :color="getQuantityBadgeColor(selectedResource.quantity)" size="lg">
            Количество: {{ selectedResource.quantity }}
          </c-badge>
        </c-col>
        <c-col md="8">
          <h6>Описание</h6>
          <p>{{ selectedResource.description }}</p>
          
          <h6>Свойства</h6>
          <c-list-group flush>
            <c-list-group-item class="d-flex justify-content-between">
              <span>Категория</span>
              <strong>{{ selectedResource.category }}</strong>
            </c-list-group-item>
            <c-list-group-item class="d-flex justify-content-between">
              <span>Тежест</span>
              <strong>{{ selectedResource.weight || 'N/A' }} кг</strong>
            </c-list-group-item>
            <c-list-group-item class="d-flex justify-content-between">
              <span>Стойност</span>
              <strong>{{ selectedResource.value || 'N/A' }} монети</strong>
            </c-list-group-item>
          </c-list-group>
        </c-col>
      </c-row>
    </c-modal-body>
    <c-modal-footer>
      <c-button color="secondary" @click="showResourceModal = false">
        Затвори
      </c-button>
      <c-button 
        v-if="selectedResource && selectedResource.actions"
        color="primary"
        @click="performAction(selectedResource.actions[0], selectedResource)"
      >
        {{ selectedResource.actions[0]?.label || 'Действие' }}
      </c-button>
    </c-modal-footer>
  </c-modal>

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
import { ref, computed, onMounted } from 'vue';
import { useGameStore } from '../stores/gameStore';

export default {
  name: 'InventoryPage',
  setup() {
    const gameStore = useGameStore();
    const activeCategory = ref('all');
    const loading = ref(false);
    const message = ref('');
    const messageType = ref('');
    const showResourceModal = ref(false);
    const selectedResource = ref(null);

    const resources = ref([
      {
        id: 1,
        name: 'Дърво',
        quantity: 150,
        category: 'materials',
        description: 'Основен строителен материал, добиван от дървета.',
        icon: 'cilStorage',
        iconColor: 'text-success',
        weight: 2,
        value: 5,
        actions: [
          { name: 'use', label: 'Използвай', icon: 'cilCheckCircle' },
          { name: 'sell', label: 'Продай', icon: 'cilMoney' }
        ]
      },
      {
        id: 2,
        name: 'Камък',
        quantity: 75,
        category: 'materials',
        description: 'Твърд материал за строителство и занаяти.',
        icon: 'cilIndustry',
        iconColor: 'text-secondary',
        weight: 5,
        value: 8,
        actions: [
          { name: 'use', label: 'Използвай', icon: 'cilCheckCircle' },
          { name: 'sell', label: 'Продай', icon: 'cilMoney' }
        ]
      },
      {
        id: 3,
        name: 'Метал',
        quantity: 25,
        category: 'materials',
        description: 'Ценен материал за направа на инструменти.',
        icon: 'cilIndustry',
        iconColor: 'text-primary',
        weight: 8,
        value: 15,
        actions: [
          { name: 'craft', label: 'Изработи', icon: 'cilWrench' },
          { name: 'sell', label: 'Продай', icon: 'cilMoney' }
        ]
      },
      {
        id: 4,
        name: 'Кирка',
        quantity: 2,
        category: 'tools',
        description: 'Инструмент за добиване на камък и метал.',
        icon: 'cilWrench',
        iconColor: 'text-warning',
        weight: 3,
        value: 50,
        actions: [
          { name: 'equip', label: 'Екипирай', icon: 'cilShieldAlt' },
          { name: 'repair', label: 'Поправи', icon: 'cilWrench' }
        ]
      },
      {
        id: 5,
        name: 'Брадва',
        quantity: 1,
        category: 'tools',
        description: 'Инструмент за сечене на дърво.',
        icon: 'cilWrench',
        iconColor: 'text-success',
        weight: 4,
        value: 45,
        actions: [
          { name: 'equip', label: 'Екипирай', icon: 'cilShieldAlt' },
          { name: 'repair', label: 'Поправи', icon: 'cilWrench' }
        ]
      },
      {
        id: 6,
        name: 'Храна',
        quantity: 10,
        category: 'consumables',
        description: 'Възстановява енергия и здраве.',
        icon: 'cilMedical',
        iconColor: 'text-danger',
        weight: 1,
        value: 3,
        actions: [
          { name: 'consume', label: 'Консумирай', icon: 'cilCheckCircle' }
        ]
      }
    ]);

    const filteredResources = computed(() => {
      if (activeCategory.value === 'all') {
        return resources.value;
      }
      return resources.value.filter(r => r.category === activeCategory.value);
    });

    const getQuantityBadgeColor = (quantity) => {
      if (quantity === 0) return 'danger';
      if (quantity < 10) return 'warning';
      if (quantity < 50) return 'info';
      return 'success';
    };

    const collectAllResources = () => {
      loading.value = true;
      setTimeout(() => {
        // Simulate collection
        resources.value.forEach(resource => {
          if (resource.category === 'materials') {
            resource.quantity += Math.floor(Math.random() * 10) + 5;
          }
        });
        loading.value = false;
        message.value = 'Всички ресурси са събрани успешно!';
        messageType.value = 'success';
      }, 1500);
    };

    const performAction = (action, resource) => {
      switch (action.name) {
        case 'use':
        case 'consume':
          if (resource.quantity > 0) {
            resource.quantity--;
            message.value = `Използвахте ${resource.name}`;
            messageType.value = 'success';
          }
          break;
        case 'sell':
          if (resource.quantity > 0) {
            resource.quantity--;
            message.value = `Продадохте ${resource.name} за ${resource.value} монети`;
            messageType.value = 'success';
          }
          break;
        case 'equip':
          message.value = `Екипирахте ${resource.name}`;
          messageType.value = 'success';
          break;
        default:
          message.value = `Извършено действие: ${action.label}`;
          messageType.value = 'success';
      }
      showResourceModal.value = false;
    };

    const openResourceDetails = (resource) => {
      selectedResource.value = resource;
      showResourceModal.value = true;
    };

    onMounted(() => {
      // Load user inventory from API
    });

    return {
      activeCategory,
      loading,
      message,
      messageType,
      showResourceModal,
      selectedResource,
      resources,
      filteredResources,
      getQuantityBadgeColor,
      collectAllResources,
      performAction,
      openResourceDetails
    };
  }
}
</script>

<style scoped>
.resource-card {
  transition: transform 0.2s ease-in-out;
  cursor: pointer;
}

.resource-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.resource-icon {
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>