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
              <c-nav-link href="#display-tab" :active="activeTab === 'display'" @click="activeTab = 'display'">
                <c-icon name="cilScreenDesktop" class="me-2" />
                {{ $t('settings.display') }}
              </c-nav-link>
            </c-nav-item>
            <c-nav-item>
              <c-nav-link href="#game-settings-tab" :active="activeTab === 'game_settings'" @click="activeTab = 'game_settings'">
                <c-icon name="cilGamepad" class="me-2" />
                {{ $t('settings.game_settings') }}
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

            <!-- Delete account button moved below remembered devices to avoid accidental clicks -->

            <!-- Remembered devices -->
            <profile-devices />

            <!-- Delete account (moved below devices list) -->
            <div class="mt-3 d-flex justify-content-center">
              <c-button color="danger" variant="outline" @click="showDeleteConfirm">
                <c-icon name="cilTrash" class="me-2" />
                {{ $t('settings.delete_account') }}
              </c-button>
            </div>

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
                  <c-icon name="cilChart" size="xl" class="text-warning mb-2" />
                  <h4 class="mb-1">{{ gameStats.occupied }}</h4>
                  <p class="text-muted mb-0">{{ $t('settings.occupied_workers') }}</p>
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
            <c-col class="col-sm-6 col-md-3 mb-3">
              <c-card class="text-center">
                <c-card-body>
                  <c-icon name="cilBuilding" size="xl" class="text-success mb-2" />
                  <h4 class="mb-1">{{ gameStats.objects }}</h4>
                  <p class="text-muted mb-0">{{ $t('settings.buildings') }}</p>
                </c-card-body>
              </c-card>
            </c-col>
          </c-row>

          <!-- Progress bars -->
          <div class="mt-4">
            <h6>{{ $t('settings.health') }}</h6>
            <p class="text-muted small">
              {{ $t('settings.expected_mortality_desc') }}
              <span v-if="gameStats.death_threshold_level !== null">. {{ $t('settings.death_threshold') }}: {{ $t('settings.level') }} {{ gameStats.death_threshold_level }}</span>
            </p>
            <div class="position-relative mb-4" style="height: 40px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
              <c-progress style="height: 100%; border-radius: 8px; background: transparent;">
                <c-progress-bar
                  :value="Math.min(Math.max(Math.round(gameStats.expected_mortality), 0), 100)"
                  color="danger"
                  style="transition: width 0.3s ease;"
                />
              </c-progress>
              <div class="position-absolute w-100 h-100 d-flex align-items-center justify-content-center" style="top: 0; left: 0; pointer-events: none;">
                <strong style="font-size: 1rem; text-shadow: 0 0 3px white, 0 0 3px white, 0 0 3px white;">
                  {{ Math.min(Math.max((gameStats.expected_mortality || 0).toFixed(2), 0), 100) }}%
                </strong>
              </div>
            </div>

            <h6>{{ $t('settings.activity_this_month') }}</h6>
            <div class="position-relative mb-3" style="height: 40px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
              <c-progress style="height: 100%; border-radius: 8px; background: transparent;">
                <c-progress-bar 
                  :value="monthlyActivity" 
                  color="info"
                  style="transition: width 0.3s ease;"
                />
              </c-progress>
              <div class="position-absolute w-100 h-100 d-flex align-items-center justify-content-center" style="top: 0; left: 0; pointer-events: none;">
                <strong style="font-size: 1rem; text-shadow: 0 0 3px white, 0 0 3px white, 0 0 3px white;">
                  {{ monthlyActivity }}%
                </strong>
              </div>
            </div>
          </div>
        </c-card-body>
      </c-card>

      <!-- Display Tab Content (was Settings) -->
      <c-card v-show="activeTab === 'display'">
        <c-card-header>
          <strong>{{ $t('settings.display_settings') }}</strong>
        </c-card-header>
        <c-card-body>
          <c-form>
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
            </div>
          </c-form>
        </c-card-body>
      </c-card>

      <!-- Game Settings Tab Content -->
      <c-card v-show="activeTab === 'game_settings'">
        <c-card-header>
          <strong>{{ $t('settings.game_settings') }}</strong>
        </c-card-header>
        <c-card-body>
          <c-form>
            <div class="mb-3">
              <c-form-label>{{ $t('settings.production_length_hours') }}</c-form-label>
              <div class="d-flex align-items-center gap-3">
                <input type="range" min="1" max="24" v-model.number="gameSettings.production_length_hours" @change="saveGameSetting('production_length_hours', gameSettings.production_length_hours)" class="form-range" />
                <div class="fw-bold">{{ gameSettings.production_length_hours }}h</div>
              </div>
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

  <!-- Delete confirmation removed: delete button is no-op and logs to console -->
