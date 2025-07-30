<?php

// File: app/Services/FontteWhatsAppService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationLog;

class FontteWhatsAppService
{
    private $apiKey;
    private $baseUrl;
    private $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.fonnte.api_key');
        $this->baseUrl = config('services.fonnte.base_url', 'https://api.fonnte.com');
        $this->timeout = config('services.fonnte.timeout', 30);
    }

    /**
     * Send WhatsApp message via Fonnte API
     */
    public function sendMessage($target, $message, $notificationLog = null): array
    {
        if (!$this->isConfigured()) {
            return $this->errorResponse('Fonnte API not configured');
        }

        try {
            $target = $this->formatPhoneNumber($target);
            
            if (!$target) {
                return $this->errorResponse('Invalid phone number format');
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => $this->apiKey,
                ])
                ->post($this->baseUrl . '/send', [
                    'target' => $target,
                    'message' => $message,
                    'countryCode' => '62', // Indonesia
                ]);

            $responseData = $response->json();
            
            if ($response->successful() && isset($responseData['status']) && $responseData['status'] === true) {
                $this->logSuccess($notificationLog, $responseData);
                return $this->successResponse($responseData);
            } else {
                $errorMsg = $responseData['reason'] ?? 'Unknown error from Fonnte API';
                $this->logError($notificationLog, $errorMsg, $responseData);
                return $this->errorResponse($errorMsg, $responseData);
            }

        } catch (\Exception $e) {
            $errorMsg = 'HTTP request failed: ' . $e->getMessage();
            $this->logError($notificationLog, $errorMsg);
            Log::error('Fonnte API Error: ' . $errorMsg);
            return $this->errorResponse($errorMsg);
        }
    }

    /**
     * Send bulk WhatsApp messages
     */
    public function sendBulkMessages(array $messages): array
    {
        $results = [];
        
        foreach ($messages as $index => $messageData) {
            $target = $messageData['target'];
            $message = $messageData['message'];
            $notificationLog = $messageData['notification_log'] ?? null;
            
            $result = $this->sendMessage($target, $message, $notificationLog);
            $results[$index] = $result;
            
            // Add small delay to avoid rate limiting
            if (count($messages) > 1 && $index < count($messages) - 1) {
                usleep(500000); // 0.5 second delay
            }
        }
        
        return $results;
    }

    /**
     * Get delivery status from Fonnte
     */
    public function getDeliveryStatus($messageId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => $this->apiKey,
                ])
                ->get($this->baseUrl . '/status', [
                    'id' => $messageId
                ]);

            if ($response->successful()) {
                return $this->successResponse($response->json());
            } else {
                return $this->errorResponse('Failed to get delivery status');
            }

        } catch (\Exception $e) {
            Log::error('Fonnte Status Check Error: ' . $e->getMessage());
            return $this->errorResponse('Status check failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if Fonnte is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }

    /**
     * Test Fonnte connection
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return $this->errorResponse('Fonnte not configured');
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => $this->apiKey,
                ])
                ->get($this->baseUrl . '/validate');

            if ($response->successful()) {
                $data = $response->json();
                return $this->successResponse([
                    'status' => 'connected',
                    'device' => $data['device'] ?? 'Unknown',
                    'quota' => $data['quota'] ?? 'Unknown'
                ]);
            } else {
                return $this->errorResponse('Connection test failed');
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Connection test error: ' . $e->getMessage());
        }
    }

    /**
     * Format phone number to Indonesian WhatsApp format
     */
    private function formatPhoneNumber($phone): ?string
    {
        if (!$phone) {
            return null;
        }

        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Convert Indonesian format to international
        if (substr($phone, 0, 1) === '0') {
            $phone = '62' . substr($phone, 1);
        } elseif (substr($phone, 0, 2) !== '62') {
            $phone = '62' . $phone;
        }

        // Validate Indonesian phone number format
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            return null;
        }

        return $phone;
    }

    /**
     * Log successful message sending
     */
    private function logSuccess($notificationLog, $responseData)
    {
        if ($notificationLog) {
            $notificationLog->update([
                'status' => 'sent',
                'api_response' => $responseData,
                'external_id' => $responseData['id'] ?? null,
                'sent_at' => now()
            ]);
        }
    }

    /**
     * Log failed message sending
     */
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

    /**
     * Success response format
     */
    private function successResponse($data = null): array
    {
        return [
            'success' => true,
            'data' => $data,
            'error' => null
        ];
    }

    /**
     * Error response format
     */
    private function errorResponse($message, $data = null): array
    {
        return [
            'success' => false,
            'data' => $data,
            'error' => $message
        ];
    }
}
