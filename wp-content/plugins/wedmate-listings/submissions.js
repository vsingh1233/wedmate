document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.wml-owner-form');
    if (!form) return;
    const category = document.getElementById('wml-input-category');
    const venue = document.getElementById('wml-venue-fields');
    function updateVenue() {
        venue.hidden = category.value !== 'wedding-venues';
        venue.querySelectorAll('input, textarea').forEach(input => { input.disabled = venue.hidden; });
    }
    category.addEventListener('change', updateVenue);
    updateVenue();
    const photos = document.getElementById('wml-photos');
    const feedback = document.getElementById('wml-photo-feedback');
    const removePhotos = document.getElementById('wml-remove-photos');
    function validatePhotos() {
        const files = Array.from(photos.files);
        const invalid = files.length > 3 || files.some(file => file.size > Number(photos.dataset.maxBytes) || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
        const message = invalid ? 'Choose up to three JPG, PNG or WebP photos within the size limit.' : '';
        photos.setCustomValidity(message);
        feedback.textContent = message || files.map(file => file.name).join(', ');
        removePhotos.hidden = files.length === 0;
    }
    photos.addEventListener('change', validatePhotos);
    photos.addEventListener('input', validatePhotos);
    removePhotos.addEventListener('click', function () {
        photos.value = '';
        validatePhotos();
        photos.focus();
    });
    validatePhotos();
    const error = document.querySelector('.wml-form-errors');
    if (error) error.focus();
    form.addEventListener('submit', function () {
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Submitting…';
    });
    window.addEventListener('pageshow', function () {
        validatePhotos();
        const button = form.querySelector('button[type="submit"]');
        button.disabled = false;
        button.textContent = 'Submit for review';
    });
});
