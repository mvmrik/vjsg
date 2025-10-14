<template>
  <div>
    <!-- Tab Navigation -->
    <c-nav variant="tabs" role="tablist" class="mb-3">
      <c-nav-item>
        <c-nav-link
          :active="activeTab === 'login'"
          @click="activeTab = 'login'"
          class="cursor-pointer"
        >
          <c-icon name="cilAccountLogout" class="me-2" />
          Вход
        </c-nav-link>
      </c-nav-item>
      <c-nav-item>
        <c-nav-link
          :active="activeTab === 'register'"
          @click="activeTab = 'register'"
          class="cursor-pointer"
        >
          <c-icon name="cilUserPlus" class="me-2" />
          Регистрация
        </c-nav-link>
      </c-nav-item>
    </c-nav>

    <!-- Login Tab -->
    <div v-show="activeTab === 'login'">
      <c-form @submit.prevent="handleLogin">
        <c-input-group class="mb-3">
          <c-input-group-text>
            <c-icon name="cilLockLocked" />
          </c-input-group-text>
          <c-form-input
            v-model="loginForm.privateKey"
            placeholder="Частен ключ (64 символа)"
            type="text"
            maxlength="64"
            required
          />
        </c-input-group>
        
        <c-form-text class="mb-3 text-muted">
          <c-icon name="cilInfo" class="me-1" />
          Въведете вашия 64-символен частен ключ за вход в системата
        </c-form-text>

        <c-button
          type="submit"
          color="primary"
          class="w-100"
          :disabled="loading || loginForm.privateKey.length !== 64"
        >
          <c-spinner
            v-if="loading"
            size="sm"
            class="me-2"
          />
          {{ loading ? 'Влизане...' : 'Влез' }}
        </c-button>
      </c-form>
    </div>

    <!-- Register Tab -->
    <div v-show="activeTab === 'register'">
      <c-form @submit.prevent="handleRegister">
        <c-input-group class="mb-3">
          <c-input-group-text>
            <c-icon name="cilUser" />
          </c-input-group-text>
          <c-form-input
            v-model="registerForm.username"
            placeholder="Потребителско име"
            required
            :minlength="3"
          />
        </c-input-group>
        
        <c-form-text class="mb-3 text-muted">
          <c-icon name="cilInfo" class="me-1" />
          Минимум 3 символа за потребителско име
        </c-form-text>

        <c-button
          type="submit"
          color="success"
          class="w-100"
          :disabled="loading || registerForm.username.length < 3"
        >
          <c-spinner
            v-if="loading"
            size="sm"
            class="me-2"
          />
          {{ loading ? 'Регистрация...' : 'Създай акаунт' }}
        </c-button>
      </c-form>
    </div>

    <!-- Error/Success Messages -->
    <c-alert
      v-if="message"
      :color="messageType === 'success' ? 'success' : 'danger'"
      :visible="true"
      dismissible
      @close="message = ''"
      class="mt-3"
    >
      <div v-html="message"></div>
    </c-alert>
  </div>
</template>

<script>
import { ref } from 'vue';
import { useGameStore } from '../stores/gameStore';

export default {
  name: 'LoginForm',
  emits: ['login-success'],
  setup(props, { emit }) {
    const gameStore = useGameStore();
    const loading = ref(false);
    const message = ref('');
    const messageType = ref('');
    const activeTab = ref('login'); // Default to login tab

    const loginForm = ref({
      privateKey: ''
    });

    const registerForm = ref({
      username: ''
    });

    const handleLogin = async () => {
      loading.value = true;
      message.value = '';

      try {
        await gameStore.login(loginForm.value.privateKey);
        message.value = 'Успешно влизане!';
        messageType.value = 'success';
        setTimeout(() => {
          emit('login-success');
        }, 1000);
      } catch (err) {
        message.value = err.message || gameStore.error || 'Грешка при влизане';
        messageType.value = 'error';
      } finally {
        loading.value = false;
      }
    };

    const handleRegister = async () => {
      loading.value = true;
      message.value = '';

      try {
        const result = await gameStore.register(registerForm.value.username);
        message.value = `✅ ${result.message}<br><br>
          <strong>📱 Вашите ключове:</strong><br>
          <strong>Публичен:</strong> ${result.user.public_key}<br>
          <strong>Частен:</strong> ${result.user.private_key}<br><br>
          ⚠️ <strong>ВАЖНО:</strong> Запазете частния ключ сигурно - нужен е за влизане!`;
        messageType.value = 'success';
        registerForm.value.username = '';
        
        // Auto switch to login tab after successful registration
        setTimeout(() => {
          activeTab.value = 'login';
        }, 3000);
      } catch (err) {
        message.value = err.message || gameStore.error || 'Грешка при регистрация';
        messageType.value = 'error';
      } finally {
        loading.value = false;
      }
    };

    return {
      activeTab,
      loginForm,
      registerForm,
      loading,
      message,
      messageType,
      handleLogin,
      handleRegister
    };
  }
}
</script>