<?php declare(strict_types=1);

namespace Kcs\MessengerExtra\Transport\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Kcs\MessengerExtra\Message\DelayedMessageInterface;
use Kcs\MessengerExtra\Message\PriorityAwareMessageInterface;
use Kcs\MessengerExtra\Message\TTLAwareMessageInterface;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Serializer Messenger sender to send messages through DBAL connection.
 *
 * @author Alessandro Chitolina <alekitto@gmail.com>
 */
class DbalSender implements SenderInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection, string $tableName, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
        $this->serializer = $serializer ?? Serializer::create();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        $message = $envelope->getMessage();
        $encodedMessage = $this->serializer->encode($envelope);

        $values = [
            'id' => Uuid::uuid1(),
            'published_at' => new \DateTimeImmutable(),
            'body' => $encodedMessage['body'],
            'headers' => $encodedMessage['headers'],
            'properties' => [],
            'priority' => 0,
            'time_to_live' => null,
            'delayed_until' => null,
        ];

        if ($message instanceof TTLAwareMessageInterface) {
            $values['time_to_live'] = $message->getTtl();
        }

        if ($message instanceof DelayedMessageInterface) {
            $timestamp = \microtime(true) + ($message->getDelay() / 1000);
            $values['delayed_until'] = \DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6f', $timestamp));
        }

        if ($message instanceof PriorityAwareMessageInterface) {
            $values['priority'] = $message->getPriority();
        }

        $this->connection->insert($this->tableName, $values, [
            'id' => UuidBinaryOrderedTimeType::NAME,
            'published_at' => Type::DATETIMETZ_IMMUTABLE,
            'body' => Type::TEXT,
            'headers' => Type::JSON,
            'properties' => Type::JSON,
            'priority' => Type::INTEGER,
            'time_to_live' => Type::INTEGER,
            'delayed_until' => Type::DATETIMETZ_IMMUTABLE,
        ]);

        return $envelope;
    }
}
