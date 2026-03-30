import { onMounted, onUnmounted, ref } from 'vue';

const feedUpdated = ref(false);

export function useFeedNotifications() {
    let channel: any;

    onMounted(() => {
        // Replace with actual Reverb/Echo setup
        // Example: window.Echo.channel('feeds').listen('FeedUpdated', () => { ... })
        if ((window as any).Echo) {
            channel = (window as any).Echo.channel('feeds').listen('FeedUpdated', () => {
                feedUpdated.value = true;
            });
        }
    });

    onUnmounted(() => {
        if (channel && channel.stopListening) {
            channel.stopListening('FeedUpdated');
        }
    });

    function resetNotification() {
        feedUpdated.value = false;
    }

    return { feedUpdated, resetNotification };
}
