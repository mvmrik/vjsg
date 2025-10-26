<template>
  <div class="lottery-page">
    <c-card>
      <c-card-header class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <strong>{{ $t('events.lottery') }}</strong>
              <span class="jackpot-badge ms-3">
                {{ $t('events.jackpot') }}
                <span class="fw-bold ms-2">{{ jackpotDisplay }}</span>
              </span>
            </div>
            <div>
              <c-button
                color="primary"
                :disabled="!canSubmit && !finished"
                @click="finished ? resetGame() : submitEntry()"
              >
                {{ finished ? ($t('events.new_game') || 'New game') : ($t('events.play') || 'Play') }}
              </c-button>
            </div>
          </c-card-header>
      <c-card-body>
        <div v-if="loading" class="text-center"><c-spinner /></div>

        <div v-else>
          <p v-if="!event">{{ $t('events.no_active') }}</p>

          <div v-if="event">
            <div class="lottery-layout">
              <div class="lottery-area">
                <div class="lottery-grid">
                  <button
                    v-for="n in gridNumbers"
                    :key="n"
                    :class="cellClass(n)"
                    @click="toggleNumber(n)"
                  >
                    {{ n }}
                  </button>
                </div>
              </div>

              <aside class="sidebar-area">
                <div class="selection-summary d-flex flex-column gap-3">
                  <div class="selected-chips">
                    <div class="small text-muted mb-1">{{ $t('events.selected') }}</div>
                    <div class="chips d-flex flex-wrap gap-2">
                      <span v-for="n in selected" :key="n" class="chip">{{ n }}</span>
                      <span v-if="selected.length===0" class="text-muted">—</span>
                    </div>
                  </div>

                  <div class="cost-block">
                    <div class="small text-muted mb-1">{{ $t('events.cost') }}</div>
                    <div class="cost-badge">{{ computedStake || 0 }}</div>
                  </div>

                  <div class="expected-payouts">
                    <div class="small text-muted mb-1">{{ $t('events.expected_payouts') }}</div>
                    <table class="table table-sm mb-0">
                      <tbody>
                        <tr v-for="k in [3,4,5,6]" :key="k">
                          <td class="align-middle">{{ k }} {{ $t('events.match_suffix') }}</td>
                          <td class="text-end align-middle fw-bold">{{ formatCurrency(expectedPayouts[k] || 0) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </aside>
            </div>

            <div class="mt-4">
              <h5>History</h5>
              <table class="table table-sm">
                <thead>
                  <tr><th>When</th><th>Numbers</th><th>Stake</th><th>Payout</th></tr>
                </thead>
                <tbody>
                  <tr v-for="h in paginatedHistory" :key="h.id">
                    <td>{{ new Date(h.created_at).toLocaleString() }}</td>
                    <td>{{ (h.numbers || []).join(', ') }}</td>
                    <td>{{ h.stake }}</td>
                    <td>{{ h.payout ?? '-' }}</td>
                  </tr>
                </tbody>
              </table>
              <nav class="mt-2 d-flex justify-content-between align-items-center">
                <div class="small text-muted">Показани: {{ (currentPage-1)*pageSize + 1 }} - {{ Math.min(currentPage*pageSize, history.length) }} от {{ history.length }}</div>
                <ul class="pagination mb-0">
                  <li class="page-item" :class="{disabled: currentPage===1}"><a class="page-link" href="#" @click.prevent="goToPage(currentPage-1)">&laquo;</a></li>
                  <li v-for="p in totalPages" :key="p" class="page-item" :class="{active: currentPage===p}"><a class="page-link" href="#" @click.prevent="goToPage(p)">{{ p }}</a></li>
                  <li class="page-item" :class="{disabled: currentPage===totalPages}"><a class="page-link" href="#" @click.prevent="goToPage(currentPage+1)">&raquo;</a></li>
                </ul>
              </nav>
            </div>

            <!-- Notifications are now shown as toasts via the global show-toast event -->

          </div>
        </div>
      </c-card-body>
    </c-card>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, inject, watch } from 'vue';
import axios from 'axios';
import { getJackpot, clearJackpotCache } from '../../utils/jackpotCache';
import { useGameStore } from '../../stores/gameStore';

const $t = inject('$t');
const loading = ref(true);
const event = ref(null);
const gridSize = 7; // 7x7 grid
const gridNumbers = Array.from({ length: gridSize * gridSize }, (_, i) => i + 1);
const selected = ref([]);
const message = ref('');
const messageType = ref('');
const jackpot = ref(0);
const observedJackpot = ref(0);
const gameStore = useGameStore();
const drawn = ref([]);
const animating = ref(false);
const highlighted = ref(null);
const history = ref([]);
const finished = ref(false);
// pagination for history
const currentPage = ref(1);
const pageSize = ref(10);
const totalPages = computed(() => Math.max(1, Math.ceil((history.value || []).length / pageSize.value)));
const paginatedHistory = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  return (history.value || []).slice(start, start + pageSize.value);
});

