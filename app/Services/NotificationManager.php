<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Customer;
use App\Models\User;
use App\Models\ServiceLocation;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\UserNotificationPreference;
use App\Models\CustomerNotificationPreference;
use Illuminate\Support\Facades\Log;

class NotificationManager
{
    private $fontteService;
    private $telegramService;

    public function __construct(FontteWhatsAppService $fontteService)
    {
        $this->fontteService = $fontteService;
        // $this->telegramService = $telegramService; // Will implement later
    }

    /**
     * Send ticket assignment notification to technicians and supervisor
     */
    public function notifyTicketAssigned(Ticket $ticket): array
    {
        $results = [];
        
        // Notify technicians
        foreach ($ticket->technicians as $technician) {
            $result = $this->sendUserNotification(
                $technician, 
                'ticket_assigned', 
                $ticket
            );
            $results['technician_' . $technician->id] = $result;
        }
        
        // Notify supervisor
        if ($ticket->supervisor) {
            $result = $this->sendUserNotification(
                $ticket->supervisor, 
                'ticket_assigned', 
                $ticket
            );
            $results['supervisor_' . $ticket->supervisor->id] = $result;
        }
        
        // Notify customer (if opted-in)
        $customerResult = $this->sendCustomerNotification(
            $ticket->customer,
            $ticket->serviceLocation,
            'technician_assigned',
            $ticket
        );
        $results['customer_' . $ticket->customer->id] = $customerResult;
        
        return $results;
    }

    /**
     * Send notification to user (employee)
     */
    public function sendUserNotification(User $user, string $notificationType, $data = null): array
    {
        try {
            // Check if user has notifications enabled
            if (!$user->notifications_enabled) {
                return ['success' => false, 'error' => 'User notifications disabled'];
            }

            // Get user preferences for this notification type
            $preference = UserNotificationPreference::where('user_id', $user->id)
                ->where('notification_type', $notificationType)
                ->where('is_active', true)
                ->first();

            if (!$preference) {
                return ['success' => false, 'error' => 'No active preference found'];
            }

            $channels = json_decode($preference->channels, true) ?? [];
            $results = [];

            foreach ($channels as $channel) {
                $result = $this->sendNotificationViaChannel(
                    $user, 
                    $channel, 
                    $notificationType, 
                    $data
                );
                $results[$channel] = $result;
            }

            return ['success' => true, 'channels' => $results];

        } catch (\Exception $e) {
            Log::error('User notification error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send notification to customer
     */
    public function sendCustomerNotification(Customer $customer, ServiceLocation $serviceLocation, string $notificationType, $data = null): array
    {
        try {
            // Check if customer has notifications enabled globally
            if (!$customer->notifications_enabled) {
                return ['success' => false, 'error' => 'Customer notifications disabled'];
            }

            // Get customer preferences for this service location and notification type
            $preference = CustomerNotificationPreference::where('customer_id', $customer->id)
                ->where('service_location_id', $serviceLocation->id)
                ->where('notification_type', $notificationType)
                ->where('is_active', true)
                ->first();

            if (!$preference) {
                return ['success' => false, 'error' => 'Customer not opted-in for this notification'];
            }

            $channels = json_decode($preference->channels, true) ?? [];
            $results = [];

            foreach ($channels as $channel) {
                $result = $this->sendCustomerNotificationViaChannel(
                    $customer,
                    $serviceLocation,
                    $channel,
                    $notificationType,
                    $data
                );
                $results[$channel] = $result;
            }

            return ['success' => true, 'channels' => $results];

        } catch (\Exception $e) {
            Log::error('Customer notification error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send notification via specific channel to user
     */
    private function sendNotificationViaChannel(User $user, string $channel, string $notificationType, $data): array
    {
        // Get template
        $template = NotificationTemplate::where('name', $notificationType . '_employee_' . $channel)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return ['success' => false, 'error' => 'Template not found'];
        }

        // Process template based on notification type
        $message = $this->processTemplate($template->content, $data, 'user');

        // Create notification log
        $notificationLog = NotificationLog::create([
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'service_location_id' => $data instanceof Ticket ? $data->service_location_id : null,
            'notification_type' => $notificationType,
            'channel' => $channel,
            'message_content' => $message,
            'status' => 'pending'
        ]);

        // Send via channel
        switch ($channel) {
            case 'whatsapp':
                $phoneNumber = $user->whatsapp_number ?: $user->phone_number;
                return $this->fontteService->sendMessage($phoneNumber, $message, $notificationLog);
                
            case 'telegram':
                // TODO: Implement telegram service
                return ['success' => false, 'error' => 'Telegram not implemented yet'];
                
            default:
                return ['success' => false, 'error' => 'Unknown channel: ' . $channel];
        }
    }

    /**
     * Send notification via specific channel to customer
     */
    private function sendCustomerNotificationViaChannel(Customer $customer, ServiceLocation $serviceLocation, string $channel, string $notificationType, $data): array
    {
        // Get template
        $template = NotificationTemplate::where('name', $notificationType . '_' . $channel)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return ['success' => false, 'error' => 'Template not found'];
        }

        // Process template
        $message = $this->processTemplate($template->content, $data, 'customer', $customer, $serviceLocation);

        // Create notification log
        $notificationLog = NotificationLog::create([
            'notifiable_type' => Customer::class,
            'notifiable_id' => $customer->id,
            'service_location_id' => $serviceLocation->id,
            'notification_type' => $notificationType,
            'channel' => $channel,
            'message_content' => $message,
            'status' => 'pending'
        ]);

        // Send via channel
        switch ($channel) {
            case 'whatsapp':
                $phoneNumber = $customer->whatsapp_number ?: $customer->phone;
                return $this->fontteService->sendMessage($phoneNumber, $message, $notificationLog);
                
            case 'telegram':
                // TODO: Implement telegram service
                return ['success' => false, 'error' => 'Telegram not implemented yet'];
                
            default:
                return ['success' => false, 'error' => 'Unknown channel: ' . $channel];
        }
    }

    /**
     * Process notification template
     */
    private function processTemplate(string $template, $data, string $recipientType, $customer = null, $serviceLocation = null): string
    {
        if ($data instanceof Ticket) {
            if ($recipientType === 'user') {
                return NotificationTemplateService::processTicketAssignmentTemplate($template, $data);
            } else {
                return NotificationTemplateService::processTechnicianAssignmentTemplate($template, $data);
            }
        }

        // Handle other data types...
        return $template;
    }
}