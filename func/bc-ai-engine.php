<?php
/**
 * bc-ai-engine.php — DGV6.90 AI Edition
 * Ollama AI Engine Interface
 *
 * Provides a secure, local-only PHP interface to Ollama.
 * Ollama MUST be bound to 127.0.0.1 — never exposed publicly.
 *
 * GOLDEN RULE: This class never writes to the database directly,
 * never moves money, and never executes transactions.
 * It returns text/JSON for PHP business logic to act upon.
 */

if (class_exists('AIEngine')) return; // Guard against double-include

class AIEngine
{
    // ─── Configuration ────────────────────────────────────────
    private string $base_url;
    private string $api_key          = '';
    private string $provider         = 'ollama'; // 'ollama', 'gemini', 'deepseek', 'groq'
    private int    $timeout_generate = 60;   
    private int    $timeout_list     = 10;   
    private bool   $debug            = false; 

    public function __construct(string $override_host = '')
    {
        global $connection_server;

        // Load Global Configuration
        $this->provider = getSuperAdminOption('ai_provider', 'ollama');
        
        // Load provider-specific key with fallback to global
        $key_name = "ai_{$this->provider}_api_key";
        $this->api_key = getSuperAdminOption($key_name, '');
        if (empty($this->api_key)) $this->api_key = getSuperAdminOption('ai_api_key', '');

        $host = getSuperAdminOption('ai_ollama_host', 'http://127.0.0.1:11434');

        if (!empty($override_host)) $host = $override_host;

        switch ($this->provider) {
            case 'gemini':
                $this->base_url = 'https://generativelanguage.googleapis.com/v1beta';
                break;
            case 'deepseek':
                $this->base_url = 'https://api.deepseek.com/v1';
                break;
            case 'groq':
                $this->base_url = 'https://api.groq.com/openai/v1';
                break;
            default: // ollama
                $this->base_url = rtrim($host, '/') . '/api';
                break;
        }
    }

    /**
     * Unified Chat/Generation Entry Point
     */
    public function chat(string $model, string $prompt, array $options = []): array
    {
        switch ($this->provider) {
            case 'gemini':   return $this->chatGemini($model, $prompt, $options);
            case 'deepseek': return $this->chatOpenAICompatible($this->base_url, $model, $prompt, $options);
            case 'groq':     return $this->chatOpenAICompatible($this->base_url, $model, $prompt, $options);
            default:         return $this->generate($model, $prompt, false, $options);
        }
    }

