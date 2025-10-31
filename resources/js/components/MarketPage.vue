<template>
  <div class="market-dark">
    <div class="header-bar">
      <select v-model="selectedToolType" @change="loadMarket" class="selector">
        <option v-for="t in toolTypes" :key="t.id" :value="t.id">{{ translateName(t.name) }}</option>
      </select>
      <span class="selector-help" :title="$t('market.selector_help')">ℹ︎</span>
      <div class="stats" v-if="stats">
        <div class="stat">
          <small>{{ $t('market.last_price') }}</small>
          <strong>{{ stats.lastPrice || '-' }}</strong>
        </div>
        <div class="stat" :class="stats.change24h >= 0 ? 'pos' : 'neg'">
          <small>{{ $t('market.change_24h') }}</small>
          <strong>{{ stats.change24h ? (stats.change24h > 0 ? '+' : '') + stats.change24h.toFixed(2) + '%' : '-' }}</strong>
        </div>
        <div class="stat">
          <small>{{ $t('market.volume_24h') }}</small>
          <strong>{{ stats.volume24h || '-' }}</strong>
        </div>
      </div>
    </div>

    <div class="grid">
      <div class="panel chart-panel">
        <div class="panel-head">
          <span>{{ $t('market.price_chart') }}</span>
          <div class="timeframe-btns">
            <button v-for="tf in timeframes" :key="tf" @click="changeTimeframe(tf)" :class="{ active: timeframe === tf }" class="tf-btn">
              {{ $t(`market.${tf}`) }}
            </button>
          </div>
        </div>
        <div class="chart-box">
          <canvas ref="chartCanvas"></canvas>
        </div>
      </div>

      <div class="panel book-panel">
        <div class="panel-head">{{ $t('market.orderbook') }}</div>
        <div class="book-grid">
          <span>{{ $t('market.price') }}</span>
          <span>{{ $t('market.amount') }}</span>
          <span>{{ $t('market.total') }}</span>
        </div>
        <div class="asks">
          <div v-for="(a, i) in topAsks" :key="'a'+i" class="row ask">
            <span>{{ a.price }}</span>
            <span>{{ a.volume }}</span>
            <span>{{ (a.price * a.volume).toFixed(0) }}</span>
          </div>
        </div>
        <div class="spread" v-if="spread">{{ $t('market.spread') }}: {{ spread }}</div>
        <div class="bids">
          <div v-for="(b, i) in topBids" :key="'b'+i" class="row bid">
            <span>{{ b.price }}</span>
            <span>{{ b.volume }}</span>
            <span>{{ (b.price * b.volume).toFixed(0) }}</span>
          </div>
        </div>
      </div>

      <div class="panel form-panel">
        <div class="panel-head">{{ $t('market.place_order') }}</div>
        <div class="form-box">
          <div class="tabs">
            <button @click="changeSide('buy')" :class="{ on: form.side === 'buy' }" class="tab buy-tab">{{ $t('market.buy') }}</button>
            <button @click="changeSide('sell')" :class="{ on: form.side === 'sell' }" class="tab sell-tab">{{ $t('market.sell') }}</button>
          </div>
          <div class="balance-info">
            <div class="balance-row">
              <span class="label">{{ $t('market.available') }}:</span>
              <span class="value">{{ availableFunds.toFixed(form.side === 'buy' ? 0 : 0) }} {{ form.side === 'buy' ? '$' : 'items' }}</span>
            </div>
          </div>
          <form @submit.prevent="submitOrder">
            <div class="field">
              <label>
                {{ $t('market.price') }}
                <span v-if="form.side === 'buy' && bestAsk" class="price-hint">(max: {{ bestAsk }})</span>
                <span v-if="form.side === 'sell' && bestBid" class="price-hint">(min: {{ bestBid }})</span>
              </label>
              <input 
                v-model.number="form.price" 
                type="number" 
                min="1" 
                :max="form.side === 'buy' && bestAsk ? bestAsk : undefined"
                :min="form.side === 'sell' && bestBid ? bestBid : 1"
              />
            </div>
            <div class="field">
              <label>{{ $t('market.quantity') }}</label>
              <input v-model.number="form.quantity" type="number" min="1" />
            </div>
            <div class="field">
              <label>{{ $t('market.total') }}</label>
              <input :value="(form.price * form.quantity).toFixed(0)" readonly />
            </div>
            <div class="balance-row after-order">
              <span class="label">{{ $t('market.after_order') }}:</span>
              <span class="value" :class="{ 'insufficient': !canAfford }">
                {{ afterOrder.toFixed(0) }} {{ form.side === 'buy' ? '$' : 'items' }}
              </span>
            </div>
            <button type="submit" class="submit" :class="form.side === 'buy' ? 'buy-btn' : 'sell-btn'" :disabled="!canAfford">
              {{ form.side === 'buy' ? $t('market.buy') : $t('market.sell') }}
            </button>
          </form>
        </div>
      </div>

      <div class="panel trades-panel">
        <div class="panel-head">{{ $t('market.recent_trades') }}</div>
        <div class="trades-grid">
          <span>{{ $t('market.time') }}</span>
          <span>{{ $t('market.price') }}</span>
          <span>{{ $t('market.amount') }}</span>
        </div>
        <div class="trades-list">
          <div v-for="t in showTrades" :key="t.id" class="row">
            <span>{{ formatTime(t.executed_at) }}</span>
            <span>{{ t.price }}</span>
            <span>{{ t.quantity }}</span>
          </div>
        </div>
        <button v-if="canLoadMore" @click="loadMore" class="load-btn">{{ $t('market.load_more') }}</button>
      </div>

      <div class="panel orders-panel">
        <div class="panel-head">
          <span>{{ $t('market.my_orders') }}</span>
          <div style="display:flex;gap:0.5rem;align-items:center;">
            <button @click="orderFilterMode='all'" :class="{ active: orderFilterMode === 'all' }" class="filter-btn">{{ $t('market.show_all') }}</button>
            <button @click="orderFilterMode='current'" :class="{ active: orderFilterMode === 'current' }" class="filter-btn">{{ $t('market.current_product') }}</button>
            <label style="display:flex;align-items:center;gap:0.25rem;margin-left:0.5rem;color:#848e9c;font-size:0.85rem;">
              <input type="checkbox" v-model="hideCancelled" />
              <span style="font-size:0.85rem;">{{ $t('market.hide_cancelled') }}</span>
            </label>
          </div>
        </div>
        <div class="orders-grid">
          <span>{{ $t('market.time') }}</span>
          <span>{{ $t('market.side') }}</span>
          <span>{{ $t('market.price') }}</span>
          <span>{{ $t('market.amount') }}</span>
          <span>{{ $t('market.status') }}</span>
          <span class="text-end">{{ $t('market.actions') }}</span>
        </div>
        <div class="orders-list">
          <div v-if="!displayedOrders.length" class="no-data">{{ $t('market.no_orders') }}</div>
          <div v-for="o in displayedOrders" :key="o.id" class="row order-row">
            <span>{{ formatTime(o.created_at) }}</span>
            <span :class="o.side === 'buy' ? 'buy-text' : 'sell-text'">{{ $t(`market.${o.side}`) }}</span>
            <span>{{ o.price }}</span>
            <span>{{ o.filled_quantity }} / {{ o.quantity }}</span>
            <span :class="'status-' + o.status">{{ $t(`market.${o.status}`) }}</span>
            <span class="order-actions">
              <button v-if="(o.status === 'open' || o.status === 'partial')" @click="cancelOrder(o.id)" class="icon-btn" :title="$t('market.cancel')">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>
              </button>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, inject, watch } from 'vue';
