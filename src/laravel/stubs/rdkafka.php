<?php

/**
 * IDE stubs for ext-rdkafka (PECL).
 * Runtime: расширение ставится в Docker-образе php/Dockerfile.
 * Этот файл нужен только редактору — не подключается при выполнении.
 *
 * @see https://github.com/arnaud-lb/php-rdkafka
 */

namespace RdKafka;

class Conf
{
    public function set(string $name, string $value): void {}
}

class KafkaConsumer
{
    public function __construct(Conf $conf) {}

    /** @param list<string> $topics */
    public function subscribe(array $topics): void {}

    public function consume(int $timeout_ms): Message {}
}

class Producer
{
    public function __construct(Conf $conf) {}

    public function newTopic(string $topic_name, ?Conf $topic_conf = null): ProducerTopic {}

    public function poll(int $timeout_ms): void {}

    public function flush(int $timeout_ms): int {}
}

class ProducerTopic
{
    public function produce(int $partition, int $msgflags, string $payload, ?string $key = null, ?int $msg_opaque = null): void {}
}

class Message
{
    public ?int $err;

    public ?string $payload;

    public ?string $topic_name;

    public function errstr(): string {}
}

namespace {
    const RD_KAFKA_RESP_ERR_NO_ERROR = 0;
    const RD_KAFKA_RESP_ERR__PARTITION_EOF = -191;
    const RD_KAFKA_RESP_ERR__TIMED_OUT = -185;
    const RD_KAFKA_PARTITION_UA = -1;
}
