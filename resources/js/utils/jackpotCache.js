import axios from 'axios';

// Simple in-memory cache for jackpot value.
// Prevents rapid repeated requests to /api/events/lottery/jackpot when
// users quickly switch menus. Shares a pending request so concurrent callers
// reuse the same promise.

const TTL = 10 * 1000; // 10 seconds cache
let cache = {
  jackpot: null,
  ts: 0,
  pending: null,
};

export async function getJackpot(force = false) {
  const now = Date.now();
  if (!force && cache.jackpot != null && (now - cache.ts) < TTL) {
    return { success: true, jackpot: cache.jackpot };
  }

  if (cache.pending) {
    // return the same pending promise
    return cache.pending;
  }

  cache.pending = (async () => {
    try {
      const res = await axios.get('/api/events/lottery/jackpot');
      if (res && res.data && res.data.success) {
        cache.jackpot = res.data.jackpot;
        cache.ts = Date.now();
        return { success: true, jackpot: cache.jackpot };
      }
      // Unexpected response - do not overwrite cache but return failure
      return { success: false };
    } catch (e) {
      // On error, propagate so callers can fallback; do not clobber existing cache
      throw e;
    } finally {
      cache.pending = null;
    }
  })();

  return cache.pending;
}

export function clearJackpotCache() {
  cache.jackpot = null;
  cache.ts = 0;
}