import axios from 'axios';
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

const $t = inject('$t');
const toolTypes = ref([]);
const selectedToolType = ref(null);
const bids = ref([]);
const asks = ref([]);
const trades = ref([]);
const myOrders = ref([]);
const stats = ref(null);
const userBalance = ref(0);
const userInventory = ref(0);
const form = ref({ side: 'buy', price: 1, quantity: 1 });
const chartCanvas = ref(null);
let chartInstance = null;
const page = ref(1);
const perPage = 5;
const timeframe = ref('1d');
const timeframes = ['1d', '1w', '1m', '3m', '1y', 'all'];

const topBids = computed(() => bids.value.slice(0, 10));
const topAsks = computed(() => asks.value.slice(0, 10));
const showTrades = computed(() => trades.value.slice(0, perPage * page.value));
const canLoadMore = computed(() => trades.value.length > perPage * page.value);
const bestBid = computed(() => bids.value.length ? Number(bids.value[0].price) : 0);
const bestAsk = computed(() => asks.value.length ? Number(asks.value[0].price) : 0);

const availableFunds = computed(() => {
  if (form.value.side === 'buy') {
    return userBalance.value;
  } else {
    return userInventory.value;
  }
});

const afterOrder = computed(() => {
  if (form.value.side === 'buy') {
    const cost = form.value.price * form.value.quantity;
    return Math.max(0, userBalance.value - cost);
  } else {
    return Math.max(0, userInventory.value - form.value.quantity);
  }
});

