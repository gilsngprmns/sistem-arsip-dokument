import axios from 'axios';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

// ===== State modular (singleton) =====
// AppLayout (badge presence header) dan Dashboard (panel Staf Aktif) sama-sama
// memanggil useStafOnline(). Module scope => state & timer berbagi, satu
// interval, tidak double-poll.
//
// `pollOnNewStaff` (parameter opsional, default null) bisa di-set bila ada
// consumer lain yang ingin callback saat staf baru terdeteksi. AppLayout saat
// ini hanya mengambil count, tidak trigger callback.
//
// Timer tidak di-unbind saat halaman berganti: Inertia SPAs sering
// re-mount komponen saat navigasi, tapi satu timer global lebih
// ringan daripada setup/teardown berulang per halaman. `document.hidden`
// sudah guarding: saat tab di-background, poll() skip.
//
// `route()` tidak tersedia di modul JS biasa (hanya template global Vue
// via ZiggyVue plugin). Mengikuti pola QrScanner.vue: gunakan string URL
// langsung untuk pemanggilan axios dari non-component code.

const STAF_ONLINE_URL = '/api/staf-online';

const active = ref([]);
const lastPolled = ref(null);
const seenIds = ref(null); // Set<string>
let timer = null;
let pollOnNewStaff = null;
let warnedOnce = false; // console.warn hanya pada kegagalan pertama

function isAdmin() {
    return usePage().props.auth?.user?.role === 'admin';
}

async function poll() {
    if (document.hidden) return;
    try {
        const { data } = await axios.get(STAF_ONLINE_URL);
        const ids = data.staf.map((s) => String(s.id));

        if (!seenIds.value) {
            // Poll pertama: seed, tidak toast (staf yang sudah online saat
            // halaman dibuka tidak perlu diulang).
            seenIds.value = new Set(ids);
        } else {
            const fresh = ids.filter((id) => !seenIds.value.has(id));
            if (fresh.length && pollOnNewStaff) {
                pollOnNewStaff(data.staf.filter((s) => fresh.includes(String(s.id))));
            }
            seenIds.value = new Set(ids);
        }

        active.value = data.staf;
        lastPolled.value = new Date();
    } catch (err) {
        // Log sekali saja agar poller mati bisa dideteksi, tidak spam console
        if (!warnedOnce) {
            console.warn('[staf-online] poll gagal:', err?.message || err);
            warnedOnce = true;
        }
    }
}

function ensureTimer() {
    if (timer || !isAdmin()) return;
    poll();
    timer = setInterval(poll, 10_000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') poll();
    });
}

export function useStafOnline(onNewStaff = null) {
    if (onNewStaff) pollOnNewStaff = onNewStaff;
    ensureTimer();

    return {
        active,
        count: computed(() => active.value.length),
        lastPolled,
    };
}
