import { getLocation } from './geolocation';
import { watermarkAndCompressImage } from './image-compression';

// Camera-only proof photo for the Sales delivery flow: a location + timestamp
// watermark is burned into the image itself at capture time (not just stored
// as separate columns) so the proof can't be swapped for a gallery photo
// taken elsewhere without the watermark giving it away.
document.addEventListener('alpine:init', () => {
    Alpine.data('deliveryPhoto', (wireModel) => ({
        preview: null,
        uploading: false,
        errorMsg: null,

        async capture(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;

            this.uploading = true;
            this.errorMsg = null;

            try {
                const location = await getLocation();
                const timestamp = new Date().toLocaleString('id-ID');
                const label = location.latitude !== null
                    ? `${location.latitude.toFixed(5)}, ${location.longitude.toFixed(5)} — ${timestamp}`
                    : `Lokasi tidak tersedia — ${timestamp}`;

                const blob = await watermarkAndCompressImage(file, label);
                const previousPreview = this.preview;
                this.preview = URL.createObjectURL(blob);
                if (previousPreview) URL.revokeObjectURL(previousPreview);

                await new Promise((resolve, reject) => {
                    this.$wire.upload(
                        wireModel,
                        new File([blob], 'foto.jpg', { type: 'image/jpeg' }),
                        () => resolve(),
                        () => reject(new Error('upload-failed'))
                    );
                });
            } catch (e) {
                this.errorMsg = 'Gagal memproses atau mengunggah foto. Coba lagi.';
                this.preview = null;
            } finally {
                this.uploading = false;
            }
        },

        retake() {
            this.preview = null;
        },
    }));
});