const canAfford = computed(() => {
  if (form.value.side === 'buy') {
    const cost = form.value.price * form.value.quantity;
    return userBalance.value >= cost;
  } else {
    return userInventory.value >= form.value.quantity;
  }
});

// Order filters
const orderFilterMode = ref('all'); // 'all' | 'current'
// hideCancelled: when true, cancelled orders are hidden. Default: false (do not hide)
const hideCancelled = ref(false);

// Persist filter prefs
onMounted(() => {
  try {
    const savedMode = localStorage.getItem('market.orderFilterMode');
    // Support previous key 'market.showCancelled' (legacy) by migrating it to new hideCancelled semantics.
    const savedHideCancelled = localStorage.getItem('market.hideCancelled');
    const legacyShowCancelled = localStorage.getItem('market.showCancelled');
    if (savedMode) orderFilterMode.value = savedMode;
    if (savedHideCancelled !== null) {
      hideCancelled.value = savedHideCancelled === 'true';
    } else if (legacyShowCancelled !== null) {
      // legacy: showCancelled (true = show cancelled). Convert to hideCancelled = !showCancelled
      const legacy = legacyShowCancelled === 'true';
      hideCancelled.value = !legacy;
      try { localStorage.setItem('market.hideCancelled', String(hideCancelled.value)); } catch (e) {}
    }
  } catch (e) { /* ignore localStorage errors */ }
});
watch(orderFilterMode, (v) => { try { localStorage.setItem('market.orderFilterMode', v); } catch (e) {} });
watch(hideCancelled, (v) => { try { localStorage.setItem('market.hideCancelled', String(v)); } catch (e) {} });

// Helpers for global UI events (toaster / confirm)
function showToast(message, type = 'info') {
  try { window.dispatchEvent(new CustomEvent('show-toast', { detail: { message, type } })); } catch (e) { console.log('toast', message); }
}
function showConfirm(message, onConfirm, onCancel) {
  try { window.dispatchEvent(new CustomEvent('show-confirm-dialog', { detail: { message, onConfirm, onCancel } })); } catch (e) { if (confirm(message)) onConfirm(); else onCancel && onCancel(); }
}

const displayedOrders = computed(() => {
  let list = Array.isArray(myOrders.value) ? myOrders.value.slice() : [];
  if (orderFilterMode.value === 'current' && selectedToolType.value) {
    list = list.filter(o => Number(o.tool_type_id) === Number(selectedToolType.value));
  }
  if (hideCancelled.value) {
    list = list.filter(o => o.status !== 'cancelled');
  }
  return list;
});

const spread = computed(() => {
  if (asks.value.length && bids.value.length) {
    return (asks.value[0].price - bids.value[0].price).toFixed(2);
  }
  return null;
});

function loadMore() { page.value++; }

function changeSide(side) {
  form.value.side = side;
  // Auto-fill price when changing side
  if (side === 'buy' && bestAsk.value > 0) {
    form.value.price = bestAsk.value;
  } else if (side === 'sell' && bestBid.value > 0) {
    form.value.price = bestBid.value;
  }
}

async function loadToolTypes() {
  try {
    const res = await axios.get('/api/tool-types');
    if (res.data?.success) {
      toolTypes.value = res.data.tool_types;
      if (toolTypes.value.length) {
        selectedToolType.value = toolTypes.value[0].id;
        loadMarket();
      }
    }
  } catch (e) { console.error(e); }
}

