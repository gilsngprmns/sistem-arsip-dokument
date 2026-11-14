<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { institution } from '@/config/institution';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const isLockedOut = computed(() =>
    (form.errors.email ?? '').toLowerCase().includes('dinonaktifkan'),
);
</script>

<template>
    <GuestLayout>
        <Head title="Masuk" />

        <!-- Skeleton khusus form login (slot #skeleton milik GuestLayout) -->
        <template #skeleton>
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <div class="h-10 w-10 rounded-xl bg-slate-200" />
                <div class="space-y-1.5">
                    <div class="h-4 w-44 rounded bg-slate-200" />
                    <div class="h-3 w-56 rounded bg-slate-100" />
                </div>
            </div>
            <div class="mb-8">
                <div class="h-6 w-40 rounded bg-slate-200" />
            </div>
            <div class="space-y-5">
                <div class="space-y-2">
                    <div class="h-4 w-24 rounded bg-slate-200" />
                    <div class="h-11 w-full rounded-lg bg-slate-100" />
                </div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="h-4 w-20 rounded bg-slate-200" />
                        <div class="h-4 w-20 rounded bg-slate-100" />
                    </div>
                    <div class="h-11 w-full rounded-lg bg-slate-100" />
                </div>
                <div class="flex min-h-11 items-center gap-2">
                    <div class="h-5 w-5 rounded bg-slate-200" />
                    <div class="h-4 w-32 rounded bg-slate-100" />
                </div>
                <div class="pt-2">
                    <div class="h-11 w-full rounded-lg bg-slate-200" />
                </div>
            </div>
            <div class="mt-8 h-4 w-48 rounded bg-slate-100" />
        </template>

        <div v-reveal class="mb-8">
            <p class="mb-3 text-sm font-medium text-brand-blue lg:hidden">
                {{ institution.accessNotice }}
            </p>
            <h1 class="text-lg font-semibold tracking-tight text-brand-navy">
                Masuk ke sistem
            </h1>
        </div>

        <div
            v-if="status"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
            {{ status }}
        </div>

        <div
            v-if="isLockedOut"
            class="mb-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
            role="alert"
        >
            <AppIcon name="lock" :size="18" :stroke-width="1.8" class="mt-0.5 shrink-0" decorative />
            <span>
                <strong class="font-semibold">Akun terkunci permanen.</strong>
                {{ form.errors.email }}
            </span>
        </div>

        <form v-reveal :style="{ transitionDelay: '150ms' }" class="space-y-5" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Alamat email" class="text-slate-700" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-2 block w-full rounded-lg border-slate-300 px-4 py-3 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <!-- Pesan akun dinonaktifkan sudah tampil di alert merah di atas form -->
                <InputError v-if="!isLockedOut" class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <div class="flex items-center justify-between gap-3">
                    <InputLabel for="password" value="Kata sandi" class="text-slate-700" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs font-semibold text-brand-blue transition duration-200 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/40"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>

                <div class="relative mt-2">
                    <TextInput
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="block w-full rounded-lg border-slate-300 px-4 py-3 pr-12 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition duration-200 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/40"
                        :title="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        @click="showPassword = !showPassword"
                    >
                        <AppIcon v-if="!showPassword" name="eye" :size="20" :stroke-width="1.8" class="h-5 w-5" decorative />
                        <AppIcon v-else name="eye-off" :size="20" :stroke-width="1.8" class="h-5 w-5" decorative />
                    </button>
                </div>

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <label class="flex min-h-11 items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-slate-500">Ingat perangkat ini</span>
                </label>
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full"
                    :class="{ 'opacity-60': form.processing }"
                    :disabled="form.processing"
                >
                    <span class="flex items-center justify-center gap-2">
                        <AppIcon v-if="form.processing" name="loader" :size="16" :stroke-width="4" class="h-4 w-4 animate-spin" decorative />
                        {{ form.processing ? 'Memproses' : 'Masuk' }}
                    </span>
                </PrimaryButton>
            </div>
        </form>

        <p v-reveal :style="{ transitionDelay: '300ms' }" class="mt-8 text-sm leading-7 text-slate-500">
            Butuh akun atau reset sandi?
            <Link
                :href="route('bantuan')"
                class="font-semibold text-brand-blue transition duration-200 hover:text-blue-800 hover:underline"
            >
                Lihat bantuan
            </Link>
        </p>
    </GuestLayout>
</template>
