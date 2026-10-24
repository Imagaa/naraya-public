function loadResizedCanvas(file, maxDimension) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const url = URL.createObjectURL(file);

        img.onload = () => {
            URL.revokeObjectURL(url);

            const scale = Math.min(1, maxDimension / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            resolve(canvas);
        };

        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('image-load-failed'));
        };

        img.src = url;
    });
}

function canvasToJpegBlob(canvas, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => (blob ? resolve(blob) : reject(new Error('compression-failed'))),
            'image/jpeg',
            quality
        );
    });
}

export async function compressImage(file, maxDimension = 1280, quality = 0.7) {
    const canvas = await loadResizedCanvas(file, maxDimension);
    return canvasToJpegBlob(canvas, quality);
}

/** Resizes the image and stamps `text` onto a solid bar across its bottom edge. */
export async function watermarkAndCompressImage(file, text, maxDimension = 1280, quality = 0.8) {
    const canvas = await loadResizedCanvas(file, maxDimension);
    const ctx = canvas.getContext('2d');

    const barHeight = Math.max(28, Math.round(canvas.height * 0.06));
    ctx.fillStyle = 'rgba(0, 0, 0, 0.55)';
    ctx.fillRect(0, canvas.height - barHeight, canvas.width, barHeight);

    ctx.fillStyle = '#ffffff';
    ctx.font = `${Math.max(11, Math.round(barHeight * 0.42))}px sans-serif`;
    ctx.textBaseline = 'middle';
    ctx.fillText(text, 8, canvas.height - barHeight / 2, canvas.width - 16);

    return canvasToJpegBlob(canvas, quality);
}
