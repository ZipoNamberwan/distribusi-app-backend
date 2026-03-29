import { ref, onMounted, onUnmounted } from 'vue';

export function usePWAInstall() {
    const deferredPrompt = ref<any>(null);
    const isInstallable = ref(false);

    const handleBeforeInstallPrompt = (e: Event) => {
        // Prevent the mini-infobar from appearing on mobile
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt.value = e;
        // Update UI notify the user they can install the PWA
        isInstallable.value = true;
    };

    onMounted(() => {
        window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    });

    onUnmounted(() => {
        window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    });

    const promptInstall = async () => {
        if (!deferredPrompt.value) {
            return;
        }
        // Show the install prompt
        deferredPrompt.value.prompt();
        // Wait for the user to respond to the prompt
        const { outcome } = await deferredPrompt.value.userChoice;
        
        // We've used the prompt, and can't use it again, throw it away
        deferredPrompt.value = null;
        isInstallable.value = false;
        
        return outcome === 'accepted';
    };

    return {
        isInstallable,
        promptInstall,
    };
}
