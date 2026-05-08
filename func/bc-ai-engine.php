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
    private int    $timeout_generate = 60;   // Seconds for generation calls
    private int    $timeout_list     = 10;   // Seconds for list/ping calls
    private bool   $debug            = false; // Set to true only for dev

    // Default model fallback chain (fastest → most capable)
    private array $model_chain = ['phi4-mini', 'gemma4:e2b', 'gemma4:26b', 'llama4-scout'];

    public function __construct(string $override_host = '')
    {
        global $connection_server;

        // Read host from DB if not overridden
        if (!empty($override_host)) {
            $this->base_url = rtrim($override_host, '/') . '/api';
        } else {
            $host = 'http://127.0.0.1:11434'; // Safe default
            if ($connection_server) {
                $q = mysqli_query($connection_server,
                    "SELECT option_value FROM sas_super_admin_options WHERE option_name='ai_ollama_host' LIMIT 1"
                );
                if ($q && $row = mysqli_fetch_assoc($q)) {
                    $candidate = trim($row['option_value']);
                    // Safety: only allow localhost URLs, never a public IP
                    if (preg_match('/^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/', $candidate)) {
                        $host = $candidate;
                    }
                }
            }
            $this->base_url = $host . '/api';
        }
    }

    // ─────────────────────────────────────────────────────────
    // 1. CORE GENERATION
    // ─────────────────────────────────────────────────────────

    /**
     * Send a prompt to an Ollama model and return the response.
     *
     * @param string $model   Ollama model name (e.g. 'phi4-mini', 'gemma4:e2b')
     * @param string $prompt  Sanitized prompt text (must pass bc_firewall_prompt() first)
     * @param bool   $stream  If true, stream output (for SSE endpoints). Default: false.
     * @param array  $options Ollama generation options (temperature, top_p, etc.)
     *
     * @return array ['status' => 'success'|'error', 'response' => string, 'model' => string, 'duration_ms' => int]
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

        if (empty($response_text)) {
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
    public function chat(string $model, array $messages, array $options = []): array
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
     * Is Ollama running and reachable?
     */
    public function isOllamaOnline(): bool
    {
        $raw = $this->curlGet(str_replace('/api', '', $this->base_url), $this->timeout_list);
        return $raw !== false && strpos($raw, 'Ollama') !== false;
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
You are a Nigerian VTU transaction intent parser.
Extract the following fields from the user's voice command:
- service: one of [airtime, data, electricity, cable, betting]
- amount: numeric value in Naira (just digits, no currency symbol)
- phone: 11-digit Nigerian phone number starting with 0 (or empty string)
- network: one of [MTN, Airtel, Glo, 9mobile] (or empty string)
- confidence: a number 0-100 representing how confident you are

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

    private function curlPost(string $url, string $body, int $timeout = 30): string|false
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            // Force localhost-only — never follow redirects to external URLs
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($this->debug && $error) error_log("[AIEngine] cURL error: $error");
        return ($result === false) ? false : $result;
    }

    private function curlGet(string $url, int $timeout = 10): string|false
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
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
