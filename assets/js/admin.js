(function($){
    const settings = window.indoortechCategoryPromotions || {};
    const form = $('#indoortech-category-promotions-form');
    const progressBar = $('#itcp-progress');
    const progressInner = progressBar.find('.itcp-progress-bar');
    const progressText = progressBar.find('.itcp-progress-text');
    const statusText = $('#itcp-status');
    const messageBox = $('#itcp-message');
    const actionRadios = $('input[name="itcp-action"]');
    const discountInput = $('#itcp-discount');
    const startInput = $('#itcp-start-date');
    const endInput = $('#itcp-end-date');

    function setLoading(isLoading){
        form.find('input, select, button').prop('disabled', isLoading);
        if(isLoading){
            statusText.text(settings.i18n.processing).show();
            messageBox.empty();
        }
    }

    function updateProgress(processed, total, percentage){
        progressBar.show();
        progressInner.css('width', percentage + '%').attr('aria-valuenow', percentage);
        progressText.text(percentage + '%');
        statusText.text(processed + ' / ' + total + '');
    }

    function escapeHtml(value){
        return $('<div>').text(value).html();
    }

    function showMessage(type, text, debug){
        const cssClass = type === 'success' ? 'notice notice-success' : 'notice notice-error';
        let content = '<div class="' + cssClass + '"><p>' + escapeHtml(text) + '</p>';

        if(debug){
            content += '<pre class="itcp-debug">' + escapeHtml(debug) + '</pre>';
        }

        content += '</div>';
        messageBox.html(content);
    }

    function toggleActionFields(action){
        const isRemove = action === 'remove';

        discountInput.prop('disabled', isRemove).prop('required', !isRemove);
        startInput.prop('disabled', isRemove).prop('required', !isRemove);
        endInput.prop('disabled', isRemove).prop('required', !isRemove);

        if(isRemove){
            discountInput.val('');
            startInput.val('');
            endInput.val('');
        }
    }

    function processBatch(){
        $.post(settings.ajax_url, {
            action: 'itcp_process_batch',
            nonce: settings.nonce
        }).done(function(response){
            if(!response.success){
                setLoading(false);
                showMessage('error', response.data && response.data.message ? response.data.message : settings.i18n.error, response.data && response.data.debug ? response.data.debug : '');
                return;
            }

            const data = response.data;
            updateProgress(data.processed, data.total, data.percentage);

            if(data.complete){
                setLoading(false);
                statusText.hide();
                showMessage('success', settings.i18n.success);
            } else {
                setTimeout(processBatch, 500);
            }
        }).fail(function(){
            setLoading(false);
            showMessage('error', settings.i18n.error);
        });
    }

    form.on('submit', function(e){
        e.preventDefault();
        setLoading(true);

        $.post(settings.ajax_url, {
            action: 'itcp_start_promotion',
            nonce: settings.nonce,
            categories: $('#itcp-categories').val(),
            discount: $('#itcp-discount').val(),
            start_date: $('#itcp-start-date').val(),
            end_date: $('#itcp-end-date').val(),
            action_type: $('input[name="itcp-action"]:checked').val()
        }).done(function(response){
            if(!response.success){
                setLoading(false);
                showMessage(
                    'error',
                    response.data && response.data.message ? response.data.message : settings.i18n.error,
                    response.data && response.data.debug ? response.data.debug : ''
                );
                return;
            }
            updateProgress(0, response.data.total, 0);
            processBatch();
        }).fail(function(){
            setLoading(false);
            showMessage('error', settings.i18n.error);
        });
    });

    actionRadios.on('change', function(){
        toggleActionFields($(this).val());
    });

    toggleActionFields(actionRadios.filter(':checked').val());
})(jQuery);
