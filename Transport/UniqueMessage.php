<?php

namespace Symfony\Component\Messenger\Bridge\Doctrine\Transport;

interface UniqueMessage {
    public function getUniqueKey();
}