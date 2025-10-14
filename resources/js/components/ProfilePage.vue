<template>
  <c-row>
    <c-col md="8" class="mx-auto">
      <!-- Profile Header -->
      <c-card class="mb-4">
        <c-card-header class="text-center">
          <c-avatar 
            :src="avatarUrl" 
            size="xl" 
            class="mb-3"
          />
          <h3 v-if="gameStore.user" class="mb-1">{{ gameStore.user.username }}</h3>
          <p class="text-muted mb-0" v-if="gameStore.user">
            <c-icon name="cilKey" class="me-1" />
            Key Auth System
          </p>
        </c-card-header>
        <c-card-body>
          <c-nav variant="tabs" role="tablist">
            <c-nav-item>
              <c-nav-link
                href="#profile-tab"
                :active="activeTab === 'profile'"
                @click="activeTab = 'profile'"
              >
                <c-icon name="cilUser" class="me-2" />
                Профил
              </c-nav-link>
            </c-nav-item>
            <c-nav-item>
              <c-nav-link
                href="#stats-tab"
                :active="activeTab === 'stats'"
                @click="activeTab = 'stats'"
              >
                <c-icon name="cilChart" class="me-2" />
                Статистики
              </c-nav-link>
            </c-nav-item>
            <c-nav-item>
              <c-nav-link
                href="#settings-tab"
                :active="activeTab === 'settings'"
                @click="activeTab = 'settings'"
              >
                <c-icon name="cilSettings" class="me-2" />
                Настройки
              </c-nav-link>
            </c-nav-item>
          </c-nav>
        </c-card-body>
      </c-card>

      <!-- Profile Tab Content -->
      <c-card v-show="activeTab === 'profile'">
        <c-card-header>
          <strong>Информация за профила</strong>
        </c-card-header>
        <c-card-body>
          <c-form @submit.prevent="updateProfile">
            <c-row class="mb-3">
              <c-col md="12">
                <c-form-label>Потребителско име</c-form-label>
                <c-form-input
                  v-model="profileForm.username"
                  :value="gameStore.user?.username"
                  disabled
                />
              </c-col>
            </c-row>

            <c-row class="mb-3" v-if="gameStore.user?.public_key">
              <c-col md="12">
                <c-form-label>Публичен ключ</c-form-label>
                <c-input-group>
                  <c-form-input
                    :value="gameStore.user.public_key"
                    readonly
                    class="font-monospace"
                  />
                  <c-button
                    type="button"
                    color="outline-secondary"
                    @click="copyToClipboard(gameStore.user.public_key)"
                  >
                    <c-icon name="cilCopy" />
                  </c-button>
                </c-input-group>
                <c-form-text>
                  <c-icon 
                    :name="copied ? 'cilCheckCircle' : 'cilInfo'" 
                    :class="copied ? 'text-success' : 'text-info'"
                    class="me-1"
                  />
                  {{ copied ? 'Копирано в clipboard!' : 'Публичният ключ може да се споделя безопасно.' }}
                </c-form-text>
              </c-col>
            </c-row>

            <c-row class="mb-3" v-if="gameStore.user?.private_key">
              <c-col md="12">
                <c-form-label>Частен ключ</c-form-label>
                <c-input-group>
                  <c-form-input
                    :value="showPrivateKey ? gameStore.user.private_key : '•'.repeat(64)"
                    readonly
                    class="font-monospace"
                  />
                  <c-button
                    type="button"
                    :color="showPrivateKey ? 'warning' : 'outline-secondary'"
                    @click="showPrivateKey = !showPrivateKey"
                  >
                    <c-icon :name="showPrivateKey ? 'cilEyeSlash' : 'cilEye'" />
                  </c-button>
                  <c-button
                    type="button"
                    color="outline-secondary"
                    @click="copyToClipboard(gameStore.user.private_key)"
                    v-if="showPrivateKey"
                  >
                    <c-icon name="cilCopy" />
                  </c-button>
                </c-input-group>
                <c-form-text class="text-danger">
                  <c-icon name="cilWarning" class="me-1" />
                  ВНИМАНИЕ: Никога не споделяйте частния си ключ! Той е нужен за влизане в системата.
                </c-form-text>
              </c-col>
            </c-row>

            <c-row class="mb-3">
              <c-col md="12">
                <c-form-label>Дата на регистрация</c-form-label>
                <c-form-input
                  :value="formatDate(gameStore.user?.created_at)"
                  readonly
                />
              </c-col>
            </c-row>
          </c-form>
        </c-card-body>
      </c-card>

      <!-- Stats Tab Content -->
      <c-card v-show="activeTab === 'stats'">
        <c-card-header>
          <strong>Игрови статистики</strong>
        </c-card-header>
        <c-card-body>
          <c-row>
            <c-col sm="6" md="3" class="mb-3">
              <c-card class="text-center">
                <c-card-body>
                  <c-icon name="cilStorage" size="2xl" class="text-primary mb-2" />
                  <h4 class="mb-1">{{ gameStats.resources }}</h4>
                  <p class="text-muted mb-0">Ресурси</p>
                </c-card-body>
              </c-card>
            </c-col>
            
            <c-col sm="6" md="3" class="mb-3">
              <c-card class="text-center">
                <c-card-body>
                  <c-icon name="cilStar" size="2xl" class="text-warning mb-2" />
                  <h4 class="mb-1">{{ gameStats.level }}</h4>
                  <p class="text-muted mb-0">Ниво</p>
                </c-card-body>
              </c-card>
            </c-col>
            
            <c-col sm="6" md="3" class="mb-3">
              <c-card class="text-center">
                <c-card-body>
                  <c-icon name="cilChart" size="2xl" class="text-success mb-2" />
                  <h4 class="mb-1">{{ gameStats.experience }}</h4>
                  <p class="text-muted mb-0">Опит</p>
                </c-card-body>
              </c-card>
            </c-col>
            
            <c-col sm="6" md="3" class="mb-3">
              <c-card class="text-center">
                <c-card-body>
                  <c-icon name="cilMap" size="2xl" class="text-info mb-2" />
                  <h4 class="mb-1">{{ gameStats.parcels }}</h4>
                  <p class="text-muted mb-0">Парцели</p>
                </c-card-body>
              </c-card>
            </c-col>
          </c-row>

          <!-- Progress bars -->
          <div class="mt-4">
            <h6>Прогрес към следващо ниво</h6>
            <c-progress class="mb-3">
              <c-progress-bar 
                :value="levelProgress" 
                color="success"
              >
                {{ levelProgress }}%
              </c-progress-bar>
            </c-progress>

            <h6>Активност този месец</h6>
            <c-progress>
              <c-progress-bar 
                :value="monthlyActivity" 
                color="info"
              >
                {{ monthlyActivity }}%
              </c-progress-bar>
            </c-progress>
          </div>
        </c-card-body>
      </c-card>

      <!-- Settings Tab Content -->
      <c-card v-show="activeTab === 'settings'">
        <c-card-header>
          <strong>Настройки на профила</strong>
        </c-card-header>
        <c-card-body>
          <c-form>
            <div class="mb-4">
              <h6>Нотификации</h6>
              <c-form-check>
                <c-form-check-input v-model="settings.emailNotifications" />
                <c-form-check-label>Email известия</c-form-check-label>
              </c-form-check>
              <c-form-check>
                <c-form-check-input v-model="settings.gameNotifications" />
                <c-form-check-label>Игрови известия</c-form-check-label>
              </c-form-check>
            </div>

            <div class="mb-4">
              <h6>Приватност</h6>
              <c-form-check>
                <c-form-check-input v-model="settings.profileVisible" />
                <c-form-check-label>Видим профил</c-form-check-label>
              </c-form-check>
              <c-form-check>
                <c-form-check-input v-model="settings.showStats" />
                <c-form-check-label>Показвай статистики</c-form-check-label>
              </c-form-check>
            </div>

            <div class="d-grid gap-2 d-md-flex">
              <c-button color="primary" @click="saveSettings">
                <c-icon name="cilSave" class="me-2" />
                Запази настройки
              </c-button>
              <c-button color="danger" variant="outline" @click="confirmDelete = true">
                <c-icon name="cilTrash" class="me-2" />
                Изтрий акаунт
              </c-button>
            </div>
          </c-form>
        </c-card-body>
      </c-card>

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
    </c-col>
  </c-row>

  <!-- Delete Confirmation Modal -->
  <c-modal v-model="confirmDelete">
    <c-modal-header>
      <c-modal-title>Потвърждение за изтриване</c-modal-title>
    </c-modal-header>
    <c-modal-body>
      Сигурни ли сте, че искате да изтриете акаунта си? Това действие не може да бъде отменено.
    </c-modal-body>
    <c-modal-footer>
      <c-button color="secondary" @click="confirmDelete = false">
        Отказ
      </c-button>
      <c-button color="danger" @click="deleteAccount">
        Изтрий акаунта
      </c-button>
    </c-modal-footer>
  </c-modal>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useGameStore } from '../stores/gameStore';