function goToPage(p) {
  if (p < 1) p = 1;
  if (p > totalPages.value) p = totalPages.value;
  currentPage.value = p;
}

watch(history, () => {
  // reset page if history changes and current page is out of range
  if (currentPage.value > totalPages.value) currentPage.value = totalPages.value;
});

const fetchCurrent = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/api/events/current');
    if (res.data.success) {
      event.value = res.data.event;
      if (event.value) await fetchJackpot();
    }
  } catch (e) {
    console.error('Failed to load event', e);
  } finally {
    loading.value = false;
  }
};

const fetchJackpot = async () => {
  try {
    const res = await getJackpot();
    if (res && res.success) jackpot.value = res.jackpot;
  } catch (e) {}
};

// fetch and update observedJackpot used for display and comparison
const fetchJackpotObserved = async () => {
  try {
    const res = await getJackpot();
    if (res && res.success) {
      jackpot.value = res.jackpot;
      observedJackpot.value = res.jackpot;
    }
  } catch (e) {}
};

onMounted(() => {
  fetchCurrent();
  fetchHistory();
  fetchJackpotObserved();
});

const fetchHistory = async () => {
  try {
    const res = await axios.get('/api/events/lottery/history');
    if (res.data.success) history.value = res.data.entries || [];
  } catch (e) {
    // ignore
  }
};

const toggleNumber = (n) => {
  if (animating.value) return; // prevent changing selection during animation
  const idx = selected.value.indexOf(n);
  if (idx === -1) {
    if (selected.value.length >= 9) return; // max
    selected.value.push(n);
  } else {
    selected.value.splice(idx, 1);
  }
};

const base = 1;
const computedStake = computed(() => {
  const l = selected.value.length;
  const mult = l === 6 ? 1 : l === 7 ? 10 : l === 8 ? 100 : l === 9 ? 1000 : 0;
  return l >= 6 ? base * mult : 0;
});

const canSubmit = computed(() => selected.value.length >= 6 && selected.value.length <= 9 && !animating.value && (gameStore.user?.balance ?? 0) >= computedStake.value);

const balanceShort = computed(() => gameStore.user?.balance ?? 0);

