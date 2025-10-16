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
                  {{ $t('settings.profile') }}
                </c-nav-link>
            </c-nav-item>
            <c-nav-item>
                              <c-nav-link
                  href="#stats-tab"
                  :active="activeTab === 'stats'"
                  @click="activeTab = 'stats'"
                >
                  <c-icon name="cilChart" class="me-2" />
                  {{ $t('settings.stats') }}
                </c-nav-link>
            </c-nav-item>
            <c-nav-item>
                              <c-nav-link
                  href="#settings-tab"
                  :active="activeTab === 'settings'"
                  @click="activeTab = 'settings'"
                >
                  <c-icon name="cilSettings" class="me-2" />
                  {{ $t('settings.settings') }}
                </c-nav-link>
            </c-nav-item>
            <c-nav-item>
                              <c-nav-link
                  href="#logout-tab"
                  :active="activeTab === 'logout'"
                  @click="activeTab = 'logout'"
                >
                  <c-icon name="cilAccountLogout" class="me-2" />
                  {{ $t('settings.logout') }}
                </c-nav-link>
            </c-nav-item>
          </c-nav>
        </c-card-body>
      </c-card>

      <!-- Profile Tab Content -->
      <c-card v-show="activeTab === 'profile'">
        <c-card-header>
          <strong>{{ $t('settings.profile_info') }}</strong>
        </c-card-header>
        <c-card-body>
          <c-form @submit.prevent="updateProfile">
            <c-row class="mb-3">
              <c-col md="12">
                <c-form-label>{{ $t('settings.username') }}</c-form-label>
                <c-form-input
                  v-model="profileForm.username"
                  :value="gameStore.user?.username"
                  disabled
                />
              </c-col>
            </c-row>

            <c-row class="mb-3" v-if="gameStore.user?.private_key">
              <c-col md="12">
                <c-form-label>{{ $t('settings.private_key') }}</c-form-label>
                <c-input-group>
                  <c-form-input
                    :value="showPrivateKey ? gameStore.user.private_key : '•'.repeat(64)"
                    readonly
                    class="font-monospace"
                  />
                  <c-button
                    type="button"
                    color="secondary"
                    @click="showPrivateKey ? copyToClipboard(gameStore.user.private_key) : (showPrivateKey = true)"
                  >
                    <span v-if="!showPrivateKey">👁</span>
                    <c-icon v-else name="cilCopy" />
                  </c-button>
                </c-input-group>
                <c-form-text class="text-danger">
                  <c-icon name="cilWarning" class="me-1" />
                  {{ $t('settings.warning') }}: {{ $t('settings.never_share_private_key') }}
                </c-form-text>
              </c-col>
            </c-row>

            <c-row class="mb-3" v-if="gameStore.user?.public_key">
              <c-col md="12">
                <c-form-label>{{ $t('public_key') }}</c-form-label>
                <c-input-group>
                  <c-form-input
                    :value="gameStore.user.public_key"
                    readonly
                    class="font-monospace"
                  />
                  <c-button
                    type="button"
                    color="secondary"
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
                                    {{ copied ? $t('global.successfully_copied') : $t('settings.public_key_safe') }}
                </c-form-text>
              </c-col>
            </c-row>

          </c-form>
        </c-card-body>
      </c-card>

      <!-- Stats Tab Content -->
      <c-card v-show="activeTab === 'stats'">
        <c-card-header>
          <strong>{{ $t('settings.game_stats') }}</strong>
        </c-card-header>
        <c-card-body>
          <c-row>
            <c-col class="col-sm-6 col-md-3 mb-3">
              <c-card class="text-center">
                <c-card-body>
                  <c-icon name="cilPeople" size="xl" class="text-primary mb-2" />
                  <h4 class="mb-1">{{ gameStats.people }}</h4>
                  <p class="text-muted mb-0">{{ $t('settings.people') }}</p>
                </c-card-body>
              </c-card>
            </c-col>
            <c-col class="col-sm-6 col-md-3 mb-3">
              <c-card class="text-center">
                <c-card-body>
                  <c-icon name="cilBuilding" size="xl" class="text-success mb-2" />
                  <h4 class="mb-1">{{ gameStats.buildings }}</h4>
                  <p class="text-muted mb-0">{{ $t('settings.buildings') }}</p>
                </c-card-body>
              </c-card>
            </c-col>
            <c-col class="col-sm-6 col-md-3 mb-3">
              <c-card class="text-center">
                <c-card-body>
                  <c-icon name="cilChart" size="xl" class="text-warning mb-2" />
                  <h4 class="mb-1">-</h4>
                  <p class="text-muted mb-0">{{ $t('settings.new') }}</p>
                </c-card-body>
              </c-card>
            </c-col>
            <c-col class="col-sm-6 col-md-3 mb-3">
              <c-card class="text-center">
                <c-card-body>
                  <c-icon name="cilMap" size="xl" class="text-info mb-2" />
                  <h4 class="mb-1">{{ gameStats.parcels }}</h4>
                  <p class="text-muted mb-0">{{ $t('settings.parcels') }}</p>
                </c-card-body>
              </c-card>
            </c-col>
          </c-row>

          <!-- Progress bars -->
          <div class="mt-4">
            <h6>{{ $t('settings.progress_to_next_level') }}</h6>
            <c-progress class="mb-3">
              <c-progress-bar 
                :value="levelProgress" 
                color="success"
              >
                {{ levelProgress }}%
              </c-progress-bar>
            </c-progress>

            <h6>{{ $t('settings.activity_this_month') }}</h6>
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
          <strong>{{ $t('settings.profile_settings') }}</strong>
        </c-card-header>
        <c-card-body>
          <c-form>
            <div class="mb-4">
              <h6>{{ $t('settings.notifications') }}</h6>
              <c-form-check>
                <c-form-check-input v-model="settings.emailNotifications" />
                <c-form-check-label>{{ $t('settings.email_notifications') }}</c-form-check-label>
              </c-form-check>
              <c-form-check>
                <c-form-check-input v-model="settings.gameNotifications" />
                <c-form-check-label>{{ $t('settings.game_notifications') }}</c-form-check-label>
              </c-form-check>
            </div>

            <div class="mb-4">
              <h6>{{ $t('settings.privacy') }}</h6>
              <c-form-check>
                <c-form-check-input v-model="settings.profileVisible" />
                <c-form-check-label>{{ $t('settings.visible_profile') }}</c-form-check-label>
              </c-form-check>
              <c-form-check>
                <c-form-check-input v-model="settings.showStats" />
                <c-form-check-label>{{ $t('settings.show_stats') }}</c-form-check-label>
              </c-form-check>
            </div>

            <div class="mb-4">
              <h6>{{ $t('global.language') }}</h6>
              <c-form-select v-model="settings.language">
                <option value="en">{{ $t('global.english') }}</option>
                <option value="bg">{{ $t('global.bulgarian') }}</option>
              </c-form-select>
            </div>

            <div class="d-grid gap-2 d-md-flex">
              <c-button color="primary" @click="saveSettings">
                <c-icon name="cilSave" class="me-2" />
                {{ $t('settings.save_settings') }}
              </c-button>
              <c-button color="danger" variant="outline" @click="confirmDelete = true">
                <c-icon name="cilTrash" class="me-2" />
                {{ $t('settings.delete_account') }}
              </c-button>
            </div>
          </c-form>
        </c-card-body>
      </c-card>

      <!-- Logout Tab Content -->
      <c-card v-show="activeTab === 'logout'">
        <c-card-header>
          <strong>{{ $t('settings.logout_from_system') }}</strong>
        </c-card-header>
        <c-card-body>
          <div class="text-center">
            <c-icon name="cilAccountLogout" size="4xl" class="text-warning mb-4" />
            <h5 class="mb-3">{{ $t('settings.ready_to_logout') }}</h5>
            <p class="text-muted mb-4">
              {{ $t('settings.will_redirect_to_home') }}
            </p>
            <c-button color="primary" size="lg" @click="logout()">
              <c-icon name="cilAccountLogout" class="me-2" />
              {{ $t('settings.logout_from_account') }}
            </c-button>
          </div>
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
      <c-modal-title>{{ $t('settings.confirmation_delete') }}</c-modal-title>
    </c-modal-header>
    <c-modal-body>
      {{ $t('settings.sure_delete_account') }}
    </c-modal-body>
    <c-modal-footer>
      <c-button color="secondary" @click="confirmDelete = false">
        {{ $t('cancel') }}
      </c-button>
      <c-button color="danger" @click="deleteAccount">
        {{ $t('settings.delete_account_btn') }}
      </c-button>
    </c-modal-footer>
  </c-modal>
