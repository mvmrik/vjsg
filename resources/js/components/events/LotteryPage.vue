<template>
  <div class="lottery-page">
    <c-card>
      <c-card-header>
        <strong>{{ $t('events.lottery') }}</strong>
      </c-card-header>
      <c-card-body>
        <div v-if="loading" class="text-center"><c-spinner /></div>

        <div v-else>
          <p v-if="!event">{{ $t('events.no_active') }}</p>

          <div v-if="event">
            <p>{{ $t('events.jackpot') }}: {{ jackpotDisplay }}</p>
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

            <div class="mt-3">
              <p>{{ $t('events.selected') }}: {{ selected.join(', ') }}</p>
              <p>{{ $t('events.cost') }}: {{ computedStake }}</p>
              <p>{{ $t('events.your_balance') || 'Balance' }}: {{ balanceShort }}</p>
              <c-button
                color="primary"
                :disabled="!canSubmit && !finished"
                @click="finished ? resetGame() : submitEntry()"
              >
                {{ finished ? ($t('events.new_game') || 'New game') : ($t('events.play') || 'Play') }}
              </c-button>
            </div>

            <div class="mt-4">
              <h5>History</h5>
              <table class="table table-sm">
                <thead>
                  <tr><th>When</th><th>Numbers</th><th>Stake</th><th>Payout</th></tr>
                </thead>
                <tbody>
                  <tr v-for="h in history" :key="h.id">
                    <td>{{ new Date(h.created_at).toLocaleString() }}</td>
                    <td>{{ (h.numbers || []).join(', ') }}</td>
                    <td>{{ h.stake }}</td>
                    <td>{{ h.payout ?? '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="message" class="mt-3">
              <c-alert :color="messageType === 'success' ? 'success' : 'danger'">{{ message }}</c-alert>
            </div>

          </div>
        </div>
      </c-card-body>
    </c-card>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, inject } from 'vue';
import axios from 'axios';
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
    const res = await axios.get('/api/events/lottery/jackpot');
    if (res.data.success) jackpot.value = res.data.jackpot;
  } catch (e) {}
};

// fetch and update observedJackpot used for display and comparison
const fetchJackpotObserved = async () => {
  try {
    const res = await axios.get('/api/events/lottery/jackpot');
    if (res.data.success) {
      jackpot.value = res.data.jackpot;
      observedJackpot.value = res.data.jackpot;
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
      const latest = await axios.get('/api/events/lottery/jackpot');
      if (latest.data && latest.data.success) {
        const latestVal = latest.data.jackpot ?? 0;
        // if jackpot decreased since user saw it, block the bet and notify
        if (latestVal < observedJackpot.value) {
          message.value = translateWithParams('events.jackpot_decreased', { old: observedJackpot.value, new: latestVal });
          messageType.value = 'warning';
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
      message.value = $t('events.entry_success') || 'Entry successful';
      messageType.value = 'success';
      // server returned draw result directly with the entry
      const drawNumbers = res.data.draw || [];
      const myEntry = res.data.entry || null;
      const matches = res.data.matches ?? (myEntry ? (myEntry.numbers || []).filter(n => drawNumbers.includes(n)).length : 0);
      const payout = res.data.payout ?? (myEntry ? myEntry.payout : 0);

      try {
        animating.value = true;
        await animateDraw(drawNumbers);

        // show match message
        if (matches >= 3) {
          message.value = translateWithParams('events.matched', { count: matches, payout: payout });
        } else {
          message.value = translateWithParams('events.no_match', { count: matches });
        }
        messageType.value = 'success';

        // refresh jackpot and user balance
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
      message.value = res.data.message || 'Error';
      messageType.value = 'error';
    }
  } catch (e) {
    // Prefer server-provided message when available
    const resp = e?.response;
    if (resp && resp.data) {
      if (resp.data.message) {
        message.value = resp.data.message;
      } else if (resp.data.errors) {
        // If validation errors, show the first one
        const first = Object.values(resp.data.errors)[0];
        message.value = Array.isArray(first) ? first[0] : String(first);
      } else {
        message.value = JSON.stringify(resp.data);
      }
    } else {
      message.value = 'Server error';
    }
    messageType.value = 'error';
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
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
  max-width: 420px;
}
.lottery-cell {
  padding: 10px;
  border: 1px solid #ccc;
  background: white;
  cursor: pointer;
}
.lottery-cell.selected { background: #007bff; color: white; }
.lottery-cell.drawn { background: #6f42c1; color: white; }
.lottery-cell.highlighted { box-shadow: 0 0 8px 3px rgba(255,200,0,0.8); }
.lottery-cell.selected-drawn { background: #28a745; color: white; }
</style>
