<script setup lang="ts">
import NavUser from '@/components/NavUser.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
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
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bookmark, ChevronRight, FolderOpen, Rss, Settings } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from './AppLogo.vue';

const { t } = useI18n();
const page = usePage();

interface SidebarCategory {
    id: number;
    name: string;
}

interface SidebarFeed {
    id: number;
    name: string;
    category_id: number | null;
    favicon_url: string | null;
}

const sidebarCategories = computed(() => (page.props as any).sidebarCategories as SidebarCategory[] ?? []);
const sidebarFeeds = computed(() => (page.props as any).sidebarFeeds as SidebarFeed[] ?? []);
const selectedFeedId = computed(() => (page.props as any).selectedFeedId as number | null ?? null);

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
                        <SidebarMenuButton
                            :is-active="isAllArticlesActive()"
                            :tooltip="t('nav.feeds')"
                            as-child
                        >
                            <button @click="selectFeed(null)">
                                <Rss />
                                <span>{{ t('nav.feeds') }}</span>
                            </button>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            :is-active="isSavedActive()"
                            :tooltip="t('nav.saved')"
                            as-child
                        >
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
                        <SidebarMenuButton
                            :is-active="isFeedActive(feed.id)"
                            :tooltip="feed.name"
                            as-child
                        >
                            <button @click="selectFeed(feed.id)">
                                <img
                                    v-if="feed.favicon_url"
                                    :src="feed.favicon_url"
                                    class="h-4 w-4 shrink-0 rounded-sm object-contain"
                                    alt=""
                                />
                                <Rss v-else class="opacity-60" />
                                <span class="truncate">{{ feed.name }}</span>
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
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton :tooltip="category.name">
                                    <FolderOpen />
                                    <span class="truncate">{{ category.name }}</span>
                                    <ChevronRight
                                        class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                    />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem
                                        v-for="feed in (feedsByCategory.get(category.id) ?? [])"
                                        :key="feed.id"
                                    >
                                        <SidebarMenuSubButton
                                            :is-active="isFeedActive(feed.id)"
                                            as-child
                                        >
                                            <button @click="selectFeed(feed.id)" class="w-full text-left">
                                                <img
                                                    v-if="feed.favicon_url"
                                                    :src="feed.favicon_url"
                                                    class="h-3.5 w-3.5 shrink-0 rounded-sm object-contain"
                                                    alt=""
                                                />
                                                <Rss v-else class="h-3.5 w-3.5 opacity-60" />
                                                <span class="truncate">{{ feed.name }}</span>
                                            </button>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                    <SidebarMenuSubItem
                                        v-if="(feedsByCategory.get(category.id) ?? []).length === 0"
                                    >
                                        <span class="px-2 py-1 text-xs text-muted-foreground">No feeds</span>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </Collapsible>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Settings link -->
            <SidebarGroup class="px-2 mt-auto">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            :is-active="currentUrl.startsWith('/settings')"
                            :tooltip="t('nav.settings')"
                            as-child
                        >
                            <Link :href="route('profile.edit')">
                                <Settings />
                                <span>{{ t('nav.settings') }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
