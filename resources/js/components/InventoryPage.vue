<template>
  <c-row>
    <c-col md="12">
      <c-card class="mb-4">
        <c-card-header class="d-flex justify-content-between align-items-center">
          <strong>
            <c-icon name="cilStorage" class="me-2" />
            Инвентар
          </strong>
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

          <!-- Unified Inventory Table -->
          <c-table hover responsive>
            <thead>
              <tr>
                <th>{{ tr("inventory.resource", "Resource") }}</th>
                <th class="text-end">{{ tr("inventory.available", "Available") }}</th>
                <th class="text-end">{{ tr("inventory.reserved", "Reserved") }}</th>
                <th class="text-end">{{ tr("inventory.expected", "Expected") }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="resource in resources" :key="resource.id">
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <img
                      v-if="resource.icon"
                      :src="`/images/tools/${resource.icon}`"
                      alt="icon"
                      style="width: 36px; height: 36px; object-fit: contain"
                    />
                    <c-icon v-else name="cilStorage" />
                    <div>{{ getTranslatedName(resource.name) || resource.name }}</div>
                  </div>
                </td>
                <td class="text-end">
                  <strong>{{ resource.quantity }}</strong>
                </td>
                <td class="text-end">
                  <strong class="text-warning">{{ resource.reserved_quantity || 0 }}</strong>
                </td>
                <td class="text-end">
                  <strong>{{
                    (parseInt(resource.quantity) || 0) +
                    (parseInt(resource.reserved_quantity) || 0) +
                    (parseInt(resource.temp_quantity) || 0)
                  }}</strong>
                </td>
              </tr>
              <tr v-if="resources.length === 0">
                <td colspan="4" class="text-center text-muted">
                  Няма ресурси в инвентара
                </td>
              </tr>
            </tbody>
          </c-table>

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
      <c-modal-title>{{
        selectedResource?.display_name || selectedResource?.name
      }}</c-modal-title>
    </c-modal-header>
    <c-modal-body v-if="selectedResource">
      <c-row>
        <c-col md="4" class="text-center">
          <c-icon
            :name="selectedResource.icon"
            size="4xl"
            :class="selectedResource.iconColor"
          />
          <h5 class="mt-2">
            {{ selectedResource ? getTranslatedName(selectedResource.name) : "" }}
          </h5>
          <c-badge :color="getQuantityBadgeColor((selectedResource.quantity || 0) + (selectedResource.reserved_quantity || 0))" size="lg">
            Количество: {{ (parseInt(selectedResource.quantity) || 0) + (parseInt(selectedResource.reserved_quantity) || 0) }}
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
              <strong>{{ selectedResource.weight || "N/A" }} кг</strong>
            </c-list-group-item>
            <c-list-group-item class="d-flex justify-content-between">
              <span>Стойност</span>
              <strong>{{ selectedResource.value || "N/A" }} монети</strong>
            </c-list-group-item>
          </c-list-group>
        </c-col>
      </c-row>
    </c-modal-body>
    <c-modal-footer>
      <c-button color="secondary" @click="showResourceModal = false"> Затвори </c-button>
      <c-button
        v-if="selectedResource && selectedResource.actions"
        color="primary"
        @click="performAction(selectedResource.actions[0], selectedResource)"
      >
        {{ selectedResource.actions[0]?.label || "Действие" }}
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
import { ref, computed, onMounted, inject } from "vue";
import { useGameStore } from "../stores/gameStore";
import axios from "axios";

export default {
  name: "InventoryPage",
  setup() {
    const gameStore = useGameStore();
    const activeCategory = ref("all");
    const loading = ref(false);
    const message = ref("");
    const messageType = ref("");
    const showResourceModal = ref(false);
    const selectedResource = ref(null);

    const resources = ref([]);

    const $t = inject("$t");

    const tr = (key, fallback) => {
      try {
        const v = $t(key);
        return typeof v === "string" && v !== key ? v : fallback;
      } catch (e) {
        return fallback;
      }
    };

    const getTranslatedName = (name) => {
      if (!name) return "";
      const key = `tools.types.${name}`;
      try {
        const translated = $t(key);
        if (!translated || translated === key) return name;
        return translated;
      } catch (e) {
        return name;
      }
    };

    const fetchInventories = async () => {
      loading.value = true;
      try {
        const res = await axios.get("/api/inventories");
        const data = res.data;
        console.log("inventories API response", data);
        if (data && data.success) {
          resources.value = data.items.map((it) => ({
            id: it.id,
            tool_type_id: it.tool_type_id,
            name: it.tool_name || "Unknown",
            // available = total count minus reserved_count (reservation happens when placing market sell orders)
            quantity: Math.max(0, (parseInt(it.count) || 0) - (parseInt(it.reserved_count) || 0)),
            // reserved items (e.g. reserved for market orders)
            reserved_quantity: parseInt(it.reserved_count) || 0,
            // temp_quantity remains (e.g. production in progress / expected)
            temp_quantity: parseInt(it.temp_count) || 0,
            description: it.tool_description || "",
            icon: it.tool_icon || null,
            category: "materials",
            actions: [],
          }));
        } else if (data && data.success === false) {
          message.value = data.message || "Failed to load inventory";
          messageType.value = "error";
        }
      } catch (e) {
        console.error("Failed to load inventories", e);
        message.value = "Failed to load inventories";
        messageType.value = "error";
      } finally {
        loading.value = false;
      }
    };

    const filteredResources = computed(() => {
      if (activeCategory.value === "all") {
        return resources.value;
      }
      return resources.value.filter((r) => r.category === activeCategory.value);
    });

    const getQuantityBadgeColor = (quantity) => {
      if (quantity === 0) return "danger";
      if (quantity < 10) return "warning";
      if (quantity < 50) return "info";
      return "success";
    };

    // collectAllResources removed: collection should only happen when production completes on server

    const performAction = (action, resource) => {
      switch (action.name) {
        case "use":
        case "consume":
          if (resource.quantity > 0) {
            resource.quantity--;
            message.value = `Използвахте ${
              getTranslatedName(resource.name) || resource.name
            }`;
            messageType.value = "success";
          }
          break;
        case "sell":
          if (resource.quantity > 0) {
            resource.quantity--;
            message.value = `Продадохте ${
              getTranslatedName(resource.name) || resource.name
            } за ${resource.value} монети`;
            messageType.value = "success";
          }
          break;
        case "equip":
          message.value = `Екипирахте ${
            getTranslatedName(resource.name) || resource.name
          }`;
          messageType.value = "success";
          break;
        default:
          message.value = `Извършено действие: ${action.label}`;
          messageType.value = "success";
      }
      showResourceModal.value = false;
    };

    const openResourceDetails = (resource) => {
      selectedResource.value = resource;
      showResourceModal.value = true;
    };

    onMounted(() => {
      fetchInventories();
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
      performAction,
      openResourceDetails,
      fetchInventories,
      tr,
      getTranslatedName,
    };
  },
};
</script>

<style scoped>
.resource-card {
  transition: transform 0.2s ease-in-out;
  cursor: pointer;
}

.resource-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.resource-icon {
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
