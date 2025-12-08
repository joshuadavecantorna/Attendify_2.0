<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ollama Host
    |--------------------------------------------------------------------------
    |
    | The base URL for your Ollama server. For local installation, this
    | should be http://localhost:11434
    |
    */

    'host' => env('OLLAMA_HOST'),  // Remove the fallback

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default Ollama model to use for AI requests. You can override
    | this on a per-request basis if needed.
    |
    */

    'model' => env('OLLAMA_MODEL', 'qwen2.5:7b'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum time (in seconds) to wait for a response from Ollama.
    | AI responses can take time, so this should be reasonably high.
    |
    */

    'timeout' => env('OLLAMA_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Stream Responses
    |--------------------------------------------------------------------------
    |
    | Whether to stream responses from Ollama or wait for complete response.
    | Set to false for easier handling in most cases.
    |
    */

    'stream' => true,

];
