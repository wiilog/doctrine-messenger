<?php

namespace Symfony\Component\Messenger\Bridge\Doctrine\Transport;

abstract class UniqueWaitingMessage implements UniqueMessage {
    private int $waitingTimes = 0;

    public function getWaitingTimes(): int {
        return $this->waitingTimes;
    }

    public function incrementWaitingTimes(): void {
        $this->waitingTimes++;
    }

}