(function () {
  const root = document.querySelector('[data-celebration-participate]');
  if (!root) {
    return;
  }

  const form = root.querySelector('[data-participate-form]');
  if (!form) {
    return;
  }

  const ratingInput = form.querySelector('[data-rating-input]');
  const ratingButtons = Array.from(form.querySelectorAll('[data-rating-option]'));
  const photoInput = form.querySelector('[data-photo-input]');
  const photoPreview = form.querySelector('[data-photo-preview]');
  const photoLabel = form.querySelector('[data-photo-label]');

  ratingButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const value = button.getAttribute('data-rating-option') || '';
      const isSelected = button.classList.contains('is-selected');

      ratingButtons.forEach((item) => {
        item.classList.remove('is-selected');
        item.setAttribute('aria-pressed', 'false');
      });

      if (isSelected) {
        if (ratingInput) {
          ratingInput.value = '';
        }
        return;
      }

      button.classList.add('is-selected');
      button.setAttribute('aria-pressed', 'true');

      if (ratingInput) {
        ratingInput.value = value;
      }
    });
  });

  if (photoInput && photoPreview) {
    photoInput.addEventListener('change', () => {
      const file = photoInput.files && photoInput.files[0];
      if (!file) {
        photoPreview.hidden = true;
        photoPreview.removeAttribute('src');
        if (photoLabel) {
          photoLabel.textContent = photoLabel.getAttribute('data-default-label') || 'Add a photo';
        }
        return;
      }

      const url = URL.createObjectURL(file);
      photoPreview.src = url;
      photoPreview.hidden = false;
      if (photoLabel) {
        if (!photoLabel.getAttribute('data-default-label')) {
          photoLabel.setAttribute('data-default-label', photoLabel.textContent || '');
        }
        photoLabel.textContent = file.name;
      }
    });
  }
})();
