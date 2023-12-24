<?php

namespace App\Http\Controllers;

use App\Http\StreamResponse;
use App\Events\ChatTokenReceived;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Http;

class StreamController extends Controller
{
    public function streamTokens()
    {
        try {
            $client = new Client();

            $url = 'https://api.together.xyz/inference';
            $model = 'togethercomputer/RedPajama-INCITE-7B-Instruct';

            $data = [
                "model" => $model,
                "prompt" => "Alan Turing was",
                "max_tokens" => 128,
                "stop" => ["\n\n"],
                "temperature" => 0.7,
                "top_p" => 0.7,
                "top_k" => 50,
                "repetition_penalty" => 1,
                "stream_tokens" => true
            ];

            $response = $client->post($url, [
                'json' => $data,
                'stream' => true,
                'headers' => [
                    'Authorization' => 'Bearer ' . env('TOGETHER_API_KEY'),
                ],
            ]);

            // Reading the streamed response
            $stream = $response->getBody();

            foreach ($this->readStream($stream) as $responseLine) {
                // $content .= $responseLine;
                dump($responseLine);
                $token = $responseLine["choices"][0]["text"];
                dump($token);
                broadcast(new ChatTokenReceived($token));
            }
        } catch (RequestException $e) {
            // Handle exception or errors here
            echo $e->getMessage();
        }
    }

    public function readStream($stream)
    {
        while (!$stream->eof()) {
            $line = $this->readLine($stream);

            if (!str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, strlen('data:')));

            if ($data === '[DONE]') {
                break;
            }

            $response = json_decode($data, true, JSON_THROW_ON_ERROR);

            if (isset($response['error'])) {
                throw new \Exception($response['error']);
            }

            yield $response;
        }
    }

    public function readLine($stream)
    {
        $line = '';
        while (!$stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;
        }
        return $line;
    }
}