<?php

namespace hiapi\console;

use hiapi\bus\ApiCommandsBusInterface;
use hiapi\commands\BaseCommand;
use hiqdev\yii2\autobus\components\AutoBusFactoryInterface;
use hiqdev\yii2\autobus\components\AutoBusInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use yii\base\Module;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Class QueueController
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 */
class QueueController extends \yii\console\Controller
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var AMQPStreamConnection
     */
    private $amqp;
    /**
     * @var BusFactoryInterface
     */
    private $busFactory;

    public function __construct(
        $id,
        Module $module,
        AMQPStreamConnection $amqp,
        LoggerInterface $logger,
        AutoBusFactoryInterface $busFactory,
        array $config = []
    ) {
        parent::__construct($id, $module, $config);

        $this->logger = $logger;
        $this->amqp = $amqp;
        $this->busFactory = $busFactory;
    }

    /**
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    private function createChannel(string $queue): AMQPChannel
    {
        $channel = $this->amqp->channel();
        $channel->queue_declare($queue, false, true, false, false);

        return $channel;
    }

    /**
     * @param int $messagesCount
     * @return int
     */
    public function actionConsume(string $queue, $messagesCount = 100)
    {
        $channel = $this->createChannel($queue);

        Console::output(' [*] Waiting for messages. To exit press CTRL+C');

        $callback = function (AMQPMessage $msg) use (&$messagesCount, $channel) {
            Console::output(' [x] Received ' . $msg->body);
            $channel->basic_ack($msg->delivery_info['delivery_tag']);
            $messagesCount--;

            try {
                $bus = $this->busFactory->get($queue);
                $this->handle($queue, $msg);
            } catch (\Error $e) {
                $this->logger->error('Failed to handle message', ['message' => $msg, 'exception' => $e]);
            }
        };

        $channel->basic_qos(null, 1, null);
        $channel->basic_consume($queue, '', false, false, false, false, $callback);

        while ($channel->callbacks && $messagesCount > 0) {
            $channel->wait();
        }

        Console::output(' [x] Reached consumed messages limit. Stopping process.');

        return ExitCode::OK;
    }

    /**
     * Decodes AMQP message and sends it to the handler
     * // TODO: move to separate class
     *
     * @param AMQPMessage $msg
     */
    protected function handle(AutoBusInterface $bus, AMQPMessage $msg)
    {
        if ($msg->get_properties()['content_type'] !== 'application/json') {
            throw new \RuntimeException('Do not know how to decode ' . $msg->getContentEncoding());
        }

        $body = json_decode($msg->getBody(), true);
        if (!isset($body['name'])) {
            $this->logger->error('Message is not supported', ['message' => $body]);
        }

        try {
            $bus->runCommand($body['name'], $body);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load message to command', ['message' => $body, 'exception' => $e]);
        }
    }
}