async function loadMarket() {
  if (!selectedToolType.value) return;
  page.value = 1;
  try {
    const [ob, tr, mo, user, inv] = await Promise.all([
      axios.get(`/api/market/${selectedToolType.value}/orderbook`),
      axios.get(`/api/market/${selectedToolType.value}/trades`),
      axios.get('/api/market/orders'),
      axios.get('/api/user/balance'),
      axios.get(`/api/user/inventory/${selectedToolType.value}`)
    ]);
    if (ob.data.success) {
      bids.value = ob.data.bids;
      asks.value = ob.data.asks;
      
      // Auto-fill price based on side
      if (form.value.side === 'buy' && asks.value.length) {
        form.value.price = Number(asks.value[0].price);
      } else if (form.value.side === 'sell' && bids.value.length) {
        form.value.price = Number(bids.value[0].price);
      }
    }
    if (tr.data.success) {
      trades.value = tr.data.trades;
      calcStats();
      buildChart();
    }
    if (mo.data?.success) {
      // Show all user orders (including cancelled) so users can see history for
      // products that may be excluded from the market selector. If desired,
      // frontend can provide filtering by product separately.
      myOrders.value = mo.data.orders; // keep server-provided order list intact
    }
    if (user.data?.balance !== undefined) {
      userBalance.value = Number(user.data.balance);
    }
    if (inv.data?.count !== undefined) {
      userInventory.value = Number(inv.data.count);
    }
  } catch (e) { console.error(e); }
}

function calcStats() {
  if (!trades.value.length) { stats.value = null; return; }
  const now = new Date();
  const yesterday = new Date(now.getTime() - 24 * 3600 * 1000);
  const t24 = trades.value.filter(t => new Date(t.executed_at) >= yesterday);
  if (!t24.length) { stats.value = { lastPrice: trades.value[0].price }; return; }
  const lastPrice = trades.value[0].price;
  const prices = t24.map(t => Number(t.price));
  const volumes = t24.map(t => Number(t.quantity));
  const high24h = Math.max(...prices);
  const low24h = Math.min(...prices);
  const volume24h = volumes.reduce((a, b) => a + b, 0);
  const firstPrice24h = prices[prices.length - 1];
  const change24h = ((lastPrice - firstPrice24h) / firstPrice24h) * 100;
  stats.value = { lastPrice, change24h, high24h, low24h, volume24h };
}

function buildChart() {
  if (!chartCanvas.value) return;
  const ctx = chartCanvas.value.getContext('2d');
  if (chartInstance) chartInstance.destroy();
  
  // Filter trades by timeframe
  const now = new Date();
  let cutoffDate;
  switch(timeframe.value) {
    case '1d': cutoffDate = new Date(now.getTime() - 24 * 3600 * 1000); break;
    case '1w': cutoffDate = new Date(now.getTime() - 7 * 24 * 3600 * 1000); break;
    case '1m': cutoffDate = new Date(now.getTime() - 30 * 24 * 3600 * 1000); break;
    case '3m': cutoffDate = new Date(now.getTime() - 90 * 24 * 3600 * 1000); break;
    case '1y': cutoffDate = new Date(now.getTime() - 365 * 24 * 3600 * 1000); break;
    default: cutoffDate = new Date(0); // all
  }
  
  const filtered = trades.value.filter(t => new Date(t.executed_at) >= cutoffDate);
  const data = filtered.slice().reverse();
  if (!data.length) return;
  
  const labels = data.map(t => new Date(t.executed_at).toLocaleTimeString());
  const prices = data.map(t => Number(t.price));
  chartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        data: prices,
        borderColor: '#26a69a',
        backgroundColor: 'rgba(38,166,154,0.1)',
        borderWidth: 2,
        tension: 0.1,
        pointRadius: 0,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#888' } },
        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#888' } }
      }
    }
  });
}

function changeTimeframe(tf) {
  timeframe.value = tf;
  buildChart();
}

