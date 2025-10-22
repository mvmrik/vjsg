<template>
  <div class="mt-4">
    <h5>{{ $t('settings.remembered_devices') }}</h5>
    <p class="text-muted small">{{ $t('settings.remembered_devices_explain') }}</p>
    <div v-if="loading">{{ $t('settings.loading') }}...</div>
    <div v-else>
      <table class="table table-striped">
        <thead>
          <tr>
            <th>{{ $t('settings.device') }}</th>
            <th>{{ $t('settings.last_used') }}</th>
            <th>{{ $t('settings.ip') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in tokens" :key="t.id">
            <td>{{ t.device_name || (t.user_agent ? t.user_agent.split(' ')[0] : 'Unknown') }}</td>
            <td>{{ t.last_used_at || t.created_at }}</td>
            <td>{{ t.ip || '-' }}</td>
            <td>
              <button class="btn btn-sm btn-outline-danger" @click="revoke(t.id)">{{ $t('settings.revoke') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="tokens.length === 0" class="text-muted">{{ $t('settings.no_devices') }}</div>
      <div class="mt-2">
        <button class="btn btn-sm btn-danger" @click="revokeAll" :disabled="tokens.length===0">{{ $t('settings.revoke_all') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useGameStore } from '../stores/gameStore';
const tokens = ref([]);
const loading = ref(true);
const load = async () => {
  loading.value = true;
  const r = await fetch('/api/remember-tokens');
  const data = await r.json();
  if (data.success) tokens.value = data.tokens;
  loading.value = false;
};

onMounted(load);

const revoke = async (id) => {
  if (!confirm('Revoke this device?')) return;
  const r = await fetch('/api/remember-tokens/' + id, { method: 'DELETE' });
  const data = await r.json();
  if (data.success) {
    // If server logged out this client (revoked current device), ensure client state is cleared
    if (data.logged_out) {
      const gameStore = useGameStore();
      // Server already cleared session and cookie for this device; skip server logout
      await gameStore.logout(true);
      // Force full page reload so app and legacy UI notice the logged-out state immediately
      window.location.reload();
      return;
    }
    await load();
  }
};

const revokeAll = async () => {
  if (!confirm('Revoke all remembered devices?')) return;
  const r = await fetch('/api/remember-tokens', { method: 'DELETE' });
  const data = await r.json();
  if (data.success) {
    if (data.logged_out) {
      const gameStore = useGameStore();
      await gameStore.logout();
      return;
    }
    await load();
  }
};
</script>
