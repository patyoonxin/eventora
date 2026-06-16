<?php

namespace App\Services;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent";

    public function __construct()
    {
        // Load the key once from the environment
        $this->apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    }

    /**
     * Sends a text prompt to Gemini and returns the generated text response.
     */
    public function generateText(string $prompt): ?string
    {
        if (empty($this->apiKey)) {
            return null; // Or throw an Exception depending on your preference
        }

        $url = $this->baseUrl . "?key=" . $this->apiKey;

        $payload = json_encode([
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $apiResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null; 
        }

        $resultData = json_decode($apiResponse, true);
        return $resultData['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}