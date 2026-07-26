(() => {
    const tag = window.__GRAV_PAGE_TAG;
    const validTag = typeof tag === 'string'
        && /^[a-z][.0-9_a-z]*-[\-.0-9_a-z]*$/.test(tag);

    if (!validTag || customElements.get(tag)) {
        return;
    }

    customElements.define(tag, class extends HTMLElement {
        connectedCallback() {
            this.textContent = 'Goosialize Leads Phase 2 skeleton. No lead functionality is enabled.';
        }
    });
})();
