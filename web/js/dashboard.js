(function ($) {
    'use strict';

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content');
    }

    function showToast(message, type) {
        var classes = type === 'error' ? 'text-bg-danger' : 'text-bg-success';
        var toastId = 'toast-' + Date.now();
        var html = '' +
            '<div id="' + toastId + '" class="toast align-items-center ' + classes + ' border-0" role="alert" aria-live="assertive" aria-atomic="true">' +
            '  <div class="d-flex">' +
            '    <div class="toast-body">' + message + '</div>' +
            '    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
            '  </div>' +
            '</div>';

        var container = $('#dashboard-toast-container');
        container.append(html);

        var toastEl = document.getElementById(toastId);
        var toast = new bootstrap.Toast(toastEl, {delay: 2500});
        toast.show();

        toastEl.addEventListener('hidden.bs.toast', function () {
            $(toastEl).remove();
        });
    }

    function reloadSection(container) {
        if ($.pjax) {
            $.pjax.reload({container: container, timeout: 5000, push: false, replace: false});
            return;
        }
        window.location.reload();
    }

    function entryPayload($form) {
        var inputType = $form.data('input-type');
        var payload = {
            bullet_id: $form.find('[name="bullet_id"]').val(),
            entry_date: $form.find('[name="entry_date"]').val(),
            note: $form.find('[name="note"]').val()
        };

        if (inputType === 'binary') {
            payload.value_int = $form.find('[name="value_int_switch"]').is(':checked') ? 1 : 0;
            return {valid: true, payload: payload};
        }

        if (inputType === 'scale' || inputType === 'stars') {
            var scaleValue = $form.find('[name="value_int"]').val();
            if (scaleValue === '') {
                return {valid: false, message: 'Selecciona un valor para guardar.'};
            }
            payload.value_int = scaleValue;
            return {valid: true, payload: payload};
        }

        if (inputType === 'numeric') {
            var numericValue = $form.find('[name="value_decimal"]').val();
            if (numericValue === '') {
                return {valid: false, message: 'Ingresa un valor numérico.'};
            }
            payload.value_decimal = numericValue;
            return {valid: true, payload: payload};
        }

        if (inputType === 'text') {
            var textValue = $form.find('[name="value_text"]').val();
            if (textValue.trim() === '') {
                return {valid: false, message: 'Ingresa un texto para guardar.'};
            }
            payload.value_text = textValue;
            return {valid: true, payload: payload};
        }

        return {valid: false, message: 'Tipo de bullet no soportado.'};
    }

    function updateEntryButtonState($form) {
        var inputType = $form.data('input-type');
        var $button = $form.find('.js-entry-save-btn');
        var enabled = true;

        if (inputType === 'scale' || inputType === 'stars') {
            enabled = $form.find('[name=\"value_int\"]').val() !== '';
        } else if (inputType === 'numeric') {
            enabled = $.trim($form.find('[name=\"value_decimal\"]').val()) !== '';
        } else if (inputType === 'text') {
            enabled = $.trim($form.find('[name=\"value_text\"]').val()) !== '';
        }

        $button.prop('disabled', !enabled);
    }

    $(document).on('change keyup', '.js-entry-form input, .js-entry-form select', function () {
        updateEntryButtonState($(this).closest('.js-entry-form'));
    });

    $(document).on('pjax:end', function () {
        $('.js-entry-form').each(function () {
            updateEntryButtonState($(this));
        });
    });

    $(document).on('submit', '.js-entry-form', function (event) {
        event.preventDefault();

        var $form = $(this);
        var parsed = entryPayload($form);
        if (!parsed.valid) {
            showToast(parsed.message, 'error');
            return;
        }

        var $button = $form.find('.js-entry-save-btn');
        $button.prop('disabled', true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: parsed.payload,
            headers: {'X-CSRF-Token': csrfToken()}
        }).done(function (response) {
            if (response.success) {
                $('#today-checkin-status').removeClass('text-warning').addClass('text-success').text('Check-in completado');
                showToast(response.message || 'Guardado', 'success');
            } else {
                showToast(response.message || 'No se pudo guardar.', 'error');
            }
        }).fail(function (xhr) {
            var message = 'Error guardando el check-in.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            showToast(message, 'error');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    $(document).on('submit', '#quick-task-form', function (event) {
        event.preventDefault();

        var $form = $(this);
        var title = $.trim($form.find('[name="title"]').val());
        if (title === '') {
            showToast('El título es obligatorio.', 'error');
            return;
        }

        var $button = $form.find('[type="submit"]');
        $button.prop('disabled', true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            headers: {'X-CSRF-Token': csrfToken()}
        }).done(function (response) {
            if (response.success) {
                showToast(response.message || 'Tarea creada.', 'success');
                var modalEl = document.getElementById('quickTaskModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) {
                    modal.hide();
                }
                $form[0].reset();
                reloadSection('#tasks-pjax');
            } else {
                showToast(response.message || 'No se pudo crear la tarea.', 'error');
            }
        }).fail(function (xhr) {
            var message = 'Error creando tarea.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            showToast(message, 'error');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    $(document).on('change', '.js-task-status', function () {
        var $select = $(this);
        var taskId = $select.data('task-id');
        var status = $select.val();
        var updateUrl = $select.data('update-url') || ('/task/quick-update?id=' + taskId);

        $select.prop('disabled', true);

        $.ajax({
            url: updateUrl,
            method: 'PATCH',
            contentType: 'application/json',
            data: JSON.stringify({status: status}),
            headers: {'X-CSRF-Token': csrfToken()}
        }).done(function (response) {
            if (response.success) {
                showToast(response.message || 'Estado actualizado.', 'success');
                reloadSection('#tasks-pjax');
            } else {
                showToast(response.message || 'No se pudo actualizar estado.', 'error');
            }
        }).fail(function (xhr) {
            var message = 'Error actualizando estado.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            showToast(message, 'error');
        }).always(function () {
            $select.prop('disabled', false);
        });
    });

    $(document).on('submit', '#daily-reminder-form', function (event) {
        event.preventDefault();

        var $form = $(this);
        var time = $.trim($form.find('[name="time"]').val());
        if (time === '') {
            showToast('Selecciona una hora para el recordatorio.', 'error');
            return;
        }

        var $button = $form.find('[type="submit"]');
        $button.prop('disabled', true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            headers: {'X-CSRF-Token': csrfToken()}
        }).done(function (response) {
            if (response.success) {
                showToast(response.message || 'Recordatorio actualizado.', 'success');
                reloadSection('#reminders-pjax');
            } else {
                showToast(response.message || 'No se pudo guardar el recordatorio.', 'error');
            }
        }).fail(function (xhr) {
            var message = 'Error guardando recordatorio.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            showToast(message, 'error');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    $(function () {
        $('.js-entry-form').each(function () {
            updateEntryButtonState($(this));
        });
    });
})(jQuery);
