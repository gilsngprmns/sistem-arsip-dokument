<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, router } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    arsip: {
        type: Object,
        default: null,
    },
    mode: {
        type: String,
        default: 'pinjam', // pinjam | kembalikan
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    pihak_terkait: '',
    keterangan: '',
});

const title = computed(() =>
    props.mode === 'kembalikan' ? 'Pengembalian Dokumen' : 'Peminjaman Dokumen',
);

watch(
    () => props.show,
    (open) => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
    },
);

const submit = () => {
    if (!props.arsip) return;

    const routeName = props.mode === 'kembalikan' ? 'arsip.kembalikan' : 'arsip.pinjam';

    form.post(route(routeName, props.arsip.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            // Reload halaman parent untuk memuat flash notifikasi
            if (window.location.pathname.startsWith('/arsip/')) {
                router.reload({ only: ['arsip', 'flash'] });
            }
        },
    });
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <form class="p-6" @submit.prevent="submit">
            <h2 class="text-lg font-bold text-slate-900">{{ title }}</h2>
            <p v-if="arsip" class="mt-1 text-sm text-brand-blue">{{ arsip.kode_arsip }} — {{ arsip.perihal }}</p>

            <div v-if="mode === 'pinjam'" class="mt-5">
                <InputLabel for="pihak_terkait" value="Nama peminjam / pihak terkait" />
                <TextInput
                    id="pihak_terkait"
                    v-model="form.pihak_terkait"
                    class="mt-1 block w-full rounded-xl"
                    required
                    autofocus
                />
                <InputError class="mt-1" :message="form.errors.pihak_terkait" />
            </div>

            <div class="mt-4">
                <InputLabel for="keterangan_sirkulasi" value="Keterangan (opsional)" />
                <textarea
                    id="keterangan_sirkulasi"
                    v-model="form.keterangan"
                    rows="3"
                    class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                />
                <InputError class="mt-1" :message="form.errors.keterangan" />
                <InputError class="mt-1" :message="form.errors.sirkulasi" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    @click="emit('close')"
                >
                    Batal
                </button>
                <PrimaryButton
                    class="bg-brand-blue"
                    :disabled="form.processing"
                >
                    {{ mode === 'kembalikan' ? 'Catat Pengembalian' : 'Catat Pinjam' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
