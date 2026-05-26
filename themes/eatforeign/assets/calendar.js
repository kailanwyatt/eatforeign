(function () {
  var pin = document.getElementById('ef-calendar-pin');

  if (!pin) {
    return;
  }

  var chips = document.querySelectorAll('.ef-calendar-chip[data-chip-preview]');
  var link = pin.querySelector('[data-pin-link]');
  var image = pin.querySelector('[data-pin-image]');
  var label = pin.querySelector('[data-pin-label]');
  var flag = pin.querySelector('[data-pin-flag]');
  var title = pin.querySelector('[data-pin-title]');
  var subtitle = pin.querySelector('[data-pin-subtitle]');
  var copy = pin.querySelector('[data-pin-copy]');
  var activeChip = null;
  var hideTimer = null;

  function parsePreview(chip) {
    try {
      return JSON.parse(chip.getAttribute('data-chip-preview') || '{}');
    } catch (error) {
      return {};
    }
  }

  function setText(el, value) {
    if (!el) {
      return;
    }

    var text = value || '';

    if (text === '') {
      el.textContent = '';
      el.hidden = true;
      return;
    }

    el.textContent = text;
    el.hidden = false;
  }

  function fillPin(data) {
    if (link) {
      link.href = data.href || '#';
    }

    if (image) {
      image.src = data.image || '';
      image.alt = data.title || '';
    }

    setText(label, data.label || '');
    setText(flag, data.flag || '');
    setText(title, data.title || '');
    setText(subtitle, data.subtitle || '');
    setText(copy, data.copy || '');
  }

  function positionPin(chip) {
    var rect = chip.getBoundingClientRect();
    var gap = 10;
    var placeBelow = rect.top < 220;

    pin.style.left = rect.left + rect.width / 2 + 'px';

    if (placeBelow) {
      pin.style.top = rect.bottom + gap + 'px';
      pin.classList.add('is-below');
      pin.classList.remove('is-above');
    } else {
      pin.style.top = rect.top - gap + 'px';
      pin.classList.add('is-above');
      pin.classList.remove('is-below');
    }
  }

  function showPin(chip) {
    activeChip = chip;
    fillPin(parsePreview(chip));
    positionPin(chip);
    pin.hidden = false;
    pin.setAttribute('aria-hidden', 'false');
    pin.classList.add('is-visible');
  }

  function hidePin() {
    activeChip = null;
    pin.classList.remove('is-visible');
    pin.hidden = true;
    pin.setAttribute('aria-hidden', 'true');
  }

  function scheduleHide() {
    clearTimeout(hideTimer);
    hideTimer = window.setTimeout(hidePin, 140);
  }

  function cancelHide() {
    clearTimeout(hideTimer);
  }

  chips.forEach(function (chip) {
    chip.addEventListener('mouseenter', function () {
      cancelHide();
      showPin(chip);
    });

    chip.addEventListener('mouseleave', scheduleHide);

    chip.addEventListener('focus', function () {
      cancelHide();
      showPin(chip);
    });

    chip.addEventListener('blur', scheduleHide);
  });

  pin.addEventListener('mouseenter', cancelHide);
  pin.addEventListener('mouseleave', scheduleHide);

  window.addEventListener(
    'scroll',
    function () {
      if (activeChip) {
        positionPin(activeChip);
      }
    },
    true
  );

  window.addEventListener('resize', function () {
    if (activeChip) {
      positionPin(activeChip);
    }
  });
})();
