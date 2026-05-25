(function () {
  const root = document.querySelector('[data-passport-wizard]');

  if (!root) {
    return;
  }

  const form = root.querySelector('[data-wizard-form]');

  if ( ! form ) {
    return;
  }

  const steps = Array.from(root.querySelectorAll('[data-wizard-step]'));
  const panels = Array.from(form.querySelectorAll('[data-wizard-panel]'));
  const progressFill = root.querySelector('[data-wizard-progress]');
  const backButton = form.querySelector('[data-wizard-back]');
  const nextButton = form.querySelector('[data-wizard-next]');
  const submitButton = form.querySelector('[data-wizard-submit]');
  const ratingInput = root.querySelector('[data-rating-input]');
  const ratingButtons = Array.from(root.querySelectorAll('[data-rating-option]'));
  const photoList = root.querySelector('[data-photo-list]');
  const photoTemplate = root.querySelector('[data-photo-template]');
  const addPhotoButton = root.querySelector('[data-add-photo]');
  const maxPhotos = Number(root.getAttribute('data-max-photos') || '6');
  const existingCount = Number(root.getAttribute('data-existing-count') || '0');
  let currentStep = 1;
  const totalSteps = panels.length;

  function setStep(step) {
    currentStep = Math.max(1, Math.min(totalSteps, step));

    panels.forEach((panel) => {
      const active = Number(panel.getAttribute('data-wizard-panel')) === currentStep;
      panel.hidden = !active;
      panel.classList.toggle('is-active', active);
    });

    steps.forEach((item) => {
      const index = Number(item.getAttribute('data-wizard-step'));
      item.classList.toggle('is-active', index === currentStep);
      item.classList.toggle('is-complete', index < currentStep);
    });

    if (progressFill) {
      const percent = totalSteps <= 1 ? 100 : ((currentStep - 1) / (totalSteps - 1)) * 100;
      progressFill.style.width = `${percent}%`;
    }

    const isLast = currentStep === totalSteps;
    toggleNavButton( backButton, currentStep > 1 );
    toggleNavButton( nextButton, ! isLast );
    toggleNavButton( submitButton, isLast );
  }

  function toggleNavButton( button, visible ) {
    if ( ! button ) {
      return;
    }

    button.hidden = ! visible;
    button.classList.toggle( 'ef-wizard-btn--hidden', ! visible );
  }

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

  function bindPhotoPreview(input) {
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      const card = input.closest('[data-photo-card]');
      const preview = card ? card.querySelector('[data-photo-preview]') : null;

      if (!file || !preview) {
        return;
      }

      preview.src = URL.createObjectURL(file);
      preview.hidden = false;
      card.classList.add('has-file');
    });
  }

  function countPhotoSlots() {
    const newSlots = photoList ? photoList.querySelectorAll('[data-photo-card]').length : 0;
    return existingCount + newSlots;
  }

  function addPhotoSlot() {
    if (!photoList || !photoTemplate || countPhotoSlots() >= maxPhotos) {
      return;
    }

    const clone = photoTemplate.content.cloneNode(true);
    const input = clone.querySelector('input[type="file"]');

    if (input) {
      bindPhotoPreview(input);
    }

    photoList.appendChild(clone);
    updateAddPhotoState();
  }

  function updateAddPhotoState() {
    if (!addPhotoButton) {
      return;
    }

    addPhotoButton.disabled = countPhotoSlots() >= maxPhotos;
  }

  root.querySelectorAll('input[type="file"][name="passport_images[]"]').forEach(bindPhotoPreview);

  if (addPhotoButton) {
    addPhotoButton.addEventListener('click', addPhotoSlot);
    updateAddPhotoState();
  }

  if (backButton) {
    backButton.addEventListener('click', () => setStep(currentStep - 1));
  }

  if (nextButton) {
    nextButton.addEventListener('click', () => setStep(currentStep + 1));
  }

  root.querySelectorAll('[data-jump-step]').forEach((button) => {
    button.addEventListener('click', () => {
      const step = Number(button.getAttribute('data-jump-step') || '1');
      setStep(step);
    });
  });

  const copyButton = root.querySelector('[data-copy-share]');
  const shareText = root.getAttribute('data-share-text') || '';

  if (copyButton) {
    copyButton.addEventListener('click', async () => {
      const url = copyButton.getAttribute('data-share-url') || window.location.href;
      const text = `${shareText} ${url}`.trim();

      try {
        await navigator.clipboard.writeText(text);
        copyButton.textContent = copyButton.getAttribute('data-copied-label') || 'Copied!';
        window.setTimeout(() => {
          copyButton.textContent = copyButton.getAttribute('data-default-label') || 'Copy link';
        }, 2000);
      } catch {
        window.prompt('Copy this link:', text);
      }
    });
  }

  setStep(1);
})();