async function submitOrder() {
  if (!selectedToolType.value) return;
  
  // Validation
  const price = Math.max(1, Math.floor(form.value.price));
  const quantity = Math.max(1, Math.floor(form.value.quantity));
  
  // Check if can afford
  if (!canAfford.value) {
    if (form.value.side === 'buy') {
      showToast(`${$t('market.insufficient_funds') || 'Insufficient funds. You need'} ${price * quantity}$`, 'error');
    } else {
      showToast(`${$t('market.insufficient_items') || 'Insufficient items. You need'} ${quantity}`, 'error');
    }
    return;
  }
  
  // Check price limits
  if (form.value.side === 'buy' && bestAsk.value > 0 && price > bestAsk.value) {
    showToast($t('market.buy_price_exceeds') || `Buy price cannot exceed best ask price (${bestAsk.value})`, 'error');
    return;
  }
  if (form.value.side === 'sell' && bestBid.value > 0 && price < bestBid.value) {
    showToast($t('market.sell_price_below') || `Sell price cannot be lower than best bid price (${bestBid.value})`, 'error');
    return;
  }
  
  try {
    const payload = {
      tool_type_id: selectedToolType.value,
      side: form.value.side,
      price,
      quantity
    };
    const res = await axios.post('/api/market/orders', payload);
    if (res.data?.success) {
      showToast($t('market.order_placed') || 'Order placed successfully!', 'success');
      loadMarket();
    } else {
      showToast(res.data.message || ($t('market.order_failed') || 'Order failed'), 'error');
    }
  } catch (e) {
    console.error(e);
    const errorMsg = e.response?.data?.message || ($t('market.order_failed') || 'Order failed');
    showToast(errorMsg, 'error');
  }
}

async function cancelOrder(orderId) {
  // Use global confirm dialog (GameApp.vue listens for this)
  showConfirm($t('market.confirm_cancel') || 'Are you sure you want to cancel this order?', async () => {
    try {
      const res = await axios.post(`/api/market/orders/${orderId}/cancel`);
      if (res.data?.success) {
        showToast($t('market.order_cancelled') || 'Order cancelled', 'success');
        loadMarket();
      } else {
        showToast(res.data.message || ($t('market.cancel_failed') || 'Cancel failed'), 'error');
      }
    } catch (e) {
      console.error(e);
      const msg = e.response?.data?.message || ($t('market.cancel_failed') || 'Cancel failed');
      showToast(msg, 'error');
    }
  }, () => {
    /* cancelled */
  });
}

function formatTime(datetime) {
  return new Date(datetime).toLocaleTimeString();
}

function translateName(name) {
  try {
    // Try to translate from tools.types section
    const translated = $t ? $t(`tools.types.${name}`) : name;
    // If translation returns the key itself (not found), return original name
    return translated.startsWith('tools.types.') ? name : translated;
  } catch (e) { 
    return name; 
  }
}

onMounted(() => loadToolTypes());
onUnmounted(() => { if (chartInstance) chartInstance.destroy(); });
</script>

