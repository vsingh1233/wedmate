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
    const error = document.querySelector('.wml-form-errors');
    if (error) error.focus();
    form.addEventListener('submit', function () {
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Submitting…';
    });
    window.addEventListener('pageshow', function () {
        const button = form.querySelector('button[type="submit"]');
        button.disabled = false;
        button.textContent = 'Submit for review';
    });
});
