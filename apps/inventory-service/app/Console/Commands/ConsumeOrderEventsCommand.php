<?php

namespace App\Console\Commands;

use App\Actions\Orders\HandleOrderCreatedAction;
use App\Services\Messaging\RabbitMqConnectionFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class ConsumeOrderEventsCommand extends Command
{
    protected $signature = 'inventory:consume-order-events';

    protected $description = 'Consume order integration events from RabbitMQ and finalize inventory state.';

    public function handle(
        RabbitMqConnectionFactory $rabbitMqConnectionFactory,
        HandleOrderCreatedAction $handleOrderCreatedAction,
    ): int {
        $connection = $rabbitMqConnectionFactory->make();
        $channel = $connection->channel();
        $queue = (string) config('services.rabbitmq.order_events_queue', 'orders');

        $channel->queue_declare(
            queue: $queue,
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
        );

        $channel->basic_qos(0, 1, false);

        $channel->basic_consume(
            queue: $queue,
            consumer_tag: '',
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: function (AMQPMessage $message) use ($handleOrderCreatedAction): void {
                $payload = json_decode($message->getBody(), true);

                if (! is_array($payload)) {
                    Log::warning('Ignoring non-JSON RabbitMQ message on orders queue.', [
                        'body' => $message->getBody(),
                    ]);
                    $message->ack();

                    return;
                }

                if (array_key_exists('ts_ms', $payload) && ! array_key_exists('event_type', $payload)) {
                    $message->ack();

                    return;
                }

                if (($payload['event_type'] ?? null) !== 'order.created') {
                    Log::warning('Ignoring unsupported RabbitMQ event on orders queue.', [
                        'payload' => $payload,
                    ]);
                    $message->ack();

                    return;
                }

                try {
                    $handleOrderCreatedAction($payload);
                    $message->ack();
                } catch (Throwable $exception) {
                    Log::error('Failed to process RabbitMQ order event.', [
                        'exception' => $exception,
                        'payload' => $payload,
                    ]);

                    $message->nack(requeue: true);
                }
            },
        );

        try {
            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } finally {
            $channel->close();
            $connection->close();
        }

        return self::SUCCESS;
    }
}
