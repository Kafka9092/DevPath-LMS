<?php

namespace App\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;

    protected string $model = 'gemma4:31b-cloud';

    protected ?string $apiKey;

    public function __construct()
    {
        $this->host   = config('services.ollama.host', 'http://localhost:11434');
        $this->apiKey = config('services.ollama.api_key');
        $this->model  = config('services.ollama.model', 'gemma4:31b-cloud');
    }

    public function chat(array $messages, ?string $system = null, array $options = []): string
    {
        $chatMessages = $messages;
        if ($system !== null && $system !== '') {
            array_unshift($chatMessages, ['role' => 'system', 'content' => $system]);
        }

        $payload = [
            'model'    => $this->model,
            'messages' => $chatMessages,
            'stream'   => false,
            'format'   => 'json',
            'options'  => array_merge([
                'num_ctx'     => 16384,
                'num_predict' => 4096,
                'temperature' => 0.4,
            ], $options),
        ];

        try {
            $response = $this->httpClient()->timeout(600)->post("{$this->host}/api/chat", $payload);

            if (!$response->successful()) {
                throw new \Exception('Ошибка подключения к нейросети. Код: ' . $response->status());
            }

            $content = $response->json('message.content');
            if (empty($content)) {
                throw new \Exception('Нейросеть вернула пустой ответ');
            }

            return trim($content);
        } catch (\Exception $e) {
            Log::error('OllamaService::chat error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function generateJson(string $prompt, ?array $schema = null, array $options = [], int $timeout = 600): array
    {
        $payload = [
            'model'   => $this->model,
            'prompt'  => $prompt,
            'stream'  => false,
            'format'  => 'json',
            'options' => array_merge([
                'num_ctx'     => 16384,
                'num_predict' => 4096,
                'temperature' => 0.1,
            ], $options),
        ];

        if ($schema) {
            $payload['format'] = $schema;
        }

        try {
            $response = $this->httpClient()->timeout($timeout)->post("{$this->host}/api/generate", $payload);

            if (!$response->successful()) {
                throw new \Exception('Ошибка подключения к нейросети. Код: ' . $response->status());
            }

            $content = $response->json('response');
            if (empty($content)) {
                throw new \Exception('Нейросеть вернула пустой ответ');
            }

            return $this->parseJsonContent($content);
        } catch (\Exception $e) {
            Log::error('OllamaService::generateJson error: ' . $e->getMessage());
            throw $e;
        }
    }

    /** Надёжнее для больших JSON-ответов (code review, уроки). */
    public function generateJsonViaChat(string $prompt, ?string $system = null, array $options = []): array
    {
        $content = $this->chat(
            [['role' => 'user', 'content' => $prompt]],
            $system,
            array_merge(['temperature' => 0.2, 'num_predict' => 8192], $options),
        );

        return $this->parseJsonContent($content);
    }

    public function parseJsonContent(string $content): array
    {
        $content = trim((string) preg_replace('/```json\s*|\s*```/', '', $content));
        $start   = strpos($content, '{');

        if ($start === false) {
            throw new \Exception('Не удалось найти JSON в ответе');
        }

        $depth = 0;
        $end   = $start;
        $len   = strlen($content);

        for ($i = $start; $i < $len; $i++) {
            $char = $content[$i];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        $json = substr($content, $start, $end - $start + 1);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \Exception('Нейросеть вернула данные в неверном формате');
        }

        return $data;
    }

    public function isAvailable(): bool
    {
        try {
            return $this->httpClient()->timeout(5)->get("{$this->host}/api/tags")->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getModel(): string
    {
        return $this->model;
    }

    private function httpClient()
    {
        $headers = ['Content-Type' => 'application/json'];

        if (!empty($this->apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        return Http::withHeaders($headers);
    }
}
