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
    boks: {
        type: Object,
        default: null,
    },
    rakList: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const page = usePage();

const errors = computed(() => {
    if (!props.show) return {};
    return page.props.errors ?? {};
});

const emptyForm = () => ({
    rak_id: '',
    kode_boks: '',
    kapasitas: '',
    nomor_baris: '',
    nomor_boks: '',
    keterangan: '',
    is_active: true,
});

const form = ref(emptyForm());
const processing = ref(false);

const fillForm = () => {
    if (props.boks) {
        form.value = {
            rak_id: props.boks.rak_id ?? '',
            kode_boks: props.boks.kode_boks ?? '',
            kapasitas: props.boks.kapasitas ?? '',
            nomor_baris: props.boks.nomor_baris ?? '',
            nomor_boks: props.boks.nomor_boks ?? '',
            keterangan: props.boks.keterangan ?? '',
            is_active: Boolean(props.boks.is_active ?? true),
        };
    } else {
        form.value = emptyForm();
    }
};

watch(
    () => [props.show, props.boks],
    ([show]) => {
        if (show) fillForm();
    },
);

const close = () => emit('close');

const submit = () => {
    if (!props.boks?.id) return;
    processing.value = true;

    router.put(
        route('boks.update', props.boks.id),
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

const truncateOption = (text, maxChars = 30) => {
    const s = String(text ?? '');
    return s.length > maxChars ? s.slice(0, maxChars - 1) + '…' : s;
};
</script>

<template>
    <Modal :show="show" max-width="xl" @close="close">
        <div class="border-b border-slate-100 px-6 py-4">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-lg font-bold text-slate-800">Edit Boks</h2>
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

        <form class="space-y-4 px-6 py-5" @submit.prevent="submit">
            <div>
                <InputLabel for="edit_rak_id" value="Rak *" />
                <select
                    id="edit_rak_id"
                    v-model="form.rak_id"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                    required
                >
                    <option value="" disabled>Pilih rak</option>
                    <option v-for="rak in rakList" :key="rak.id" :value="rak.id">
                        {{ truncateOption(rak.kode_rak + ' — ' + (rak.nama_rak ?? '')) }}
                    </option>
                </select>
                <InputError class="mt-1" :message="errors.rak_id" />
            </div>

            <div>
                <InputLabel for="edit_kode_boks" value="Kode Boks *" />
                <TextInput
                    id="edit_kode_boks"
                    v-model="form.kode_boks"
                    class="mt-1 block w-full"
                    placeholder="mis. BOK-RAK-PEM-01"
                    required
                />
                <InputError class="mt-1" :message="errors.kode_boks" />
            </div>

            <div>
                <InputLabel for="edit_kapasitas" value="Kapasitas Dokumen *" />
                <input
                    id="edit_kapasitas"
                    v-model.number="form.kapasitas"
                    type="number"
                    min="1"
                    required
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                />
                <p class="mt-1 text-xs text-slate-500">Batas maksimal dokumen di boks ini.</p>
                <InputError class="mt-1" :message="errors.kapasitas" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="edit_nomor_baris" value="Nomor Baris" />
                    <TextInput
                        id="edit_nomor_baris"
                        v-model="form.nomor_baris"
                        class="mt-1 block w-full"
                        placeholder="mis. B-01"
                    />
                    <InputError class="mt-1" :message="errors.nomor_baris" />
                </div>

                <div>
                    <InputLabel for="edit_nomor_boks" value="Nomor Boks" />
                    <TextInput
                        id="edit_nomor_boks"
                        v-model="form.nomor_boks"
                        class="mt-1 block w-full"
                        placeholder="mis. BOX-A01"
                    />
                    <InputError class="mt-1" :message="errors.nomor_boks" />
                </div>
            </div>

            <div>
                <InputLabel for="edit_keterangan" value="Keterangan" />
                <textarea
                    id="edit_keterangan"
                    v-model="form.keterangan"
                    rows="2"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                    placeholder="Catatan tambahan (opsional)"
                ></textarea>
                <InputError class="mt-1" :message="errors.keterangan" />
            </div>

            <div>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    Boks aktif
                </label>
                <InputError class="mt-1" :message="errors.is_active" />
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                <SecondaryButton type="button" @click="close">Batal</SecondaryButton>
                <PrimaryButton
                    class="bg-brand-blue hover:bg-blue-700"
                    :disabled="processing"
                >
                    {{ processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
