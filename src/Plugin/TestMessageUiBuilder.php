<?php

namespace LibreCodeCoop\LSTelegramNotify\Plugin;

final class TestMessageUiBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function buildSetting(?int $surveyId = null): array
    {
        $surveyAttribute = $surveyId === null ? '' : ' data-survey-id="' . $surveyId . '"';

        return [
            'type' => 'info',
            'label' => 'Connection test',
            'content' =>
                '<button type="button" class="btn btn-secondary ls-telegram-notify-test-message"' .
                $surveyAttribute .
                '>Send test message</button>' .
                '<span class="ms-2 ls-telegram-notify-test-message-status" aria-live="polite"></span>' .
                '<div class="form-text">Save the Telegram settings before sending a test message.</div>',
        ];
    }

    public function buildScript(string $endpointUrl): string
    {
        $encodedUrl = json_encode($endpointUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encodedUrl === false) {
            return '';
        }

        return <<<JS
$(document)
    .off('click.lsTelegramNotifyTestMessage', '.ls-telegram-notify-test-message')
    .on('click.lsTelegramNotifyTestMessage', '.ls-telegram-notify-test-message', function () {
        var button = $(this);
        var status = button.siblings('.ls-telegram-notify-test-message-status').first();
        var payload = {};
        var surveyId = button.data('survey-id');

        if (surveyId !== undefined && surveyId !== '') {
            payload.surveyId = surveyId;
        }

        var csrf = button.closest('form').find('input[name="YII_CSRF_TOKEN"]').first();

        if (csrf.length) {
            payload.YII_CSRF_TOKEN = csrf.val();
        }

        button.prop('disabled', true);
        status.removeClass('text-success text-danger').text('Sending test message...');

        $.ajax({
            url: {$encodedUrl},
            method: 'POST',
            data: payload,
            dataType: 'json'
        })
        .done(function (response) {
            if (response.success) {
                status.addClass('text-success').text(response.message || 'Test message sent successfully.');
                return;
            }

            status.addClass('text-danger').text(response.message || 'Could not send test message.');
        })
        .fail(function (xhr) {
            var message = 'Could not send test message.';

            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }

            status.addClass('text-danger').text(message);
        })
        .always(function () {
            button.prop('disabled', false);
        });
    });
JS;
    }
}
