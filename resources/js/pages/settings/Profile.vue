<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type LocaleMeta, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    availableLocales: LocaleMeta[];
    availableTimezones: string[];
    userLocale: string;
    userTimezone: string;
}

const props = defineProps<Props>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('settings.breadcrumb.profile'),
        href: '/settings/profile',
    },
]);

const page = usePage();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
    locale: props.userLocale,
    timezone: props.userTimezone,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="t('settings.profile.profile_information')"
                    :description="t('settings.profile.profile_information_desc')"
                />

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Name -->
                    <div class="grid gap-2">
                        <Label for="name">{{ t('settings.profile.name') }}</Label>
                        <Input id="name" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" placeholder="Full name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- Email -->
                    <div class="grid gap-2">
                        <Label for="email">{{ t('settings.profile.email') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <!-- Unverified email notice -->
                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Your email address is unverified.
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Click here to resend the verification email.
                            </Link>
                        </p>
                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                            A new verification link has been sent to your email address.
                        </div>
                    </div>

                    <!-- Separator -->
                    <hr class="border-border" />

                    <HeadingSmall
                        :title="t('settings.profile.preferences')"
                        :description="t('settings.profile.preferences_desc')"
                    />

                    <!-- Language -->
                    <div class="grid gap-2">
                        <Label for="locale">{{ t('settings.profile.language') }}</Label>
                        <select
                            id="locale"
                            v-model="form.locale"
                            class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option v-for="loc in availableLocales" :key="loc.code" :value="loc.code">
                                {{ loc.native }} <template v-if="loc.native !== loc.name">({{ loc.name }})</template>
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.locale" />
                    </div>

                    <!-- Timezone -->
                    <div class="grid gap-2">
                        <Label for="timezone">{{ t('settings.profile.timezone') }}</Label>
                        <select
                            id="timezone"
                            v-model="form.timezone"
                            class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option v-for="tz in availableTimezones" :key="tz" :value="tz">{{ tz }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.timezone" />
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">{{ t('settings.profile.save') }}</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600">{{ t('settings.profile.saved') }}</p>
                        </Transition>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
