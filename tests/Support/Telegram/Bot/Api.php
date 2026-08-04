<?php

namespace Telegram\Bot;

class Api
{
    /** @var callable */
    private $sendMessageHandler;

    public function __construct(callable $sendMessageHandler)
    {
        $this->sendMessageHandler = $sendMessageHandler;
    }

    public function sendMessage(array $params): array
    {
        return ($this->sendMessageHandler)($params);
    }
}
