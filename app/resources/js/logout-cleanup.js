// Every portal's logout form marked data-push-logout: stop pushes to this
// device (and tell the server which subscription to forget via the hidden
// push_endpoint field), drop the sales pages cached for offline use and the
// app badge — so the next person on a shared device sees none of it.
document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-push-logout]');
    if (!form || form.dataset.cleaned) return;
    event.preventDefault();
    try {
        const registration = 'serviceWorker' in navigator ? await navigator.serviceWorker.getRegistration() : null;
        const subscription = await registration?.pushManager?.getSubscription();
        if (subscription) {
            form.elements.push_endpoint.value = subscription.endpoint;
            await subscription.unsubscribe();
        }
        if ('caches' in window) {
            const keys = await caches.keys();
            await Promise.all(keys.filter((key) => key.startsWith('naraya-sales-pages-')).map((key) => caches.delete(key)));
        }
        if ('clearAppBadge' in navigator) await navigator.clearAppBadge();
        // the Android app (resources/js/sales.js): forget its FCM token and clear its notifications
        const fcmToken = localStorage.getItem('naraya-fcm-token');
        if (fcmToken && form.elements.fcm_token) form.elements.fcm_token.value = fcmToken;
        sessionStorage.removeItem('naraya-fcm-sent');
        await window.Capacitor?.Plugins?.PushNotifications?.removeAllDeliveredNotifications();
    } catch (error) {
        // Logging out matters more than the cleanup.
    }
    form.dataset.cleaned = '1';
    form.submit();
});
