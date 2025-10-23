<template>
  <c-row>
    <c-col md="12">
      <c-card>
        <c-card-header>
          <div class="d-flex justify-content-between align-items-center">
            <strong>{{ $t('notifications.title') }}</strong>
            <c-button
              v-if="unreadCount > 0"
              color="primary"
              size="sm"
              @click="markAllAsRead"
              :disabled="loading"
            >
              <c-icon name="cilCheckCircle" class="me-2" />
              {{ $t('notifications.mark_all_read') }}
            </c-button>
          </div>
        </c-card-header>
        <c-card-body>
          <!-- Notifications Table -->
          <c-table hover responsive v-if="notifications.data && notifications.data.length > 0">
            <c-table-head>
              <c-table-row>
                <c-table-header-cell>{{ $t('notifications.type') }}</c-table-header-cell>
                <c-table-header-cell>{{ $t('notifications.title') }}</c-table-header-cell>
                <c-table-header-cell>{{ $t('notifications.message') }}</c-table-header-cell>
                <c-table-header-cell>{{ $t('notifications.date') }}</c-table-header-cell>
                <c-table-header-cell>{{ $t('notifications.actions') }}</c-table-header-cell>
              </c-table-row>
            </c-table-head>
            <c-table-body>
              <c-table-row
                v-for="notification in notifications.data"
                :key="notification.id"
                :class="{ 'table-light': !notification.is_read }"
              >
                <c-table-data-cell>
                  <c-badge
                    :color="getTypeColor(notification.type)"
                    class="text-uppercase"
                  >
                    {{ getTypeLabel(notification.type) }}
                  </c-badge>
                </c-table-data-cell>
                <c-table-data-cell>
                  <strong>{{ $t(notification.title) }}</strong>
                  <c-icon
                    v-if="!notification.is_read"
                    name="cilCircle"
                    class="text-primary ms-2"
                    size="sm"
                  />
                </c-table-data-cell>
                <c-table-data-cell>
                  <span v-html="renderNotificationMessage(notification)"></span>
                </c-table-data-cell>
                <c-table-data-cell>
                  {{ formatDate(notification.created_at) }}
                </c-table-data-cell>
                <c-table-data-cell>
                  <div class="d-flex gap-2">
                    <c-button
                      v-if="!notification.is_read && notification.type !== 'functional'"
                      color="link"
                      size="sm"
                      @click="markAsRead(notification)"
                      :disabled="loading"
                    >
                      {{ $t('notifications.mark_read') }}
                    </c-button>
                    <c-button
                      v-if="notification.type === 'functional' && !notification.is_confirmed"
                      color="success"
                      size="sm"
                      @click="confirmNotification(notification)"
                      :disabled="loading"
                    >
                      {{ notification.action_text || $t('notifications.confirm') }}
                    </c-button>
                    <c-badge
                      v-if="notification.type === 'functional' && notification.is_confirmed"
                      color="success"
                    >
                      {{ $t('notifications.confirmed') }}
                    </c-badge>
                  </div>
                </c-table-data-cell>
              </c-table-row>
            </c-table-body>
          </c-table>

          <!-- Pagination -->
          <c-pagination
            v-if="notifications.last_page > 1"
            :pages="notifications.last_page"
            :active-page="notifications.current_page"
            @update:active-page="changePage"
            class="mt-3"
          />

          <!-- Empty State -->
          <div v-if="!notifications.data || notifications.data.length === 0" class="text-center py-5">
            <c-icon name="cilBell" size="3xl" class="text-muted mb-3" />
            <h5 class="text-muted">{{ $t('notifications.no_notifications') }}</h5>
            <p class="text-muted">{{ $t('notifications.no_notifications_desc') }}</p>
          </div>
        </c-card-body>
      </c-card>
    </c-col>
  </c-row>
</template>

<script>
import { ref, onMounted, inject, computed } from 'vue';
import { useGameStore } from '../stores/gameStore';

