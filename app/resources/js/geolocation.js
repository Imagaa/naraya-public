export function getLocation(timeout = 8000) {
    return new Promise((resolve) => {
        if (!('geolocation' in navigator)) {
            resolve({ latitude: null, longitude: null });
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude }),
            () => resolve({ latitude: null, longitude: null }),
            { timeout }
        );
    });
}