const submitEntry = async () => {
  if (!canSubmit.value) return;
  try {
    // Refresh jackpot before submitting to detect quick changes by others
      try {
      // Force fetch latest jackpot (bypass short cache) to detect rapid decreases
      const latestRes = await getJackpot(true).catch(() => null);
      if (latestRes && latestRes.success) {
        const latestVal = latestRes.jackpot ?? 0;
        // if jackpot decreased since user saw it, block the bet and notify
        if (latestVal < observedJackpot.value) {
          const warnMsg = translateWithParams('events.jackpot_decreased', { old: observedJackpot.value, new: latestVal });
          // show warning toast instead of inline alert
          window.dispatchEvent(new CustomEvent('show-toast', { detail: { message: warnMsg, type: 'warning' } }));
          // update observed value so user can retry with new jackpot
          observedJackpot.value = latestVal;
          jackpot.value = latestVal;
          return; // do not submit the bet
        }
      }
    } catch (e) {
      // ignore errors fetching latest jackpot and proceed to submit
    }

    const res = await axios.post('/api/events/lottery/enter', { numbers: selected.value, observed_jackpot: observedJackpot.value });
    if (res.data.success) {
      // notify immediately that entry was accepted
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { message: $t('events.entry_success') || 'Entry successful', type: 'success' } }));
      // server returned draw result directly with the entry
      const drawNumbers = res.data.draw || [];
      const myEntry = res.data.entry || null;
      const matches = res.data.matches ?? (myEntry ? (myEntry.numbers || []).filter(n => drawNumbers.includes(n)).length : 0);
      const payout = res.data.payout ?? (myEntry ? myEntry.payout : 0);

      try {
        animating.value = true;
        await animateDraw(drawNumbers);

        // show match/no-match message as a toast
        if (matches >= 3) {
          const m = translateWithParams('events.matched', { count: matches, payout: payout });
          window.dispatchEvent(new CustomEvent('show-toast', { detail: { message: m, type: 'success' } }));
        } else {
          const m = translateWithParams('events.no_match', { count: matches });
          window.dispatchEvent(new CustomEvent('show-toast', { detail: { message: m, type: 'info' } }));
        }

        // refresh jackpot and user balance
      // clear cache after submit so subsequent viewers get updated value from server
      clearJackpotCache();
      await fetchJackpot();
        await gameStore.fetchUserData();
        // refresh history
        await fetchHistory();
        // mark finished so UI can offer New Game
        finished.value = true;
        // update balance if backend returned it
        if (res.data.new_balance != null) {
          // fetchUserData already updated, but keep new_balance in case
        }
      } catch (e) {
        console.error('Draw animation error', e);
      } finally {
        animating.value = false;
      }
    } else {
      const errMsg = res.data.message || 'Error';
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { message: errMsg, type: 'error' } }));
    }
  } catch (e) {
    // Prefer server-provided message when available
    const resp = e?.response;
    if (resp && resp.data) {
      let err = '';
      if (resp.data.message) {
        err = resp.data.message;
      } else if (resp.data.errors) {
        const first = Object.values(resp.data.errors)[0];
        err = Array.isArray(first) ? first[0] : String(first);
      } else {
        err = JSON.stringify(resp.data);
      }
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { message: err, type: 'error' } }));
    } else {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { message: 'Server error', type: 'error' } }));
    }
  }
};

// Reset the UI for a new game
function resetGame() {
  drawn.value = [];
  highlighted.value = null;
  message.value = '';
  messageType.value = '';
  selected.value = [];
  finished.value = false;
}

// Simple translation helper that replaces :placeholders in translation strings
function translateWithParams(key, params = {}) {
  let str = $t(key);
  // if translator returned an object (unexpected), fallback to key
  if (typeof str !== 'string') str = String(str || key);
  Object.keys(params || {}).forEach(k => {
    str = str.replace(new RegExp(':' + k, 'g'), String(params[k]));
  });
  return str;
}

// Animate draw: for each final number, spin highlights then stop on the final
function wait(ms) { return new Promise(r => setTimeout(r, ms)); }
async function animateDraw(finalNumbers) {
  drawn.value = [];
  // simple sequential pick animation
  for (let i = 0; i < finalNumbers.length; i++) {
    const final = finalNumbers[i];
    // spin for a bit with decreasing speed
    let spins = 25 + i * 10;
    let delay = 30;
    for (let s = 0; s < spins; s++) {
      const pick = Math.floor(Math.random() * 49) + 1;
      highlighted.value = pick;
      await wait(delay);
      delay = Math.min(200, delay + 5);
    }
    // settle on final
    highlighted.value = final;
    drawn.value.push(final);
    await wait(300);
  }
}

const jackpotDisplay = computed(() => jackpot.value);
// Expected payouts based on current communal jackpot and server rules
// Server payout percentages: 6 => 100%, 5 => 20%, 4 => 4%, 3 => 1% (min 1)
const tierPercents = { 6: 1.0, 5: 0.20, 4: 0.04, 3: 0.01 };
const expectedPayouts = computed(() => {
  const out = {};
  const currentJackpot = jackpot.value || 0;
  Object.keys(tierPercents).forEach((k) => {
    const ik = Number(k);
    let p = Math.floor((tierPercents[ik] || 0) * currentJackpot);
    if (ik === 3 && p < 1) p = 1;
    out[ik] = p;
  });
  return out;
});

