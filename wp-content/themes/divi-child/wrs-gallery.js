jQuery(document).ready(function($){

    const galleryInput = $('#wrs_story_gallery');
    const previewBox = $('#wrs_gallery_preview');

    function renderGallery() {

        previewBox.html('');

        const ids = galleryInput.val();

        if (!ids) return;

        ids.split(',').forEach(function(id){

            const image = wp.media.attachment(id);

            image.fetch().then(function(){

                previewBox.append(
                    '<div class="wrs-thumb">' +
                    '<img src="' + image.attributes.sizes.thumbnail.url + '">' +
                    '</div>'
                );

            });

        });
    }

    renderGallery();

    $('#wrs_upload_gallery').on('click', function(e){

        e.preventDefault();

        const frame = wp.media({
            title: 'Select Story Images',
            button: {
                text: 'Use Images'
            },
            multiple: true
        });

        frame.on('select', function(){

            const attachments = frame.state().get('selection').toJSON();

            const ids = attachments.map(item => item.id);

            galleryInput.val(ids.join(','));

            renderGallery();

        });

        frame.open();

    });

    $('#wrs_clear_gallery').on('click', function(){

        galleryInput.val('');
        previewBox.html('');

    });

});