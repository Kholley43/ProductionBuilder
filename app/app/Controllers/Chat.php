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
        // Allow CORS preflight
        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        // Accept both POST (JSON body) and GET (?message=...)
        $method = strtolower($this->request->getMethod());
        if (in_array($method, ['post', 'get'], true)) {
            $userMsg = null;
            if ($method === 'post') {
                $userMsg = $this->request->getJSON(true)['message'] ?? null;
            } else {
                $userMsg = $this->request->getGet('message');
            }
        } else {
            return $this->response->setStatusCode(405);
        }

        if (! $userMsg) {
            return $this->failValidationError('message required');
        }

        $session = session();
        $ctx     = $session->get('chat_ctx') ?? [];
        $ctx[]   = ['role' => 'user', 'content' => $userMsg];

        $payload = [
            'messages' => $ctx,
        ];

        // Only supply tool definitions when the user message includes a builder keyword
        $msgLower = strtolower($userMsg);
        $payload['tools'] = '';
        foreach (['scaffold','migrate','zip_project','clean_db'] as $kw) {
            if (str_contains($msgLower, $kw)) {
                $payload['tools'] = file_get_contents(APPPATH . 'Config/tools.json');
                break;
            }
        }

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
        try {
            $resp = $client->setBody(json_encode($payload))
                           ->setHeader('Content-Type', 'application/json')
                           ->post($endpoint);
            if ($resp->getStatusCode() !== 200) {
                return 'LLM error: ' . $resp->getBody();
            }
            $data = json_decode($resp->getBody(), true);
            return $data['content'] ?? $resp->getBody();
        } catch (\Throwable $e) {
            return 'LLM error: ' . $e->getMessage();
        }
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