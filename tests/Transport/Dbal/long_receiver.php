<?php declare(strict_types=1);

$root = $_SERVER['ROOT'];
$autoload = $root.'/vendor/autoload.php';

if (! \file_exists($autoload)) {
    exit('You should run "composer install --dev" in the component before running this script.');
}

require_once $autoload;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Kcs\MessengerExtra\Transport\Dbal\DbalReceiver;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

$serializer = new Serializer(
    new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
);

if (! Type::hasType(UuidBinaryType::NAME)) {
    Type::addType(UuidBinaryType::NAME, UuidBinaryType::class);
}
if (! Type::hasType(UuidBinaryOrderedTimeType::NAME)) {
    Type::addType(UuidBinaryOrderedTimeType::NAME, UuidBinaryOrderedTimeType::class);
}

$connection = DriverManager::getConnection(['url' => \getenv('DSN')]);
$receiver = new DbalReceiver($connection, 'messenger', $serializer);

$worker = new Worker($receiver, new class() implements MessageBusInterface {
    public function dispatch($envelope): Envelope
    {
        echo 'Get envelope with message: '.\get_class($envelope->getMessage())."\n";
        echo \sprintf("with stamps: %s\n", \json_encode(\array_keys($envelope->all()), JSON_PRETTY_PRINT));

        \sleep(30);
        echo "Done.\n";

        return $envelope;
    }
});

echo "Receiving messages...\n";
$worker->run();
