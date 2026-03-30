<?php

namespace App\Services\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMqConnectionFactory
{
    public function make(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            host: (string) config('services.rabbitmq.host', 'rabbitmq'),
            port: (int) config('services.rabbitmq.port', 5672),
            user: (string) config('services.rabbitmq.user', 'guest'),
            password: (string) config('services.rabbitmq.password', 'guest'),
            vhost: (string) config('services.rabbitmq.vhost', '/'),
        );
    }
}
