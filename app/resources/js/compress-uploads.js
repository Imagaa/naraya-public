// Every photo picked through a file input (x-ui.file-picker: shop front, profile photo, product, proof…) is shrunk on
// the phone before Livewire uploads it: a phone camera's 5–12 MB photo became too big for the 5 MB limit and slow on a
// weak signal. Longest side 1600 px, JPEG 0.82 — usually 200–500 KB, still sharp for a shop front or a receipt.
// Runs in the capture phase, before Livewire's own change listener on the input, then hands it the smaller files.
import { compressImage } from './image-compression';

const MAX_SIDE = 1600;
const QUALITY = 0.82;
const SMALL_ENOUGH = 700 * 1024; // already small: leave as is

document.addEventListener('change', async (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.files?.length) return;
    if (input.dataset.compressed === '1') { delete input.dataset.compressed; return; } // our own re-dispatch
    const files = [...input.files];
    if (!files.some((f) => f.type.startsWith('image/') && f.type !== 'image/gif' && f.size > SMALL_ENOUGH)) return;

    event.stopImmediatePropagation();
    const out = new DataTransfer();
    for (const file of files) {
        let next = file;
        if (file.type.startsWith('image/') && file.type !== 'image/gif' && file.size > SMALL_ENOUGH) {
            try {
                const blob = await compressImage(file, MAX_SIDE, QUALITY);
                if (blob.size < file.size) next = new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
            } catch (e) { /* a format the browser can't draw (e.g. HEIC): send the original, the server says if it's too big */ }
        }
        out.items.add(next);
    }
    input.files = out.files;
    input.dataset.compressed = '1';
    input.dispatchEvent(new Event('change', { bubbles: true }));
}, true);
