// Admin on the phone: every admin data table with a header row gets each
// cell labelled (data-label = its column header) and is marked data-stacked, so phone.css can lay rows out as
// cards. Kept in sync with Livewire re-renders by a MutationObserver; costs nothing on the desktop.
function headerLabels(table) {
    const row = table.tHead?.rows[0];
    if (!row) return null;
    const labels = [];
    for (const th of row.cells) {
        const text = th.textContent.trim();
        for (let i = 0; i < (th.colSpan || 1); i++) labels.push(text);
    }
    return labels;
}

function label(table) {
    const labels = headerLabels(table);
    if (!labels) return;
    if (!table.hasAttribute('data-stacked')) table.setAttribute('data-stacked', '');
    for (const section of [...table.tBodies, table.tFoot].filter(Boolean)) {
        for (const row of section.rows) {
            let col = 0;
            for (const cell of row.cells) {
                const text = labels[col] ?? '';
                if (cell.getAttribute('data-label') !== text) cell.setAttribute('data-label', text);
                col += cell.colSpan || 1;
            }
        }
    }
}

function labelAll(root = document) {
    root.querySelectorAll('.adm-content table').forEach(label);
}

let queued = false;
function schedule() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => { queued = false; labelAll(); });
}

document.addEventListener('DOMContentLoaded', () => {
    labelAll();
    const content = document.querySelector('.adm-content');
    if (content) new MutationObserver(schedule).observe(content, { childList: true, subtree: true });
});