</template>

<script>
import { ref, computed, onMounted, watch, inject } from 'vue';
import { useRouter } from 'vue-router';
import { useGameStore } from '../stores/gameStore';
import ProfileDevices from './ProfileDevices.vue';

export default {
  name: 'ProfilePage',
  components: {
    ProfileDevices
  },
  setup() {
    const router = useRouter();
    const gameStore = useGameStore();
    const $t = inject('$t');
    
    const activeTab = ref('profile');
  const copied = ref(false);
  const message = ref('');
  const messageType = ref('');

    const showPrivateKey = ref(false);
    
    const profileForm = ref({
      username: ''
    });

    const gameStats = ref({
      people: 0,
      buildings: 0,
      resources: 0,
      level: 0,
      experience: 0,
      parcels: 0,
      objects: 0,
      hospital_capacity: 0,
      population: 0,
      expected_mortality: 0, // percent
      death_threshold_level: null,
      occupied: 0
    });

    const settings = ref({
      emailNotifications: true,
      gameNotifications: true,
      profileVisible: true,
      showStats: true,
      language: 'en' // default
    });

    const gameSettings = ref({
      production_length_hours: 12
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
        // Currently a no-op server-side; keep logout behavior but do not show modal
        await gameStore.logout();
        router.push('/');
      } catch (error) {
        message.value = $t('settings.error_deleting_account');
        messageType.value = 'error';
      }
    };

    const showDeleteConfirm = () => {
      // For now just log the click (no modal or server-side deletion yet)
      console.log('Delete account clicked (no-op)');
    };

    const saveGameSetting = async (key, value) => {
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) return;
        await fetch('/api/game-settings', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
          body: JSON.stringify({ key, value: String(value) })
        });
        // Dispatch global toast handled by GameApp.vue (bottom-right)
        try {
          window.dispatchEvent(new CustomEvent('show-toast', { detail: { message: $t('settings.settings_saved') || 'Saved', type: 'success' } }));
        } catch (e) {
          // fallback to inline message
          message.value = $t('settings.settings_saved') || 'Saved';
          messageType.value = 'success';
          setTimeout(() => { message.value = ''; }, 1800);
        }
      } catch (e) {
        console.error('Failed to save game setting', key, e);
        try {
          window.dispatchEvent(new CustomEvent('show-toast', { detail: { message: 'Error saving setting', type: 'error' } }));
        } catch (e) {
          message.value = 'Error saving setting';
          messageType.value = 'error';
        }
      }
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
    
    onMounted(async () => {
      // If not authenticated, redirect to home
      if (!gameStore.isAuthenticated) {
        router.push('/');
        return;
      }
      // Fetch fresh user data and wait for it so we can read user-specific settings
      if (gameStore.fetchUserData) {
        await gameStore.fetchUserData();
      }

      // Load game statistics (parcels, objects, population, expected mortality)
      const loadStats = async () => {
        try {
          const res = await fetch('/api/stats');
          if (!res.ok) {
            console.error('Failed to fetch stats', res.status);
            return;
          }
          const d = await res.json();
          if (!d.success) return;
          gameStats.value.parcels = d.parcels ?? 0;
          gameStats.value.objects = d.objects ?? 0;
          gameStats.value.people = d.population ?? 0;
          gameStats.value.population = d.population ?? 0;
          gameStats.value.hospital_capacity = d.hospital_capacity ?? 0;
          gameStats.value.expected_mortality = (typeof d.expectedMortality === 'number') ? Number(d.expectedMortality) : 0;
          gameStats.value.death_threshold_level = (typeof d.death_threshold_level === 'number') ? Number(d.death_threshold_level) : null;
          gameStats.value.occupied = (typeof d.occupied === 'number') ? Number(d.occupied) : 0;
        } catch (e) {
          console.error('loadStats error', e);
        }
      };

      await loadStats();

      // Load user settings and stats (after user is available)
      if (gameStore.user) {
        profileForm.value.username = gameStore.user.username;
        settings.value.language = userLanguage.value;
        // load per-user game settings
        try {
          const r = await fetch('/api/game-settings');
          const data = await r.json();
          if (data.success && data.settings) {
            const s = data.settings;
            gameSettings.value.production_length_hours = s.production_length_hours ? parseInt(s.production_length_hours) : 12;
          } else {
            gameSettings.value.production_length_hours = 12;
          }
        } catch (e) {
          gameSettings.value.production_length_hours = 12;
        }
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
      gameSettings,
      saveGameSetting,
      deleteAccount,
      showDeleteConfirm,
      logout
    };
  }
}
</script>