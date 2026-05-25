(function () {
  var preview = document.getElementById('calendar-celebration-preview');

  if (!preview) {
    return;
  }

  if (window.location.search.indexOf('c=') !== -1) {
    preview.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
})();
