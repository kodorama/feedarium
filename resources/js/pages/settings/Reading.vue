<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAutoAdvance } from '@/composables/useAutoAdvance';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const { autoAdvanceEnabled, setAutoAdvance } = useAutoAdvance();

const isAdmin = computed(() => (page.props.auth?.user as any)?.is_admin === true);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Reading', href: '/settings/reading' },
];

interface Toast {
    type: 'success' | 'error';
    message: string;
}

const toast = ref<Toast | null>(null);
const loading = ref(false);
const saving = ref(false);

const retentionEnabled = ref(true);
const retentionDays = ref(90);

function showToast(type: Toast['type'], message: string) {
    toast.value = { type, message };
    setTimeout(() => {
        toast.value = null;
    }, 3500);
}

async function loadRetentionSettings() {
    if (!isAdmin.value) return;
    loading.value = true;
    try {
        const res = await axios.get('/api/settings');
        retentionEnabled.value = res.data.news_retention_enabled;
        retentionDays.value = res.data.news_retention_days;
    } catch {
        // silently ignore; defaults are already set
    } finally {
        loading.value = false;
    }
}

async function saveRetentionSettings() {
    saving.value = true;
    try {
        await axios.patch('/api/settings', {
            news_retention_enabled: retentionEnabled.value,
            news_retention_days: retentionDays.value,
        });
        showToast('success', 'Retention settings saved.');
    } catch {
        showToast('error', 'Failed to save retention settings.');
    } finally {
        saving.value = false;
    }
}

onMounted(loadRetentionSettings);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Reading — Settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <!-- Toast notification -->
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 -translate-y-1"
                    leave-active-class="transition ease-in duration-150"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <div
                        v-if="toast"
                        :class="[
                            'flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium',
                            toast.type === 'success'
                                ? 'bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-200'
                                : 'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-200',
                        ]"
                    >
                        <Check v-if="toast.type === 'success'" class="h-4 w-4 shrink-0" />
                        <X v-else class="h-4 w-4 shrink-0" />
                        {{ toast.message }}
                    </div>
                </Transition>

                <!-- Auto-advance section -->
                <div class="space-y-4">
                    <HeadingSmall :title="t('general.auto_advance')" :description="t('general.auto_advance_desc')" />

                    <div class="flex items-center gap-3">
                        <input
                            id="auto-advance"
                            type="checkbox"
                            class="h-4 w-4 cursor-pointer rounded border-border accent-primary"
                            :checked="autoAdvanceEnabled"
                            @change="setAutoAdvance(($event.target as HTMLInputElement).checked)"
                        />
                        <Label for="auto-advance" class="cursor-pointer font-normal">
                            {{ t('general.auto_advance') }}
                        </Label>
                    </div>
                </div>

                <!-- Article retention section (admin only) -->
                <div v-if="isAdmin" class="space-y-4 border-t border-border pt-6">
                    <HeadingSmall :title="t('general.news_retention')" :description="t('general.news_retention_desc')" />

                    <div v-if="loading" class="text-sm text-muted-foreground">{{ t('general.loading') }}</div>

                    <template v-else>
                        <div class="flex items-center gap-3">
                            <input
                                id="retention-enabled"
                                type="checkbox"
                                class="h-4 w-4 cursor-pointer rounded border-border accent-primary"
                                v-model="retentionEnabled"
                            />
                            <Label for="retention-enabled" class="cursor-pointer font-normal">
                                {{ t('general.news_retention_enabled') }}
                            </Label>
                        </div>

                        <div v-if="retentionEnabled" class="grid gap-1.5">
                            <Label for="retention-days">{{ t('general.news_retention_days') }}</Label>
                            <Input id="retention-days" v-model.number="retentionDays" type="number" min="1" max="3650" class="w-40" />
                        </div>

                        <Button size="sm" :disabled="saving" @click="saveRetentionSettings">
                            {{ t('general.save_retention_settings') }}
                        </Button>
                    </template>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
