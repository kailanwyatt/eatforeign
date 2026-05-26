(function () {
  const root = document.querySelector('[data-countries-filter]');

  if (!root) {
    return;
  }

  const input = root.querySelector('[data-countries-search]');
  const grid = document.querySelector('[data-countries-grid]');
  const cards = grid ? Array.from(grid.querySelectorAll('[data-country-card]')) : [];
  const countEl = root.querySelector('[data-countries-count]');
  const emptyEl = root.querySelector('[data-countries-empty]');
  const total = cards.length;
  const i18n = window.efCountriesFilter && window.efCountriesFilter.i18n ? window.efCountriesFilter.i18n : {};

  function formatMessage(template, values) {
    if (!template) {
      return '';
    }

    return template.replace(/\{(\w+)\}/g, function (_match, key) {
      return Object.prototype.hasOwnProperty.call(values, key) ? String(values[key]) : '';
    });
  }

  function updateCount(visible, query) {
    if (!countEl) {
      return;
    }

    if (query === '') {
      countEl.textContent = formatMessage(i18n.all || 'Showing all {total} countries', { total: total });
      return;
    }

    countEl.textContent = formatMessage(i18n.match || '{visible} of {total} countries match "{query}"', {
      visible: visible,
      total: total,
      query: query,
    });
  }

  function filterCountries() {
    const query = input ? input.value.trim().toLowerCase() : '';
    let visible = 0;

    cards.forEach(function (card) {
      const haystack = (card.getAttribute('data-search') || '').toLowerCase();
      const matches = query === '' || haystack.indexOf(query) !== -1;

      card.hidden = !matches;

      if (matches) {
        visible += 1;
      }
    });

    updateCount(visible, query);

    if (emptyEl) {
      emptyEl.hidden = visible > 0;
    }

    if (grid) {
      grid.classList.toggle('is-empty', visible === 0 && query !== '');
    }
  }

  if (input) {
    input.addEventListener('input', filterCountries);
  }

  filterCountries();
})();
