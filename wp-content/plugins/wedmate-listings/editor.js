jQuery(function ($) {
    function groups() {
        $('.wml-field-group').each(function () {
            const term = $(this).data('term');
            $(this).toggle(!term || $('#in-vendor_category-' + term).prop('checked') || $('#in-popular-vendor_category-' + term).prop('checked'));
        });
    }
    // Classic editor taxonomy controls; keep all panels accessible in the block editor.
    if ($('#vendor_categorychecklist').length) {
        $(document).on('change', '#vendor_categorychecklist input, #vendor_categorychecklist-pop input', groups);
        groups();
    }
    $('.wml-gallery').on('click', function () {
        const input = $('#' + $(this).data('target'));
        const frame = wp.media({title: 'Listing gallery', library: {type: 'image'}, multiple: true});
        frame.on('open', function () {
            input.val().split(',').filter(Boolean).forEach(id => frame.state().get('selection').add(wp.media.attachment(id)));
        });
        frame.on('select', function () { input.val(frame.state().get('selection').map(a => a.id).join(',')); });
        frame.open();
    });
});
