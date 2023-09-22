<?php

class Telegram
{
    public function __construct(
        private string $authToken
    ) {
    }

    public function sendDocument(array $params): void
    {
        $postData = [
            'chat_id' => $params['chat_id'],
            'document' => new CURLFile($params['document']->file, '', $params['document']->filename),
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot' . $this->authToken . '/sendDocument');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
        curl_close($ch);
    }

    public function sendMessage(array $params): void
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot' . $this->authToken . '/sendMessage');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
        curl_close($ch);
    }
}
