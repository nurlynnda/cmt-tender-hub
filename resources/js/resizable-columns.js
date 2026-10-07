// Drag a column's right edge to resize it; widths are remembered per table on this computer.
// Double-click an edge to go back to normal widths. Works across Livewire re-renders.
const PREFIX = 'tenderhub-cols:';
const MIN = 60;

const read = (key) => { try { return JSON.parse(localStorage.getItem(PREFIX + key) || 'null'); } catch { return null; } };
const write = (key, widths) => {
    try { widths ? localStorage.setItem(PREFIX + key, JSON.stringify(widths)) : localStorage.removeItem(PREFIX + key); } catch { /* storage off: resize still works for this visit */ }
};
const headers = (table) => Array.from(table.querySelectorAll('thead th'));

function fix(table, widths) {
    headers(table).forEach((th, i) => { th.style.width = widths[i] + 'px'; });
    table.style.tableLayout = 'fixed';
    table.style.width = widths.reduce((a, b) => a + b, 0) + 'px';
    table.style.minWidth = '0';
}

function unfix(table) {
    headers(table).forEach((th) => { th.style.width = ''; });
    table.style.tableLayout = table.style.width = table.style.minWidth = '';
}

function startDrag(event, table, index) {
    event.preventDefault();
    event.stopPropagation();
    const widths = headers(table).map((th) => th.getBoundingClientRect().width);
    const startX = event.clientX;
    const startWidth = widths[index];
    let moved = false; // a plain click on the edge must not freeze the columns
    const move = (e) => {
        if (!moved && Math.abs(e.clientX - startX) < 3) return;
        moved = true;
        widths[index] = Math.max(MIN, startWidth + e.clientX - startX);
        fix(table, widths);
    };
    const up = () => {
        document.removeEventListener('pointermove', move);
        document.removeEventListener('pointerup', up);
        if (moved) write(table.dataset.resizable, widths.map(Math.round));
    };
    document.addEventListener('pointermove', move);
    document.addEventListener('pointerup', up);
}

function setUp(table) {
    const key = table.dataset.resizable;
    const ths = headers(table);
    const saved = read(key);
    if (saved && saved.length === ths.length) fix(table, saved);
    else if (saved) write(key, null); // the table changed shape: forget the old widths

    ths.forEach((th, i) => {
        if (th.querySelector('[data-col-handle]')) return;
        th.style.position = 'relative';
        const handle = document.createElement('span');
        handle.dataset.colHandle = '';
        handle.title = 'Drag to resize · double-click to reset';
        handle.style.cssText = 'position:absolute;top:0;bottom:0;right:-4px;width:8px;cursor:col-resize;z-index:5;touch-action:none';
        handle.addEventListener('pointerdown', (e) => startDrag(e, table, i));
        handle.addEventListener('click', (e) => e.stopPropagation()); // never trigger the header's sort
        handle.addEventListener('dblclick', (e) => { e.stopPropagation(); write(key, null); unfix(table); });
        th.appendChild(handle);
    });
}

let queued = false;
function setUpAll() {
    queued = false;
    document.querySelectorAll('table[data-resizable]').forEach(setUp);
}
function queue() {
    if (!queued) { queued = true; requestAnimationFrame(setUpAll); }
}

// Livewire re-renders strip the handles and inline widths; put them back whenever the page changes.
new MutationObserver(queue).observe(document.documentElement, { childList: true, subtree: true });
document.addEventListener('DOMContentLoaded', setUpAll);
