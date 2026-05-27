<script setup lang="ts">
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useAutoAdvance } from '@/composables/useAutoAdvance';
import { useReadStatus } from '@/composables/useReadStatus';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from '@/lib/axios';
import { TitleTooltip } from '@/components/ui/tooltip';
import { Bookmark, CheckCheck, ChevronRight, FolderOpen, LogOut, Rss, Settings, Settings2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from './AppLogo.vue';

const { t } = useI18n();
const page = usePage();
const { adjustedFeedCount, feedReadDelta, zeroedFeeds, markAllAsRead, markAllSignal, zeroFeedUnread, zeroFeedsUnread } = useReadStatus();
const { navigateToNextUnreadCategory } = useAutoAdvance();

interface SidebarCategory {
    id: number;
    name: string;
}

interface SidebarFeed {
    id: number;
    name: string;
    category_id: number | null;
    favicon_url: string | null;
    unread_count: number;
    disable_full_article_scraping: boolean;
    hide_image_in_detail: boolean;
}

const sidebarCategories = computed(() => ((page.props as any).sidebarCategories as SidebarCategory[]) ?? []);
const sidebarFeeds = computed(() => ((page.props as any).sidebarFeeds as SidebarFeed[]) ?? []);
const selectedFeedId = computed(() => ((page.props as any).selectedFeedId as number | null) ?? null);
const selectedCategoryId = computed(() => ((page.props as any).selectedCategoryId as number | null) ?? null);

// ---------------------------------------------------------------------------
// Per-feed customization modal state
// ---------------------------------------------------------------------------
const customizeFeedTarget = ref<SidebarFeed | null>(null);
const customizeOpen = ref(false);
const customizeScraping = ref(false);
const customizeHideImage = ref(false);
const customizeSaving = ref(false);
const customizeSaved = ref(false);

function openCustomize(feed: SidebarFeed): void {
    customizeFeedTarget.value = feed;
    customizeScraping.value = feed.disable_full_article_scraping;
    customizeHideImage.value = feed.hide_image_in_detail;
    customizeSaved.value = false;
    customizeOpen.value = true;
}

async function saveCustomize(): Promise<void> {
    if (!customizeFeedTarget.value) return;
    customizeSaving.value = true;
    try {
        await axios.patch(`/api/feeds/${customizeFeedTarget.value.id}/customize`, {
            disable_full_article_scraping: customizeScraping.value,
            hide_image_in_detail: customizeHideImage.value,
        });
        // Update local sidebar data optimistically
        const feed = sidebarFeeds.value.find((f) => f.id === customizeFeedTarget.value!.id);
        if (feed) {
            feed.disable_full_article_scraping = customizeScraping.value;
            feed.hide_image_in_detail = customizeHideImage.value;
        }
        customizeSaved.value = true;
        setTimeout(() => (customizeOpen.value = false), 900);
    } finally {
        customizeSaving.value = false;
    }
}


const currentUrl = computed(() => page.url);

const feedsByCategory = computed(() => {
    const map = new Map<number | null, SidebarFeed[]>();
    for (const feed of sidebarFeeds.value) {
        const key = feed.category_id;
        if (!map.has(key)) {
            map.set(key, []);
        }
        map.get(key)!.push(feed);
    }
    return map;
});

const uncategorizedFeeds = computed(() => feedsByCategory.value.get(null) ?? []);

/** Live unread count for a feed (server count adjusted by local read delta) */
function feedUnread(feed: SidebarFeed): number {
    return adjustedFeedCount(feed.id, feed.unread_count);
}

/** Summed unread count for a category */
const categoryUnread = computed(() => {
    // feedReadDelta and zeroedFeeds are referenced explicitly to ensure reactivity
    void feedReadDelta.value;
    void zeroedFeeds.value;
    const map = new Map<number, number>();
    for (const feed of sidebarFeeds.value) {
        if (feed.category_id == null) continue;
        const count = feedUnread(feed);
        map.set(feed.category_id, (map.get(feed.category_id) ?? 0) + count);
    }
    return map;
});

const expandedCategories = ref<Set<number>>(new Set());

function initExpanded() {
    for (const cat of sidebarCategories.value) {
        expandedCategories.value.add(cat.id);
    }
}
initExpanded();

function isAllArticlesActive(): boolean {
    return currentUrl.value === '/feeds' || (currentUrl.value.startsWith('/feeds') && selectedFeedId.value === null);
}

function isFeedActive(feedId: number): boolean {
    return selectedFeedId.value === feedId;
}

function isCategoryActive(categoryId: number): boolean {
    return selectedCategoryId.value === categoryId && selectedFeedId.value === null;
}

function isSavedActive(): boolean {
    return currentUrl.value.startsWith('/saved');
}

function selectFeed(feedId: number | null) {
    if (feedId === null) {
        router.visit('/feeds', { preserveScroll: true });
    } else {
        router.visit(route('feeds.index'), {
            data: { feed_id: feedId },
            preserveScroll: true,
        });
    }
}

function selectCategory(categoryId: number) {
    router.visit(route('feeds.index'), {
        data: { category_id: categoryId },
        preserveScroll: true,
    });
}

async function markAllForCategory(e: Event, categoryId: number): Promise<void> {
    e.stopPropagation();
    await markAllAsRead({ categoryId });
    zeroFeedsUnread((feedsByCategory.value.get(categoryId) ?? []).map((f) => f.id));
    navigateToNextUnreadCategory(categoryId, sidebarCategories.value, categoryUnread.value);
}

async function markAllForFeed(e: Event, feedId: number): Promise<void> {
    e.stopPropagation();
    await markAllAsRead({ feedId });
    zeroFeedUnread(feedId);
}

// Handle mark-all triggered from the main toolbar (feeds/Index.vue) or anywhere else
watch(markAllSignal, (sig) => {
    if (!sig) return;
    if (sig.feedId != null) {
        zeroFeedUnread(sig.feedId);
    } else if (sig.categoryId != null) {
        zeroFeedsUnread((feedsByCategory.value.get(sig.categoryId) ?? []).map((f) => f.id));
        navigateToNextUnreadCategory(sig.categoryId, sidebarCategories.value, categoryUnread.value);
    } else {
        // No filter — mark all feeds as read
        zeroFeedsUnread(sidebarFeeds.value.map((f) => f.id));
    }
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('feeds.index')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <!-- Top navigation -->
            <SidebarGroup class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton :is-active="isAllArticlesActive()" :tooltip="t('nav.feeds')" as-child>
                            <button @click="selectFeed(null)">
                                <Rss />
                                <span>{{ t('nav.feeds') }}</span>
                            </button>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton :is-active="isSavedActive()" :tooltip="t('nav.saved')" as-child>
                            <Link :href="route('saved.index')">
                                <Bookmark />
                                <span>{{ t('nav.saved') }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Feed Sources grouped by category -->
            <SidebarGroup v-if="sidebarFeeds.length > 0" class="px-2">
                <SidebarGroupLabel>{{ t('nav.categories') }}</SidebarGroupLabel>
                <SidebarMenu>
                    <!-- Uncategorized feeds -->
                    <SidebarMenuItem v-for="feed in uncategorizedFeeds" :key="feed.id">
                        <SidebarMenuButton :is-active="isFeedActive(feed.id)" :tooltip="feed.name" as-child>
                            <button @click="selectFeed(feed.id)" class="group flex w-full items-center gap-2 pr-1">
                                <!-- Favicon / gear toggle -->
                                <span class="relative h-4 w-4 shrink-0">
                                    <img v-if="feed.favicon_url" :src="feed.favicon_url" class="h-4 w-4 rounded-sm object-contain transition-opacity group-hover:opacity-0" alt="" />
                                    <Rss v-else class="h-4 w-4 opacity-60 transition-opacity group-hover:opacity-0" />
                                    <TitleTooltip :title="t('feeds.customize')" side="top">
                                        <span
                                            class="absolute inset-0 flex cursor-pointer items-center justify-center rounded p-0.5 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-muted hover:text-foreground"
                                            @click.stop="openCustomize(feed)"
                                        >
                                            <Settings2 class="h-3.5 w-3.5 text-muted-foreground" />
                                        </span>
                                    </TitleTooltip>
                                </span>
                                <span class="flex-1 truncate text-left">{{ feed.name }}</span>
                                <span
                                    v-if="feedUnread(feed) > 0"
                                    class="shrink-0 rounded-full bg-primary px-1.5 py-0.5 text-[10px] leading-none font-semibold text-primary-foreground"
                                >
                                    {{ feedUnread(feed) > 99 ? '99+' : feedUnread(feed) }}
                                </span>
                                <TitleTooltip :title="t('general.mark_all_read')" side="top">
                                    <span
                                        class="shrink-0 cursor-pointer rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                        @click="markAllForFeed($event, feed.id)"
                                    >
                                        <CheckCheck class="h-3.5 w-3.5" />
                                    </span>
                                </TitleTooltip>
                            </button>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <!-- Categories with feeds -->
                    <Collapsible
                        v-for="category in sidebarCategories"
                        :key="category.id"
                        as-child
                        :default-open="expandedCategories.has(category.id)"
                    >
                        <SidebarMenuItem>
                            <SidebarMenuButton :tooltip="category.name" :is-active="isCategoryActive(category.id)" as-child>
                                <button @click="selectCategory(category.id)" class="flex w-full items-center gap-2 pr-1">
                                    <FolderOpen class="h-4 w-4 shrink-0" />
                                    <span class="flex-1 truncate text-left">{{ category.name }}</span>
                                    <span
                                        v-if="(categoryUnread.get(category.id) ?? 0) > 0"
                                        class="shrink-0 rounded-full bg-primary px-1.5 py-0.5 text-[10px] leading-none font-semibold text-primary-foreground"
                                    >
                                        {{ (categoryUnread.get(category.id) ?? 0) > 99 ? '99+' : categoryUnread.get(category.id) }}
                                    </span>
                                    <TitleTooltip :title="t('general.mark_all_read')" side="top">
                                        <span
                                            class="shrink-0 cursor-pointer rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                            @click="markAllForCategory($event, category.id)"
                                        >
                                            <CheckCheck class="h-3.5 w-3.5" />
                                        </span>
                                    </TitleTooltip>
                                    <CollapsibleTrigger as-child>
                                        <span class="shrink-0 rounded p-0.5 hover:bg-muted" @click.stop>
                                            <ChevronRight
                                                class="h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                            />
                                        </span>
                                    </CollapsibleTrigger>
                                </button>
                            </SidebarMenuButton>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem v-for="feed in feedsByCategory.get(category.id) ?? []" :key="feed.id">
                                        <SidebarMenuSubButton :is-active="isFeedActive(feed.id)" as-child>
                                            <button @click="selectFeed(feed.id)" class="group flex w-full items-center gap-2 pr-1">
                                                <!-- Favicon / gear toggle -->
                                                <span class="relative h-3.5 w-3.5 shrink-0">
                                                    <img
                                                        v-if="feed.favicon_url"
                                                        :src="feed.favicon_url"
                                                        class="h-3.5 w-3.5 rounded-sm object-contain transition-opacity group-hover:opacity-0"
                                                        alt=""
                                                    />
                                                    <Rss v-else class="h-3.5 w-3.5 opacity-60 transition-opacity group-hover:opacity-0" />
                                                    <TitleTooltip :title="t('feeds.customize')" side="top">
                                                        <span
                                                            class="absolute inset-0 flex cursor-pointer items-center justify-center rounded p-0.5 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-muted hover:text-foreground"
                                                            @click.stop="openCustomize(feed)"
                                                        >
                                                            <Settings2 class="h-3 w-3 text-muted-foreground" />
                                                        </span>
                                                    </TitleTooltip>
                                                </span>
                                                <span class="flex-1 truncate text-left">{{ feed.name }}</span>
                                                <span
                                                    v-if="feedUnread(feed) > 0"
                                                    class="shrink-0 rounded-full bg-primary px-1.5 py-0.5 text-[10px] leading-none font-semibold text-primary-foreground"
                                                >
                                                    {{ feedUnread(feed) > 99 ? '99+' : feedUnread(feed) }}
                                                </span>
                                                <TitleTooltip :title="t('general.mark_all_read')" side="top">
                                                    <span
                                                        class="shrink-0 cursor-pointer rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                        @click="markAllForFeed($event, feed.id)"
                                                    >
                                                        <CheckCheck class="h-3 w-3" />
                                                    </span>
                                                </TitleTooltip>
                                            </button>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                    <SidebarMenuSubItem v-if="(feedsByCategory.get(category.id) ?? []).length === 0">
                                        <span class="px-2 py-1 text-xs text-muted-foreground">No feeds</span>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </Collapsible>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Settings + Logout -->
            <SidebarGroup class="mt-auto px-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton :is-active="currentUrl.startsWith('/settings')" :tooltip="t('nav.settings')" as-child>
                            <Link :href="route('profile.edit')">
                                <Settings />
                                <span>{{ t('nav.settings') }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton :tooltip="t('auth.logout')" as-child>
                            <Link method="post" :href="route('logout')" @click="router.flushAll()" as="button">
                                <LogOut />
                                <span>{{ t('auth.logout') }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>
    </Sidebar>
    <slot />

    <!-- ── Feed Customization Modal ─────────────────────────────────────── -->
    <Dialog v-model:open="customizeOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('feeds.customize_title') }}<template v-if="customizeFeedTarget"> — {{ customizeFeedTarget.name }}</template></DialogTitle>
            </DialogHeader>

            <div class="space-y-5 py-2">
                <!-- Disable full article scraping -->
                <div class="flex items-start gap-3">
                    <input
                        id="customize-disable-scraping"
                        v-model="customizeScraping"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer rounded border-border accent-primary"
                    />
                    <label for="customize-disable-scraping" class="cursor-pointer space-y-0.5">
                        <span class="block text-sm font-medium leading-snug">{{ t('feeds.disable_scraping_label') }}</span>
                        <span class="block text-xs text-muted-foreground">{{ t('feeds.disable_scraping_desc') }}</span>
                    </label>
                </div>

                <!-- Hide image in detail popup -->
                <div class="flex items-start gap-3">
                    <input
                        id="customize-hide-image"
                        v-model="customizeHideImage"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer rounded border-border accent-primary"
                    />
                    <label for="customize-hide-image" class="cursor-pointer space-y-0.5">
                        <span class="block text-sm font-medium leading-snug">{{ t('feeds.hide_image_label') }}</span>
                        <span class="block text-xs text-muted-foreground">{{ t('feeds.hide_image_desc') }}</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 pt-2">
                <span v-if="customizeSaved" class="text-xs text-green-600 dark:text-green-400">{{ t('feeds.customize_saved') }}</span>
                <span v-else class="flex-1" />
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="cursor-pointer rounded-lg border border-border px-4 py-1.5 text-sm hover:bg-muted"
                        @click="customizeOpen = false"
                    >
                        {{ t('general.cancel') }}
                    </button>
                    <button
                        type="button"
                        :disabled="customizeSaving"
                        class="cursor-pointer rounded-lg bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="saveCustomize"
                    >
                        {{ customizeSaving ? t('general.loading') : t('general.save') }}
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
