<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 */
class DoctrineSender implements SenderInterface
{
    private Connection $connection;
    private SerializerInterface $serializer;

    public function __construct(Connection $connection, ?SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);

        $message = $envelope->getMessage();
        $uniqueKey = $message instanceof UniqueMessage
            ? $message->getUniqueKey()
            : null;

        /** @var DelayStamp|null $delayStamp */
        $delayStamp = $envelope->last(DelayStamp::class);

        $delay = null !== $delayStamp
            ? $delayStamp->getDelay()
            : 0;

        $body = $encodedMessage['body'];
        $header = $encodedMessage['headers'];

        try {
            try {
                $id = $this->connection->send($body, $header, $delay, [
                    "unique_key" => $uniqueKey,
                ]);
            } catch (ShouldWaitException) {
                if ($message instanceof UniqueWaitingMessage) {
                    $id = $this->connection->send($body, $header, $delay, [
                        "unique_key" => $uniqueKey . "_" . $message->getWaitingTimes(),
                        "in_waiting_queue" => true,
                    ]);
                }
            }
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $id
            ? $envelope->with(new TransportMessageIdStamp($id))
            : $envelope;
    }
}