function formatCurrency(v) {
  if (v === null || v === undefined) return '-';
  if (v === 0) return '0';
  return v.toLocaleString(undefined, { maximumFractionDigits: 0 });
}
// helper to show cell classes including drawn/highlight
function cellClass(n) {
  return {
    'lottery-cell': true,
    selected: selected.value.includes(n),
    drawn: drawn.value.includes(n),
    highlighted: highlighted.value === n,
    'selected-drawn': selected.value.includes(n) && drawn.value.includes(n),
  };
}
</script>

<style scoped>
.lottery-grid {
  display: grid;
  /* allow cells to shrink on small screens so the whole grid fits the viewport */
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 8px;
  width: 100%;
  max-width: 100%;
}
.lottery-cell {
  border: 1px solid #e9ecef;
  background: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1 / 1;
  min-width: 0; /* allow shrinking inside grid columns on small screens */
  border-radius: 50%;
  padding: 0.35rem;
  font-weight: 700;
  color: #212529;
}
.lottery-cell.selected {
  /* show as chip-like circle, slightly darker so selected numbers stand out more */
  background: #e9ecef;
  border-color: #d0d7db;
  color: #0f1720;
  box-shadow: 0 1px 0 rgba(15,23,32,0.04) inset;
  transition: background .12s ease, transform .06s ease;
}
.lottery-cell.drawn { background: #dc3545; color: white; }
.lottery-cell.highlighted { box-shadow: 0 0 8px 3px rgba(255,200,0,0.8); }
.lottery-cell.selected-drawn { background: #28a745; color: white; border-color: #28a745; }

.jackpot-badge {
  display: inline-flex;
  align-items: center;
  background: linear-gradient(90deg, #fff3cd, #ffe8a1);
  color: #856404;
  padding: 0.25rem 0.6rem;
  border-radius: 0.75rem;
  box-shadow: 0 6px 18px rgba(255,193,7,0.12), 0 0 0 4px rgba(255,193,7,0.03);
  font-weight: 600;
}

.jackpot-badge .fw-bold { margin-left: 0.25rem; }

.selection-summary .chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 36px;
  padding: 0 8px;
  border-radius: 18px;
  background: #f1f3f5;
  color: #212529;
  font-weight: 600;
  border: 1px solid #e9ecef;
}
.selection-summary .chips { gap: 6px; }
.selection-summary .cost-badge {
  display: inline-block;
  background: linear-gradient(90deg,#e7f5ff,#cfe9ff);
  color: #0b5ed7;
  padding: 6px 10px;
  border-radius: 0.5rem;
  font-weight: 700;
  border: 1px solid rgba(11,94,215,0.12);
}
.selection-summary .expected-payouts table td { border-top: none; padding-top: 2px; padding-bottom: 2px; }

@media (max-width: 575.98px) {
  .selection-summary { gap: 0.5rem; }
  .selection-summary .chip { min-width: 32px; height: 32px; border-radius: 16px; }
  .selection-summary .expected-payouts { width: 100%; }
}

/* Layout: place the selection summary to the right of the grid on larger screens */
.lottery-layout {
  display: flex;
  flex-direction: column;
  gap: 0.5rem; /* tighter spacing */
}

.lottery-area { flex: 1 1 auto; }
.sidebar-area { width: 100%; }

.lottery-area { display: flex; justify-content: flex-start; }
.sidebar-area { align-self: flex-start; }

@media (min-width: 768px) {
  .lottery-layout {
    flex-direction: row;
    align-items: flex-start;
    align-content: flex-start;
  }
  /* Fix the grid width and let the sidebar fill remaining space on larger screens */
  .lottery-area { flex: 0 0 420px; margin-right: 0.5rem; }
  .sidebar-area { width: auto; flex: 1 1 0; }
  /* keep the grid fixed at 420px on desktop so cells stay a comfortable size */
  .lottery-grid { width: 420px; max-width: 420px; }
}

@media (max-width: 400px) {
  /* slightly reduce gaps and padding on very small screens */
  .lottery-grid { gap: 6px; }
  .lottery-cell { padding: 0.25rem; }
}
</style>