export default {
  name: 'ProfilePage',
  setup() {
    const router = useRouter();
    const gameStore = useGameStore();
    
    const activeTab = ref('profile');
    const copied = ref(false);
    const message = ref('');
    const messageType = ref('');
    const confirmDelete = ref(false);

    const showPrivateKey = ref(false);
    
    const profileForm = ref({
      username: ''
    });

    const gameStats = ref({
      resources: 1250,
      level: 15,
      experience: 3450,
      parcels: 3
    });

    const settings = ref({
      emailNotifications: true,
      gameNotifications: true,
      profileVisible: true,
      showStats: true
    });

    const avatarUrl = computed(() => {
      if (gameStore.user && gameStore.user.username) {
        const initial = gameStore.user.username.charAt(0).toUpperCase();
        return `https://via.placeholder.com/80x80/20A8D8/ffffff?text=${initial}`;
      }
      return 'https://via.placeholder.com/80x80/20A8D8/ffffff?text=U';
    });

    const levelProgress = computed(() => {
      const baseExp = gameStats.value.level * 100;
      const nextLevelExp = (gameStats.value.level + 1) * 100;
      const currentExp = gameStats.value.experience - baseExp;
      const requiredExp = nextLevelExp - baseExp;
      return Math.round((currentExp / requiredExp) * 100);
    });

    const monthlyActivity = computed(() => {
      // Mock calculation - replace with real data
      return 75;
    });
    
    const copyToClipboard = (text) => {
      navigator.clipboard.writeText(text).then(() => {
        copied.value = true;
        message.value = 'Успешно копирано в clipboard!';
        messageType.value = 'success';
        setTimeout(() => {
          copied.value = false;
          message.value = '';
        }, 2000);
      }).catch(err => {
        console.error('Failed to copy: ', err);
        message.value = 'Грешка при копиране';
        messageType.value = 'error';
      });
    };

    const formatDate = (dateString) => {
      if (!dateString) return 'N/A';
      return new Date(dateString).toLocaleDateString('bg-BG');
    };

    const updateProfile = () => {
      message.value = 'Профилът беше обновен успешно!';
      messageType.value = 'success';
    };

    const saveSettings = () => {
      // Save settings logic
      message.value = 'Настройките бяха запазени успешно!';
      messageType.value = 'success';
    };

    const deleteAccount = async () => {
      try {
        // Delete account logic
        await gameStore.logout();
        router.push('/');
      } catch (error) {
        message.value = 'Грешка при изтриване на акаунта';
        messageType.value = 'error';
      }
      confirmDelete.value = false;
    };
    
    onMounted(() => {
      // If not authenticated, redirect to home
      if (!gameStore.isAuthenticated) {
        router.push('/');
        return;
      }
      
      // Fetch fresh user data
      if (gameStore.fetchUserData) {
        gameStore.fetchUserData();
      }

      // Load user settings and stats
      if (gameStore.user) {
        profileForm.value.username = gameStore.user.username;
      }
    });
    
    return {
      gameStore,
      activeTab,
      copied,
      message,
      messageType,
      confirmDelete,
      showPrivateKey,
      profileForm,
      gameStats,
      settings,
      avatarUrl,
      levelProgress,
      monthlyActivity,
      copyToClipboard,
      formatDate,
      updateProfile,
      saveSettings,
      deleteAccount
    };
  }
}
</script>