import { ref } from 'vue';
import axios from 'axios';

export function useSavedArticles() {
    const saving = ref<Record<number, boolean>>({});

    async function save(newsId: number): Promise<void> {
        saving.value[newsId] = true;
        try {
            await axios.post(`/api/news/${newsId}/save`);
        } finally {
            saving.value[newsId] = false;
        }
    }

    async function unsave(newsId: number): Promise<void> {
        saving.value[newsId] = true;
        try {
            await axios.delete(`/api/news/${newsId}/save`);
        } finally {
            saving.value[newsId] = false;
        }
    }

    function isSaving(newsId: number): boolean {
        return !!saving.value[newsId];
    }

    return { save, unsave, isSaving };
}