export default {
  name: 'NotificationsPage',
  setup() {
    const $t = inject('$t');
    const gameStore = useGameStore();
    const loading = ref(false);
    const notifications = ref({
      data: [],
      current_page: 1,
      last_page: 1,
      total: 0
    });
    const unreadCount = computed(() => gameStore.unreadNotificationsCount);

    const fetchNotifications = async (page = 1) => {
      try {
        loading.value = true;
        const response = await fetch(`/api/notifications?page=${page}`);
        const data = await response.json();

        if (data.success) {
          notifications.value = data.notifications;
        }
      } catch (error) {
        console.error('Failed to fetch notifications:', error);
      } finally {
        loading.value = false;
      }
    };

    const fetchUnreadCount = async () => {
      try {
        const response = await fetch('/api/notifications/unread-count');
        const data = await response.json();

        if (data.success) {
          gameStore.unreadNotificationsCount = data.count;
        }
      } catch (error) {
        console.error('Failed to fetch unread count:', error);
      }
    };

    const markAsRead = async (notification) => {
      try {
        loading.value = true;
        const response = await fetch(`/api/notifications/${notification.id}/read`, {
          method: 'PATCH',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          }
        });

        if (response.ok) {
          notification.is_read = true;
          gameStore.unreadNotificationsCount = Math.max(0, gameStore.unreadNotificationsCount - 1);
        }
      } catch (error) {
        console.error('Failed to mark as read:', error);
      } finally {
        loading.value = false;
      }
    };

    const markAllAsRead = async () => {
      try {
        loading.value = true;
        const response = await fetch('/api/notifications/mark-all-read', {
          method: 'PATCH',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          }
        });

        if (response.ok) {
          await fetchNotifications(notifications.value.current_page);
          gameStore.unreadNotificationsCount = 0;
        }
      } catch (error) {
        console.error('Failed to mark all as read:', error);
      } finally {
        loading.value = false;
      }
    };

    const confirmNotification = async (notification) => {
      try {
        loading.value = true;
        const response = await fetch(`/api/notifications/${notification.id}/confirm`, {
          method: 'PATCH',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          }
        });

        if (response.ok) {
          notification.is_confirmed = true;
          await fetchUnreadCount();
        }
      } catch (error) {
        console.error('Failed to confirm notification:', error);
      } finally {
        loading.value = false;
      }
    };

    const changePage = (page) => {
      fetchNotifications(page);
    };

    const getTypeColor = (type) => {
      const colors = {
        info: 'info',
        success: 'success',
        warning: 'warning',
        danger: 'danger',
        functional: 'primary'
      };
      return colors[type] || 'secondary';
    };

    const getTypeLabel = (type) => {
      return $t('notifications.types.' + type);
    };

    const formatDate = (dateString) => {
      const date = new Date(dateString);
      return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const renderNotificationMessage = (notification) => {
      try {
        let data = notification.data || {};
        if (typeof data === 'string' && data.length > 0) {
          try { data = JSON.parse(data); } catch (e) { /* keep as-is */ }
        }
        // If objectType present, prepare translated objectType for interpolation
        if (data.objectType) {
          try { data.objectType = $t('city.' + String(data.objectType)); } catch (e) { /* ignore */ }
        }

        const msg = notification.message || '';
        if (typeof msg !== 'string') return '';

        // Only treat the stored message as a translation key when it explicitly starts with 'notifications.'
        if (msg.startsWith('notifications.')) {
          return $t(msg, data);
        }

        // Otherwise assume backend stored full resolved text and return it unchanged
        return msg;
      } catch (e) {
        console.error('Failed to render notification message', e);
        return notification.message || '';
      }
    };

    onMounted(() => {
      fetchNotifications();
      fetchUnreadCount();
    });

    return {
      loading,
      notifications,
      unreadCount,
      markAsRead,
      markAllAsRead,
      confirmNotification,
      changePage,
      getTypeColor,
      getTypeLabel,
      formatDate,
      renderNotificationMessage,
      $t
    };
  }
}
</script>
