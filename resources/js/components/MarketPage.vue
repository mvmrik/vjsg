<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3>{{ $t('menu.market') }}</h3>
      <div>
        <label class="me-2 small">{{ $t('market.select_product') }}</label>
        <select v-model="selectedToolType" @change="loadMarket" class="form-select d-inline-block" style="width:220px">
          <option v-for="t in toolTypes" :key="t.id" :value="t.id">{{ translateName(t.name) }}</option>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title">{{ $t('market.price_chart') }}</h5>
            <div class="w-100" style="height:240px;">
              <svg v-if="chartPoints.length" :viewBox="chartViewBox" preserveAspectRatio="none" class="w-100 h-100">
                <polyline :points="chartPoints.join(' ')" fill="none" stroke="#0d6efd" stroke-width="2" />
              </svg>
              <div v-else class="text-muted">{{ $t('market.no_trades') }}</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <h5 class="card-title">{{ $t('market.orderbook') }}</h5>
            <div class="row">
              <div class="col-6">
                <h6 class="text-success">{{ $t('market.bids') }}</h6>
                <ul class="list-unstyled small">
                  <li v-for="b in bids" :key="b.price">{{ b.volume }} @ {{ b.price }}</li>
                </ul>
              </div>
              <div class="col-6">
                <h6 class="text-danger">{{ $t('market.asks') }}</h6>
                <ul class="list-unstyled small">
                  <li v-for="a in asks" :key="a.price">{{ a.volume }} @ {{ a.price }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title">{{ $t('market.place_order') }}</h5>
            <form @submit.prevent="submitOrder">
              <div class="mb-2">
                <label class="form-label">{{ $t('market.side') }}</label>
                <select v-model="form.side" class="form-select">
                  <option value="buy">{{ $t('market.buy') }}</option>
                  <option value="sell">{{ $t('market.sell') }}</option>
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label">{{ $t('market.price') }}</label>
                <input v-model.number="form.price" type="number" class="form-control" min="1" />
              </div>
              <div class="mb-2">
                <label class="form-label">{{ $t('market.quantity') }}</label>
                <input v-model.number="form.quantity" type="number" class="form-control" min="1" />
              </div>
              <button class="btn btn-primary">{{ $t('market.place_order') }}</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <h5 class="card-title">{{ $t('market.recent_trades') }}</h5>
            <ul class="list-unstyled small">
              <li v-for="t in trades" :key="t.id">{{ t.quantity }} @ {{ t.price }} — {{ new Date(t.executed_at).toLocaleString() }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
const router = useRouter();
const $t = inject('$t');

const toolTypes = ref([]);
const selectedToolType = ref(null);
const bids = ref([]);
const asks = ref([]);
const trades = ref([]);
const chartPoints = ref([]);

const form = ref({ side: 'buy', price: 1, quantity: 1 });

const chartViewBox = computed(() => {
  // viewBox: minX minY width height
  return `0 0 100 100`;
});

async function loadToolTypes() {
  try {
    const res = await axios.get('/api/tool-types');
    if (res.data && res.data.success) {
      toolTypes.value = res.data.tool_types;
      if (toolTypes.value.length) {
        selectedToolType.value = toolTypes.value[0].id;
        loadMarket();
      }
    }
  } catch (e) {
    console.error(e);
  }
}

async function loadMarket() {
  if (!selectedToolType.value) return;
  try {
    const ob = await axios.get(`/api/market/${selectedToolType.value}/orderbook`);
    if (ob.data.success) {
      bids.value = ob.data.bids;
      asks.value = ob.data.asks;
    }
    const tr = await axios.get(`/api/market/${selectedToolType.value}/trades`);
    if (tr.data.success) {
      trades.value = tr.data.trades;
      buildChart();
    }
  } catch (e) {
    console.error(e);
  }
}

function buildChart() {
  // Simple sparkline: normalize last 20 trades to 0-100 range
  const pts = trades.value.slice().reverse().slice(0, 20).map(t => Number(t.price));
  if (!pts.length) { chartPoints.value = []; return; }
  const max = Math.max(...pts);
  const min = Math.min(...pts);
  const wStep = 100 / Math.max(1, pts.length - 1);
  chartPoints.value = pts.map((p, i) => {
    const x = (i) * wStep;
    const y = 100 - ((p - min) / Math.max(1, (max - min))) * 100;
    return `${x},${isNaN(y) ? 50 : y}`;
  });
}

async function submitOrder() {
  if (!selectedToolType.value) return;
  try {
    const payload = {
      tool_type_id: selectedToolType.value,
      side: form.value.side,
      price: Math.max(1, Math.floor(form.value.price)),
      quantity: Math.max(1, Math.floor(form.value.quantity))
    };
    const res = await axios.post('/api/market/orders', payload);
    if (res.data && res.data.success) {
      alert('Order placed');
      loadMarket();
    } else {
      alert(res.data.message || 'Order failed');
    }
  } catch (e) {
    console.error(e);
    alert('Order failed');
  }
}

onMounted(() => {
  loadToolTypes();
});

function translateName(name) {
  try {
    const translated = $t ? $t(name) : name;
    return translated === name ? name : translated;
  } catch (e) {
    return name;
  }
}
</script>

<style scoped>
.card { min-height: 150px }
</style>
