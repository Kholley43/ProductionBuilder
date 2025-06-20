<?php

namespace App\Controllers;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\CLI\CLI;

/**
 * Simple chat endpoint that proxies messages to a local LLM service, executes
 * recognized builder tools, and returns the assistant reply.
 *
 * POST /chat  { "message": "create product api with name:string" }
 *
 * Environment variable LLM_ENDPOINT may override the default URL.
 */
class Chat extends BaseController
{
    public function index()
    {
        if ($this->request->getMethod() !== 'post') {
            return $this->response->setStatusCode(405);
        }

        $userMsg = $this->request->getJSON(true)['message'] ?? null;
        if (! $userMsg) {
            return $this->failValidationError('message required');
        }

        $session = session();
        $ctx     = $session->get('chat_ctx') ?? [];
        $ctx[]   = ['role' => 'user', 'content' => $userMsg];

        $payload = [
            'messages' => $ctx,
            'tools'    => file_get_contents(APPPATH . 'Config/tools.json'),
        ];

        $assistantText = $this->callLLM($payload);

        // Check if assistant replied with a tool-call
        $out = null;
        $decoded = json_decode($assistantText, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['name'])) {
            $toolName = $decoded['name'];
            $args     = $decoded['arguments'] ?? [];
            $out      = $this->executeTool($toolName, $args);
            $ctx[]    = ['role' => 'assistant', 'content' => $assistantText];
            $ctx[]    = ['role' => 'tool', 'content' => $out];
        } else {
            $ctx[] = ['role' => 'assistant', 'content' => $assistantText];
        }

        $session->set('chat_ctx', $ctx);

        return $this->response->setJSON([
            'reply' => $assistantText,
            'toolOutput' => $out,
        ]);
    }

    private function callLLM(array $payload): string
    {
        $endpoint = getenv('LLM_ENDPOINT') ?: 'http://127.0.0.1:8000/v1/chat';
        /** @var CURLRequest $client */
        $client = service('curlrequest');
        $resp   = $client->setBody(json_encode($payload))
                        ->setHeader('Content-Type', 'application/json')
                        ->post($endpoint);
        if ($resp->getStatusCode() !== 200) {
            return 'LLM error: ' . $resp->getBody();
        }
        $data = json_decode($resp->getBody(), true);
        return $data['content'] ?? $resp->getBody();
    }

    private function executeTool(string $name, array $args): string
    {
        ob_start();
        $commands = service('commands');
        match ($name) {
            'scaffold'    => $commands->run('scaffold', [$args['prompt'] ?? '']),
            'migrate'     => $commands->run('migrate', ['--all' => null]),
            'zip_project' => (new Builder())->packageProject(),
            default       => CLI::error("Unknown tool: {$name}"),
        };
        return ob_get_clean();
    }
} 