    /**
     * Google Gemini API Handler
     */
    private function chatGemini(string $model, string $prompt, array $options): array
    {
        // Default to gemini-1.5-flash if not specified or unknown
        if (strpos($model, 'gemini') === false) $model = 'gemini-1.5-flash';
        
        $url = "{$this->base_url}/models/{$model}:generateContent?key={$this->api_key}";
        $payload = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['num_predict'] ?? 1024,
            ]
        ]);

        $start = microtime(true);
        $raw = $this->curlPost($url, $payload, $this->timeout_generate);
        $duration_ms = (int)((microtime(true) - $start) * 1000);

        if ($raw === false) return $this->errorResult('Gemini API unreachable', $model, $duration_ms);
        
        $data = json_decode($raw, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($text)) {
            $err = $data['error']['message'] ?? 'Unknown Gemini Error';
            return $this->errorResult($err, $model, $duration_ms);
        }

        return [
            'status' => 'success',
            'response' => $text,
            'model' => $model,
            'duration_ms' => $duration_ms,
            'provider' => 'gemini'
        ];
    }

    /**
     * OpenAI Compatible API Handler (DeepSeek, Groq, etc.)
     */
    private function chatOpenAICompatible(string $endpoint, string $model, string $prompt, array $options): array
    {
        $url = "{$endpoint}/chat/completions";
        $payload = json_encode([
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['num_predict'] ?? 1024,
        ]);

        $headers = [
            'Content-Type: application/json',
            "Authorization: Bearer {$this->api_key}"
        ];

        $start = microtime(true);
        $raw = $this->curlPost($url, $payload, $this->timeout_generate, $headers);
        $duration_ms = (int)((microtime(true) - $start) * 1000);

        if ($raw === false) return $this->errorResult("{$this->provider} API unreachable", $model, $duration_ms);
        
        $data = json_decode($raw, true);
        $text = $data['choices'][0]['message']['content'] ?? '';

        if (empty($text)) {
            $err = $data['error']['message'] ?? 'Unknown API Error';
            return $this->errorResult($err, $model, $duration_ms);
        }

        return [
            'status' => 'success',
            'response' => $text,
            'model' => $model,
            'duration_ms' => $duration_ms,
            'provider' => $this->provider
        ];
    }

    /**
     * Original Ollama Generation Handler
     */
    public function generate(string $model, string $prompt, bool $stream = false, array $options = []): array
    {
        $model = $this->sanitizeModelName($model);

        if (empty($model) || empty($prompt)) {
            return $this->errorResult('Empty model or prompt');
        }

        $payload = json_encode([
            'model'   => $model,
            'prompt'  => $prompt,
            'stream'  => $stream,
            'options' => array_merge([
                'temperature' => 0.7,
                'top_p'       => 0.9,
                'num_predict' => 512, // Limit response length for speed
            ], $options),
        ]);

        $start = microtime(true);
        $raw = $this->curlPost($this->base_url . '/generate', $payload, $this->timeout_generate);
        $duration_ms = (int)((microtime(true) - $start) * 1000);

        if ($raw === false) {
            return $this->errorResult('Ollama not reachable', $model, $duration_ms);
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->errorResult('Invalid JSON from Ollama', $model, $duration_ms);
        }

        $response_text = $data['response'] ?? '';

        if (empty($response_text) && !$stream) {
            return $this->errorResult('Empty response from model', $model, $duration_ms);
        }

        return [
            'status'      => 'success',
            'response'    => trim($response_text),
            'model'       => $model,
            'duration_ms' => $duration_ms,
        ];
    }

    /**
     * Generate response with vision (image support)
     */
    public function generateWithVision(string $model, string $prompt, array $images, array $options = []): array
    {
        $payload = json_encode([
            'model'   => $model,
            'prompt'  => $prompt,
            'images'  => $images, // Array of base64 strings
            'stream'  => false,
            'options' => array_merge([
                'temperature' => 0.2,
                'num_predict' => 256,
            ], $options),
        ]);

        $start = microtime(true);
        $raw = $this->curlPost($this->base_url . '/generate', $payload, $this->timeout_generate);
        $duration_ms = (int)((microtime(true) - $start) * 1000);

        if ($raw === false) return $this->errorResult('Ollama not reachable', $model, $duration_ms);
        $data = json_decode($raw, true);
        return [
            'status' => 'success',
            'response' => $data['response'] ?? '',
            'model' => $model,
            'duration_ms' => $duration_ms
        ];
    }

    /**
     * Generate with automatic fallback through the model chain.
     * Tries the preferred model first, then falls back if unavailable.
     *
     * @param string $preferred_model  First model to try
     * @param string $prompt           Sanitized prompt
     * @param array  $options          Generation options
     */
    public function generateWithFallback(string $preferred_model, string $prompt, array $options = []): array
    {
        $models_to_try = array_unique(array_merge([$preferred_model], $this->model_chain));

        foreach ($models_to_try as $model) {
            if (!$this->isModelReady($model)) continue;
            $result = $this->generate($model, $prompt, false, $options);
            if ($result['status'] === 'success') return $result;
        }

        return $this->errorResult('No AI models available. Please install a model from the AI Model Manager.');
    }

    /**
     * Chat-style multi-turn conversation (uses /api/chat endpoint).
     *
     * @param string $model     Ollama model name
     * @param array  $messages  Array of ['role' => 'user'|'assistant'|'system', 'content' => string]
     */
    public function chatOllamaRaw(string $model, array $messages, array $options = []): array
    {
        $model = $this->sanitizeModelName($model);

        $payload = json_encode([
            'model'    => $model,
            'messages' => $messages,
            'stream'   => false,
            'options'  => array_merge(['temperature' => 0.7, 'num_predict' => 512], $options),
        ]);

        $start = microtime(true);
        $raw = $this->curlPost($this->base_url . '/chat', $payload, $this->timeout_generate);
        $duration_ms = (int)((microtime(true) - $start) * 1000);

        if ($raw === false) return $this->errorResult('Ollama not reachable', $model, $duration_ms);

        $data = json_decode($raw, true);
        $response_text = $data['message']['content'] ?? '';

        if (empty($response_text)) return $this->errorResult('Empty chat response', $model, $duration_ms);

        return [
            'status'      => 'success',
            'response'    => trim($response_text),
            'model'       => $model,
            'duration_ms' => $duration_ms,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // 2. MODEL MANAGEMENT
    // ─────────────────────────────────────────────────────────

    /**
     * Returns list of currently installed models from Ollama.
     * @return array Array of model name strings, or empty array on failure.
     */
    public function listModels(): array
    {
        $raw = $this->curlGet($this->base_url . '/tags', $this->timeout_list);
        if ($raw === false) return [];

        $data = json_decode($raw, true);
        $models = [];
        foreach (($data['models'] ?? []) as $m) {
            $models[] = $m['name'] ?? '';
        }
        return array_filter($models);
    }

    /**
     * Checks if a specific model is installed and ready.
     * @param string $model_name  e.g. 'phi4-mini', 'gemma4:e2b'
     */
    public function isModelReady(string $model_name): bool
    {
        $installed = $this->listModels();
        // Normalize comparison — Ollama often returns 'modelname:latest' even if you specify 'modelname'
        $model_name = strtolower(trim($model_name));
        foreach ($installed as $m) {
            $m_norm = strtolower($m);
            if ($m_norm === $model_name || $m_norm === $model_name . ':latest') {
                return true;
            }
        }
        return false;
    }


    /**
     * Initiates a model pull in the background (non-blocking).
     * Logs the request to sas_ai_install_queue.
     *
     * @param string $model_name  Model to download (e.g. 'phi4-mini')
     * @param string $admin_email For notification when complete
     * @return bool True if the background process was started
     */
    public function pullModelBackground(string $model_name, string $admin_email = ''): bool
    {
        global $connection_server;

        $model_name = $this->sanitizeModelName($model_name);
        if (empty($model_name)) return false;

        // Allow only known safe model names to prevent shell injection
        $allowed_models = [
            'phi4-mini', 'gemma4:e2b', 'gemma4:27b', 'gemma4:12b',
            'llama4-scout', 'qwen3:0.6b', 'qwen3:1.7b', 'qwen3:4b',
            'qwen3:8b', 'llama3.2:3b', 'mistral:7b', 'deepseek-r1:1.5b'
        ];

        if (!in_array($model_name, $allowed_models)) {
            bc_log_security_event('AI_MODEL_BLOCKED', 'pull_model', $model_name, 'Model not in allowed list');
            return false;
        }

        // Log to install queue
        if ($connection_server) {
            $esc_model = mysqli_real_escape_string($connection_server, $model_name);
            $esc_email = mysqli_real_escape_string($connection_server, $admin_email);
            mysqli_query($connection_server,
                "INSERT INTO sas_ai_install_queue (model_name, status, admin_email)
                 VALUES ('$esc_model', 'downloading', '$esc_email')
                 ON DUPLICATE KEY UPDATE status='downloading', admin_email='$esc_email', started_at=NOW(), completed_at=NULL"
            );
        }

        // Fire background process — output redirected, process detached
        // The model name has already been validated against an allowlist, so shell injection is prevented.
        $log_path = sys_get_temp_dir() . '/ollama_pull_' . preg_replace('/[^a-z0-9]/', '_', $model_name) . '.log';
        $cmd = "nohup ollama pull " . escapeshellarg($model_name) . " > " . escapeshellarg($log_path) . " 2>&1 &";
        shell_exec($cmd);

        return true;
    }

    /**
     * Called by cron job to check if a downloading model has completed.
     * Updates sas_ai_install_queue to 'ready' when found.
     *
     * @return array Newly completed model names
     */
    public function checkAndUpdateModelStatus(): array
    {
        global $connection_server;
        $newly_completed = [];

        if (!$connection_server) return $newly_completed;

        $q = mysqli_query($connection_server,
            "SELECT model_name, admin_email FROM sas_ai_install_queue WHERE status='downloading'"
        );

        while ($row = mysqli_fetch_assoc($q)) {
            $model = $row['model_name'];
            if ($this->isModelReady($model)) {
                $esc_model = mysqli_real_escape_string($connection_server, $model);
                mysqli_query($connection_server,
                    "UPDATE sas_ai_install_queue SET status='ready', completed_at=NOW()
                     WHERE model_name='$esc_model'"
                );
                $newly_completed[] = ['model' => $model, 'notify_email' => $row['admin_email']];
            }
        }

        return $newly_completed;
    }

    // ─────────────────────────────────────────────────────────
    // 3. STRUCTURED INTENT PARSING (for Voice-to-VTU)
    // ─────────────────────────────────────────────────────────

    /**
     * Parse a voice transcript into a structured VTU transaction intent.
     * Returns structured JSON or null on failure.
     *
     * @param string $transcript     Raw speech-to-text output
     * @param string $model          Ollama model to use
     *
     * @return array|null ['service','amount','phone','network','confidence'] or null
     */
    public function parseVtuIntent(string $transcript, string $model = 'phi4-mini'): ?array
    {
        $safe_transcript = bc_sanitize(substr($transcript, 0, 300));

        $system_prompt = <<<PROMPT
You are a professional Nigerian VTU transaction intent parser.
You understand formal English, Nigerian Pidgin, and local transaction shorthand.

Extract the following fields from the user's voice command:
- service: one of [airtime, data, electricity, cable, betting]
- amount: numeric value in Naira (just digits, no currency symbol)
- phone: 11-digit Nigerian phone number starting with 0 (or empty string)
- network: one of [MTN, Airtel, Glo, 9mobile] (or empty string)
- confidence: a number 0-100 representing how confident you are

Handle Pidgin examples: 
- "Abeg load 500 MTN for 080123..." -> service: airtime, amount: 500, network: MTN
- "I want subscribe for data 1k on 070..." -> service: data, amount: 1000

Output ONLY valid JSON with these exact keys. No explanation. No markdown.
Example: {"service":"airtime","amount":"500","phone":"08012345678","network":"MTN","confidence":95}

Voice command: $safe_transcript
PROMPT;

        $result = $this->generate($model, $system_prompt, false, [
            'temperature' => 0.1, // Very low for deterministic JSON output
            'num_predict' => 150,
        ]);

        if ($result['status'] !== 'success') return null;

        // Extract JSON from the response
        $response = $result['response'];
        preg_match('/\{[^}]+\}/', $response, $matches);
        if (empty($matches[0])) return null;

        $parsed = json_decode($matches[0], true);
        if (!is_array($parsed)) return null;

        // Validate required fields
        $required = ['service', 'amount', 'phone', 'confidence'];
        foreach ($required as $key) {
            if (!isset($parsed[$key])) return null;
        }

        // Validate service type
        $allowed_services = ['airtime', 'data', 'electricity', 'cable', 'betting'];
        if (!in_array(strtolower($parsed['service']), $allowed_services)) return null;

        // Sanitize the phone number
        if (!empty($parsed['phone'])) {
            $parsed['phone'] = bc_sanitize_phone($parsed['phone']);
        }

        // Sanitize amount
        $parsed['amount'] = bc_sanitize_number($parsed['amount']);
        $parsed['confidence'] = (int)($parsed['confidence'] ?? 0);

        return $parsed;
    }

    // ─────────────────────────────────────────────────────────
    // 4. PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────

    /**
     * Check if the currently selected AI provider is reachable and active.
     */
    public function isAiOnline(): bool
    {
        switch ($this->provider) {
            case 'gemini':
                $url = "{$this->base_url}/models/gemini-1.5-flash?key={$this->api_key}";
                $res = $this->curlGet($url);
                return ($res !== false && strpos($res, 'gemini-1.5-flash') !== false);
            
            case 'deepseek':
                $url = "{$this->base_url}/models";
                $headers = ["Authorization: Bearer {$this->api_key}"];
                $res = $this->curlGet($url, 10, $headers);
                return ($res !== false && strpos($res, 'deepseek') !== false);

            case 'groq':
                $url = "{$this->base_url}/models";
                $headers = ["Authorization: Bearer {$this->api_key}"];
                $res = $this->curlGet($url, 10, $headers);
                return ($res !== false && strpos($res, 'groq') !== false);

            default: // ollama
                $res = $this->curlGet(str_replace('/api', '', $this->base_url));
                return ($res !== false && strpos($res, 'Ollama is running') !== false);
        }
    }

    /**
     * Compatibility alias for legacy calls
     */
    public function isOllamaOnline(): bool { return $this->isAiOnline(); }

    private function curlPost(string $url, string $body, int $timeout = 30, array $headers = []): string|false
    {
        $ch = curl_init($url);
        $final_headers = array_merge(['Content-Type: application/json'], $headers);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => $final_headers,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($this->debug && $error) error_log("[AIEngine] cURL error: $error");
        return ($result === false) ? false : $result;
    }

    private function curlGet(string $url, int $timeout = 10, array $headers = []): string|false
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return ($result === false) ? false : $result;
    }

    private function sanitizeModelName(string $model): string
    {
        // Only allow alphanumeric, hyphens, colons, and dots for model names
        return preg_replace('/[^a-zA-Z0-9\-\.:_]/', '', trim($model));
    }

    /**
     * Compatibility alias for 'generate' that returns only the raw string.
     */
    public function chatSimple(string $prompt, string $model = 'phi4-mini'): string
    {
        $res = $this->generate($model, $prompt);
        return $res['response'] ?? '';
    }

    private function errorResult(string $message, string $model = '', int $duration_ms = 0): array
    {
        if ($this->debug) error_log("[AIEngine] Error: $message");
        return [
            'status'      => 'error',
            'response'    => '',
            'message'     => $message,
            'model'       => $model,
            'duration_ms' => $duration_ms,
        ];
    }
}

/**
 * Compatibility Wrapper for legacy BcAiEngine calls
 */
class BcAiEngine {
    public static function getInstance() {
        return ai_engine();
    }
}

// ─────────────────────────────────────────────────────────────
// Global singleton accessor
// Usage: $ai = ai_engine(); $result = $ai->generate('phi4-mini', $prompt);
// ─────────────────────────────────────────────────────────────
function ai_engine(): AIEngine
{
    static $instance = null;
    if ($instance === null) {
        $instance = new AIEngine();
    }
    return $instance;
}
