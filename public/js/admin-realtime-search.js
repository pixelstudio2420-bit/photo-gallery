/**
 * Admin Real-Time Search Component
 * ================================
 * Intercepts filter forms and performs AJAX fetch instead of full page reload.
 * Debounces text inputs, instant trigger for selects/dates.
 *
 * Usage: Wrap a <form> with x-data="adminFilter()" on a parent element.
 *   - The form must have an action URL (or defaults to current page)
 *   - Mark the table container with id="admin-table-area"
 *   - Mark the pagination container with id="admin-pagination-area"
 *   - Text inputs trigger after 300ms debounce
 *   - Selects and date inputs trigger immediately
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('adminFilter', () => ({
    loading: false,
    _debounce: null,
    resultCount: null,

    init() {
      const form = this.$el.querySelector('form') || this.$el.closest('form');
      if (!form) return;

      // Intercept form submit
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        this.doSearch();
      });

      // Auto-bind all filter inputs
      form.querySelectorAll('input, select').forEach(el => {
        if (el.type === 'text' || el.type === 'search') {
          el.addEventListener('input', () => this.debouncedSearch(300));
        } else {
          el.addEventListener('change', () => this.debouncedSearch(50));
        }
      });
    },

    debouncedSearch(delay) {
      clearTimeout(this._debounce);
      this._debounce = setTimeout(() => this.doSearch(), delay);
    },

    async doSearch() {
      const form = this.$el.querySelector('form') || this.$el.closest('form');
      if (!form) return;

      this.loading = true;

      const formData = new FormData(form);
      const params = new URLSearchParams();
      formData.forEach((v, k) => { if (v !== '') params.set(k, v); });

      const baseUrl = form.getAttribute('action') || window.location.pathname;
      const url = baseUrl + (params.toString() ? '?' + params.toString() : '');

      try {
        const res = await fetch(url, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const html = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // Swap table area
        const newTable = doc.querySelector('#admin-table-area');
        const oldTable = document.querySelector('#admin-table-area');
        if (newTable && oldTable) {
          oldTable.innerHTML = newTable.innerHTML;
          oldTable.style.opacity = '0';
          requestAnimationFrame(() => {
            oldTable.style.transition = 'opacity 0.2s ease';
            oldTable.style.opacity = '1';
          });
        }

        // Swap pagination
        const newPag = doc.querySelector('#admin-pagination-area');
        const oldPag = document.querySelector('#admin-pagination-area');
        if (oldPag) {
          oldPag.innerHTML = newPag ? newPag.innerHTML : '';
        }

        // Update result count badge
        const newCount = doc.querySelector('[data-result-count]');
        if (newCount) {
          this.resultCount = newCount.getAttribute('data-result-count');
          const oldCount = document.querySelector('[data-result-count]');
          if (oldCount) oldCount.setAttribute('data-result-count', this.resultCount);
        }

        // Update URL without reload
        window.history.replaceState({}, '', url);

      } catch (e) {
        console.error('Admin search error:', e);
        // Fallback: submit form normally
        form.submit();
      }

      this.loading = false;
    },

    clearFilters() {
      const form = this.$el.querySelector('form') || this.$el.closest('form');
      if (!form) return;
      form.querySelectorAll('input, select').forEach(el => {
        if (el.type === 'text' || el.type === 'search' || el.type === 'date') el.value = '';
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
      });
      this.doSearch();
    }
  }));
});
