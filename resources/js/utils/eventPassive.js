// Helper to add event listeners with passive option when supported
export function supportsPassive() {
    let sup = false;
    try {
        const opts = Object.defineProperty({}, 'passive', {
            get() { sup = true; }
        });
        window.addEventListener('testPassive', null, opts);
        window.removeEventListener('testPassive', null, opts);
    } catch (e) { }
    return sup;
}

export function addListener(el, event, handler, options = { passive: true }) {
    const sup = supportsPassive();
    const useOpts = sup ? options : false;
    el.addEventListener(event, handler, useOpts);
}
