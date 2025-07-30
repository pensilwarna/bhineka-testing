<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationLog;

class TelegramService
{
    private $botToken;
    private $baseUrl;
    private $timeout;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->baseUrl = 'https://api.telegram.org/bot' . $this->botToken;
        $this->timeout = 30;
    }

    /**
     * Send message via Telegram
     */
    public function sendMessage($chatId, $message, $notificationLog = null): array
    {
        if (!$this->isConfigured()) {
            return $this->errorResponse('Telegram bot not configured');
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ]);

            $responseData = $response->json();
            
            if ($response->successful() && $responseData['ok']) {
                $this->logSuccess($notificationLog, $responseData);
                return $this->successResponse($responseData);
            } else {
                $errorMsg = $responseData['description'] ?? 'Unknown Telegram API error';
                $this->logError($notificationLog, $errorMsg, $responseData);
                return $this->errorResponse($errorMsg, $responseData);
            }

        } catch (\Exception $e) {
            $errorMsg = 'Telegram API request failed: ' . $e->getMessage();
            $this->logError($notificationLog, $errorMsg);
            Log::error('Telegram API Error: ' . $errorMsg);
            return $this->errorResponse($errorMsg);
        }
    }

    /**
     * Test Telegram connection
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return $this->errorResponse('Telegram not configured');
        }

        try {
            $response = Http::timeout(10)
                ->get($this->baseUrl . '/getMe');

            if ($response->successful()) {
                $data = $response->json();
                return $this->successResponse([
                    'status' => 'connected',
                    'bot_name' => $data['result']['first_name'] ?? 'Unknown',
                    'username' => $data['result']['username'] ?? 'Unknown'
                ]);
            } else {
                return $this->errorResponse('Connection test failed');
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Connection test error: ' . $e->getMessage());
        }
    }

    /**
     * Check if properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken);
    }

    private function logSuccess($notificationLog, $responseData)
    {
        if ($notificationLog) {
            $notificationLog->update([
                'status' => 'sent',
                'api_response' => $responseData,
                'external_id' => $responseData['result']['message_id'] ?? null,
                'sent_at' => now()
            ]);
        }
    }

    private function logError($notificationLog, $errorMsg, $responseData = null)
    {
        if ($notificationLog) {
            $notificationLog->update([
                'status' => 'failed',
                'error_message' => $errorMsg,
                'api_response' => $responseData
            ]);
        }
    }

    private function successResponse($data = null): array
    {
        return [
            'success' => true,
            'data' => $data,
            'error' => null
        ];
    }

    private function errorResponse($message, $data = null): array
    {
        return [
            'success' => false,
            'data' => $data,
            'error' => $message
        ];
    }
}