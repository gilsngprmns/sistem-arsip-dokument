<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: 'edit',
    },
    initialData: {
        type: Object,
        default: null,
    },
    seksiList: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const page = usePage();

const title = computed(() =>
    props.mode === 'edit' ? 'Perbarui Rak Arsip' : 'Tambah Rak Arsip',
);

// Truncate long option labels so they fit the dropdown button width
const truncateOption = (text, maxChars = 28) => {
    const s = String(text ?? '');
    return s.length > maxChars ? s.slice(0, maxChars - 1) + '…' : s;
};

const emptyForm = () => ({
    seksi_id: '',
    kode_rak: '',
    nama_rak: '',
    nomor_rak: '',
    deskripsi: '',
    is_active: true,
});

const form = ref(emptyForm());

const errors = computed(() => {
    if (!props.show) return {};
    return page.props.errors ?? {};
});

const fillForm = () => {
    if (props.mode === 'edit' && props.initialData) {
        form.value = {
            seksi_id: props.initialData.seksi_id ?? '',
            kode_rak: props.initialData.kode_rak ?? '',
            nama_rak: props.initialData.nama_rak ?? '',
            nomor_rak: props.initialData.nomor_rak ?? '',
            deskripsi: props.initialData.deskripsi ?? '',
            is_active: Boolean(props.initialData.is_active ?? true),
        };
    } else {
        form.value = emptyForm();
    }
};

watch(
    () => [props.show, props.mode, props.initialData],
    ([show]) => {
        if (show) {
            fillForm();
        }
    },
);

const close = () => emit('close');

const processing = ref(false);

const submit = () => {
    processing.value = true;

    if (props.mode === 'edit' && props.initialData?.id) {
        router.put(
            route('rak.update', props.initialData.id),
            form.value,
            {
                preserveScroll: true,
                onSuccess: () => {
                    processing.value = false;
                    close();
                },
                onError: () => {
                    processing.value = false;
                },
            },
        );
        return;
    }

    router.post(
        route('rak.store'),
        form.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                processing.value = false;
                close();
            },
            onError: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="close">
        <div class="border-b border-slate-100 px-6 py-4">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-lg font-bold text-slate-800">{{ title }}</h2>
                <button
                    type="button"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    @click="close"
                >
                    <span class="sr-only">Tutup</span>
                    <AppIcon name="close" :size="20" :stroke-width="2" class="h-5 w-5" decorative />
                </button>
            </div>
        </div>

        <form class="space-y-4 px-6 py-5 max-h-[75vh] overflow-y-auto rounded-lg" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <InputLabel for="seksi_id" value="Seksi Pemilik *" />
                    <select
                        id="seksi_id"
                        v-model="form.seksi_id"
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                        required
                    >
                        <option value="">Pilih seksi</option>
                        <option v-for="seksi in seksiList" :key="seksi.id" :value="seksi.id">
                            {{ truncateOption(seksi.nama_seksi) }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="errors.seksi_id" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <InputLabel for="kode_rak" value="Kode Rak *" />
                    <TextInput
                        id="kode_rak"
                        v-model="form.kode_rak"
                        class="mt-1 block w-full"
                        placeholder="mis. RAK-PEM-01"
                        required
                    />
                    <InputError class="mt-1" :message="errors.kode_rak" />
                </div>

                <div class="min-w-0">
                    <InputLabel for="nama_rak" value="Nama Rak *" />
                    <TextInput
                        id="nama_rak"
                        v-model="form.nama_rak"
                        class="mt-1 block w-full"
                        placeholder="mis. Rak No. 1 Seksi Pemerintahan"
                        required
                    />
                    <InputError class="mt-1" :message="errors.nama_rak" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <InputLabel for="nomor_rak" value="Nomor Rak" />
                    <TextInput
                        id="nomor_rak"
                        v-model="form.nomor_rak"
                        class="mt-1 block w-full"
                        placeholder="mis. 1"
                    />
                    <InputError class="mt-1" :message="errors.nomor_rak" />
                </div>
            </div>

            <div>
                <InputLabel for="deskripsi" value="Deskripsi" />
                <textarea
                    id="deskripsi"
                    v-model="form.deskripsi"
                    rows="3"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                    placeholder="Catatan tambahan tentang rak..."
                ></textarea>
                <InputError class="mt-1" :message="errors.deskripsi" />
            </div>

            <div>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    Rak aktif
                </label>
                <InputError class="mt-1" :message="errors.is_active" />
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                <SecondaryButton type="button" @click="close">Batal</SecondaryButton>
                <PrimaryButton
                    class="bg-brand-blue hover:bg-blue-700"
                    :disabled="processing"
                >
                    {{ mode === 'edit' ? 'Simpan Perubahan' : 'Simpan Rak' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
