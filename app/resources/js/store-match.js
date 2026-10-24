// Fuzzy store search for the sales app (store search on Kerja, Atur jadwal): every typed word must match a word of
// the store's name, owner or address — as a prefix, anywhere inside it, or with a typo or two ("sejatera" finds
// Sejahtera, "wrng" finds Warung). Better matches rank first. Runs on the phone, so it works offline.
// Check: node resources/js/store-match.check.mjs

export const fold = (t) => (t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

const tokens = (t) => fold(t).split(/[^a-z0-9]+/).filter(Boolean);

/** Typos allowed for a typed word: none up to 2 letters, one up to 6, then two. */
const allowed = (w) => (w.length <= 2 ? 0 : w.length <= 6 ? 1 : 2);

/** Damerau–Levenshtein (optimal string alignment) distance, stopping early once it is past `max`. */
function distance(a, b, max) {
    if (Math.abs(a.length - b.length) > max) return max + 1;
    let prev2 = null;
    let prev = Array.from({ length: b.length + 1 }, (_, j) => j);
    for (let i = 1; i <= a.length; i++) {
        const cur = [i];
        let best = i;
        for (let j = 1; j <= b.length; j++) {
            const cost = a[i - 1] === b[j - 1] ? 0 : 1;
            cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + cost);
            if (prev2 && i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) cur[j] = Math.min(cur[j], prev2[j - 2] + 1);
            best = Math.min(best, cur[j]);
        }
        if (best > max) return max + 1;
        [prev2, prev] = [prev, cur];
    }
    return prev[b.length];
}

/** How well one typed word matches one word of the text: 3 prefix, 2 inside, 1 with typos, 0 not at all. */
function wordScore(w, t) {
    if (t.startsWith(w)) return 3;
    if (w.length >= 2 && t.includes(w)) return 2;
    const max = allowed(w);
    if (!max) return 0;
    // the typed word against the start of the text word (typing is usually a prefix), or the whole word; a 3-letter
    // word only against whole words ("wti" → Wati), else nearly every word would start close enough
    const d = Math.min(w.length > 3 ? distance(w, t.slice(0, w.length), max) : max + 1, distance(w, t, max));
    return d <= max ? 1 : 0;
}

/** A matcher for what was typed: score(text) → 0 when some word misses, else higher = better; hit(word) for highlighting. */
export function storeMatcher(query) {
    const words = tokens(query);

    return {
        empty: words.length === 0,
        score(text) {
            const ts = tokens(text);
            let total = 0;
            for (const w of words) {
                let best = 0;
                for (const t of ts) best = Math.max(best, wordScore(w, t));
                if (!best) return 0;
                total += best;
            }
            return total;
        },
        hit: (word) => words.some((w) => wordScore(w, fold(word)) > 0),
    };
}