</template>

<script>
import { ref, computed, onMounted, watch, inject } from 'vue';
import { useRouter } from 'vue-router';
import { useGameStore } from '../stores/gameStore';

export default {
  name: 'ProfilePage',
  setup() {
    const router = useRouter();
    const gameStore = useGameStore();
    const $t = inject('$t');
    
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
      people: 2,
      buildings: 0,
      resources: 1250,
      level: 15,
      experience: 3450,
      parcels: 3
    });

    const settings = ref({
      emailNotifications: true,
      gameNotifications: true,
      profileVisible: true,
      showStats: true,
      language: 'en' // default
    });

    const userLanguage = computed(() => {
      const lang = gameStore.user?.locale;
      return lang || 'en';
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
        message.value = $t('global.successfully_copied');
        messageType.value = 'success';
        setTimeout(() => {
          copied.value = false;
          message.value = '';
        }, 2000);
      }).catch(err => {
        console.error('Failed to copy: ', err);
        message.value = $t('global.copy_error');
        messageType.value = 'error';
      });
    };

    const updateProfile = () => {
        message.value = $t('settings.profile_updated');
      messageType.value = 'success';
    };

    const saveSettings = async () => {
      try {
        console.log('Saving settings, language:', settings.value.language);
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
          console.error('CSRF token not found');
          message.value = 'Грешка при запазване на настройките';
          messageType.value = 'error';
          return;
        }

        const response = await fetch('/api/user-data', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({
            locale: settings.value.language
          })
        });

        console.log('Response status:', response.status);

        if (!response.ok) {
          console.error('HTTP error:', response.status, response.statusText);
          message.value = 'Грешка при запазване на настройките';
          messageType.value = 'error';
          return;
        }

        const data = await response.json();
        console.log('Response data:', data);
        
        if (data.success) {
          message.value = $t('settings.settings_saved');
          messageType.value = 'success';
          // Update user data in store
          if (gameStore.user) {
            gameStore.user.locale = settings.value.language;
          }
          // Reload page to apply new language
          window.location.reload();
        } else {
          console.error('API error:', data.message);
          message.value = 'Грешка при запазване на настройките';
          messageType.value = 'error';
        }
      } catch (error) {
        console.error('Save settings error:', error);
        message.value = 'Грешка при запазване на настройките';
        messageType.value = 'error';
      }
    };

    const deleteAccount = async () => {
      try {
        // Delete account logic
        await gameStore.logout();
        router.push('/');
      } catch (error) {
        message.value = $t('settings.error_deleting_account');
        messageType.value = 'error';
      }
      confirmDelete.value = false;
    };

    const logout = async () => {
      try {
        await gameStore.logout();
        router.push('/');
      } catch (error) {
        message.value = $t('settings.error_logging_out');
        messageType.value = 'error';
      }
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
        settings.value.language = userLanguage.value;
      }
    });

    // Watch for user language changes
    watch(userLanguage, (newLang) => {
      settings.value.language = newLang;
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
      updateProfile,
      saveSettings,
      deleteAccount,
      logout
    };
  }
}
</script>