<style scoped>
.market-dark { background: #0b0e11; color: #d1d4dc; min-height: 100vh; padding: 1rem; }
.header-bar { background: #161a1e; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
.selector { background: #1e2329; border: 1px solid #2b3139; color: #d1d4dc; padding: 0.5rem 1rem; border-radius: 4px; min-width: 200px; }
.stats { display: flex; gap: 2rem; flex: 1; flex-wrap: wrap; }
.stat { display: flex; flex-direction: column; gap: 0.25rem; }
.stat small { font-size: 0.75rem; color: #848e9c; text-transform: uppercase; }
.stat strong { font-size: 1.125rem; font-weight: 600; }
.stat.pos strong { color: #0ecb81; }
.stat.neg strong { color: #f6465d; }
.grid { display: grid; grid-template-columns: 1fr 320px; grid-template-rows: auto auto; gap: 1rem; }
.panel { background: #161a1e; border-radius: 8px; overflow: hidden; }
.panel-head { background: #1e2329; padding: 0.75rem 1rem; font-weight: 600; border-bottom: 1px solid #2b3139; display: flex; justify-content: space-between; align-items: center; }
.timeframe-btns { display: flex; gap: 0.25rem; }
.tf-btn { padding: 0.25rem 0.5rem; background: #0b0e11; border: 1px solid #2b3139; border-radius: 3px; color: #848e9c; font-size: 0.75rem; cursor: pointer; transition: all 0.2s; }
.tf-btn:hover { background: #1e2329; border-color: #26a69a; }
.tf-btn.active { background: #26a69a; border-color: #26a69a; color: #fff; }
/* order filter buttons styling */
.filter-btn { padding: 0.25rem 0.5rem; background: #0b0e11; border: 1px solid #2b3139; border-radius: 4px; color: #848e9c; font-size: 0.8rem; cursor: pointer; }
.filter-btn.active { background: #26a69a; border-color: #26a69a; color: #fff; }

/* small icon button for order actions */
.icon-btn { background: transparent; border: 1px solid transparent; color: #f6465d; padding: 0.25rem; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.icon-btn svg { display: block; }
.icon-btn:hover { background: rgba(246,70,93,0.08); border-color: rgba(246,70,93,0.15); }
.order-actions { display: flex; justify-content: flex-end; }
.chart-panel { grid-column: 1; grid-row: 1; }
.book-panel { grid-column: 2; grid-row: 1 / 3; }
.form-panel { grid-column: 1; grid-row: 2; }
.trades-panel { grid-column: 1; grid-row: 3; }
.orders-panel { grid-column: 1; grid-row: 4; }
.chart-box { padding: 1rem; height: 400px; }
.book-grid, .trades-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; padding: 0.5rem 1rem; font-size: 0.75rem; color: #848e9c; text-transform: uppercase; border-bottom: 1px solid #2b3139; }
.row { display: grid; grid-template-columns: 1fr 1fr 1fr; padding: 0.25rem 1rem; font-family: monospace; font-size: 0.875rem; }
.row:hover { background: #1e2329; }
.ask span:first-child { color: #f6465d; }
.bid span:first-child { color: #0ecb81; }
.spread { padding: 0.5rem 1rem; background: #1e2329; border-top: 1px solid #2b3139; border-bottom: 1px solid #2b3139; font-size: 0.875rem; color: #848e9c; }
.asks, .bids { max-height: 200px; overflow-y: auto; overflow-x: hidden; }
.form-box { padding: 1rem; }
.tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 1rem; }
.tab { padding: 0.75rem; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; background: #1e2329; color: #848e9c; }
.buy-tab.on { background: #0ecb81; color: #fff; }
.sell-tab.on { background: #f6465d; color: #fff; }
.balance-info { background: #1e2329; border-radius: 4px; padding: 0.75rem; margin-bottom: 1rem; }
.balance-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; }
.balance-row.after-order { margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #2b3139; }
.balance-row .label { color: #848e9c; }
.balance-row .value { color: #d1d4dc; font-weight: 600; }
.balance-row .value.insufficient { color: #f6465d; }
.field { margin-bottom: 1rem; }
.field label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: #848e9c; }
.price-hint { color: #26a69a; font-weight: 600; margin-left: 0.5rem; }
.field input { width: 100%; padding: 0.75rem; background: #1e2329; border: 1px solid #2b3139; border-radius: 4px; color: #d1d4dc; }
.submit { width: 100%; padding: 1rem; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; }
.submit:disabled { opacity: 0.5; cursor: not-allowed; }
.buy-btn { background: #0ecb81; color: #fff; }
.sell-btn { background: #f6465d; color: #fff; }
.trades-list { max-height: 300px; overflow-y: auto; overflow-x: hidden; }
.load-btn { width: calc(100% - 2rem); margin: 1rem; padding: 0.75rem; background: #1e2329; border: 1px solid #2b3139; border-radius: 4px; color: #d1d4dc; font-weight: 600; cursor: pointer; }
.orders-grid { display: grid; grid-template-columns: 1fr 0.7fr 0.8fr 1fr 0.8fr 0.6fr; padding: 0.5rem 1rem; font-size: 0.75rem; color: #848e9c; text-transform: uppercase; border-bottom: 1px solid #2b3139; }
.orders-list { max-height: 300px; overflow-y: auto; overflow-x: hidden; }
.order-row { grid-template-columns: 1fr 0.7fr 0.8fr 1fr 0.8fr 0.6fr; }
.order-actions { display:flex; justify-content:center; }
.buy-text { color: #0ecb81; font-weight: 600; }
.sell-text { color: #f6465d; font-weight: 600; }
.status-open { color: #26a69a; }
.status-partial { color: #f0b90b; }
.status-filled { color: #848e9c; }
.status-cancelled { color: #f6465d; }
.no-data { padding: 2rem 1rem; text-align: center; color: #848e9c; }
@media (max-width: 768px) {
  .grid { grid-template-columns: 1fr; }
  .chart-panel { grid-row: 1; }
  .book-panel { grid-column: 1; grid-row: 2; }
  .form-panel { grid-row: 3; }
  .trades-panel { grid-row: 4; }
  .orders-panel { grid-row: 5; }
  .chart-box { height: 250px; }
}
</style>
