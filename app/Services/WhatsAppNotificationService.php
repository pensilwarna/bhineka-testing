<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Ticket;
use App\Models\User;

class WhatsAppNotificationService
{
    private $apiUrl;
    private $token;

    public function __construct()
    {
        $this->apiUrl = config('services.fonnte.api_url', 'https://api.fonnte.com/send');
        $this->token = config('services.fonnte.token');
    }

    /**
     * Send WhatsApp notification to technician about new ticket
     */
    public function notifyTechnicianNewTicket(Ticket $ticket, User $technician): bool
    {
        $message = $this->buildNewTicketMessage($ticket);
        
        return $this->sendMessage($technician->phone, $message, [
            'ticket_id' => $ticket->id,
            'technician_id' => $technician->id,
            'type' => 'new_ticket'
        ]);
    }

    /**
     * Send WhatsApp notification to multiple technicians
     */
    public function notifyTechniciansNewTicket(Ticket $ticket): array
    {
        $results = [];
        
        foreach ($ticket->technicians as $technician) {
            $results[$technician->id] = $this->notifyTechnicianNewTicket($ticket, $technician);
        }
        
        // Update ticket notification log
        $ticket->update([
            'notification_sent_at' => now(),
            'notification_log' => array_merge($ticket->notification_log ?? [], [
                'new_ticket_notification' => [
                    'sent_at' => now(),
                    'results' => $results,
                    'message' => $this->buildNewTicketMessage($ticket)
                ]
            ])
        ]);
        
        return $results;
    }

    /**
     * Alias for notifyTechniciansNewTicket (for backward compatibility)
     */
    public function notifyTicketAssigned(Ticket $ticket): array
    {
        return $this->notifyTechniciansNewTicket($ticket);
    }

    /**
     * Send reminder notification for pending tickets
     */
    public function sendTicketReminder(Ticket $ticket, User $technician): bool
    {
        $message = $this->buildReminderMessage($ticket);
        
        return $this->sendMessage($technician->phone, $message, [
            'ticket_id' => $ticket->id,
            'technician_id' => $technician->id,
            'type' => 'reminder'
        ]);
    }

    /**
     * Send completion confirmation to customer
     */
    public function notifyCustomerCompletion(Ticket $ticket): bool
    {
        $message = $this->buildCompletionMessage($ticket);
        
        return $this->sendMessage($ticket->customer->phone, $message, [
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer->id,
            'type' => 'completion'
        ]);
    }
    
    /**
     * Notify supervisor when technician checks in
     */
    public function notifySupervisorCheckin(Ticket $ticket): bool
    {
        if (!$ticket->supervisor) {
            Log::warning('Cannot send check-in notification: No supervisor assigned', [
                'ticket_id' => $ticket->id,
                'ticket_code' => $ticket->kode
            ]);
            return false;
        }
        
        $message = $this->buildCheckinMessage($ticket);
        
        return $this->sendMessage($ticket->supervisor->phone, $message, [
            'ticket_id' => $ticket->id,
            'supervisor_id' => $ticket->supervisor->id,
            'type' => 'checkin'
        ]);
    }

    /**
     * Build new ticket notification message
     */
    private function buildNewTicketMessage(Ticket $ticket): string
    {
        $priorityEmoji = $this->getPriorityEmoji($ticket->priority);
        $typeText = $this->getTicketTypeText($ticket->ticket_type);
        
        return "🎫 *TIKET BARU* {$priorityEmoji}\n\n" .
               "📋 *Kode:* {$ticket->kode}\n" .
               "🔧 *Jenis:* {$typeText}\n" .
               "👤 *Customer:* {$ticket->customer->name}\n" .
               "📍 *Lokasi:* {$ticket->serviceLocation->address}\n" .
               "⚡ *Prioritas:* " . ucfirst($ticket->priority) . "\n" .
               "📝 *Deskripsi:* {$ticket->description}\n\n" .
               "⏰ *Dibuat:* " . $ticket->created_at->format('d/m/Y H:i') . "\n\n" .
               "Silakan buka aplikasi untuk melihat detail dan melakukan check-in.\n\n" .
               "_Sistem Manajemen Tiket ISP_";
    }

    /**
     * Build reminder message
     */
    private function buildReminderMessage(Ticket $ticket): string
    {
        $hoursAgo = $ticket->created_at->diffInHours(now());
        
        return "⏰ *PENGINGAT TIKET*\n\n" .
               "📋 *Kode:* {$ticket->kode}\n" .
               "�  *Customer:* {$ticket->customer->name}\n" .
               "📍 *Lokasi:* {$ticket->serviceLocation->address}\n" .
               "🕐 *Sudah:* {$hoursAgo} jam yang lalu\n\n" .
               "Tiket ini masih menunggu penanganan. Mohon segera lakukan check-in dan mulai pengerjaan.\n\n" .
               "_Sistem Manajemen Tiket ISP_";
    }

    /**
     * Build completion message for customer
     */
    private function buildCompletionMessage(Ticket $ticket): string
    {
        $typeText = $this->getTicketTypeText($ticket->ticket_type);
        
        return "✅ *PEKERJAAN SELESAI*\n\n" .
               "Halo {$ticket->customer->name},\n\n" .
               "Pekerjaan *{$typeText}* di lokasi Anda telah selesai dikerjakan.\n\n" .
               "📋 *Kode Tiket:* {$ticket->kode}\n" .
               "📍 *Lokasi:* {$ticket->serviceLocation->address}\n" .
               "⏱️ *Selesai:* " . $ticket->completed_at->format('d/m/Y H:i') . "\n\n" .
               "Terima kasih atas kepercayaan Anda. Jika ada kendala, silakan hubungi customer service kami.\n\n" .
               "_Tim Teknis ISP_";
    }
    
    /**
     * Build check-in message for supervisor
     */
    private function buildCheckinMessage(Ticket $ticket): string
    {
        $priorityEmoji = $this->getPriorityEmoji($ticket->priority);
        $typeText = $this->getTicketTypeText($ticket->ticket_type);
        $technicians = $ticket->technicians->pluck('name')->join(', ');
        
        // Format jarak check-in
        $distance = $ticket->checkin_distance_meters ?? 0;
        $distanceText = number_format($distance, 1, ',', '.') . ' meter';
        
        // Format waktu check-in
        $checkinTime = $ticket->checkin_time ? $ticket->checkin_time->format('d/m/Y H:i') : 'N/A';
        
        return "🔔 *CHECK-IN TEKNISI* {$priorityEmoji}\n\n" .
               "Teknisi telah melakukan check-in pada tiket:\n\n" .
               "📋 *Kode:* {$ticket->kode}\n" .
               "🔧 *Jenis:* {$typeText}\n" .
               "👤 *Customer:* {$ticket->customer->name}\n" .
               "📍 *Lokasi:* {$ticket->serviceLocation->address}\n" .
               "👨‍🔧 *Teknisi:* {$technicians}\n" .
               "🧭 *Jarak:* {$distanceText}\n" .
               "⏰ *Waktu Check-in:* {$checkinTime}\n\n" .
               "Pekerjaan telah dimulai dan status tiket diubah menjadi 'In Progress'.\n\n" .
               "_Sistem Manajemen Tiket ISP_";
    }

    /**
     * Send WhatsApp message via Fonnte API
     */
    private function sendMessage(string $phone, string $message, array $metadata = []): bool
    {
        try {
            // Clean phone number (remove +, spaces, etc.)
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            
            // Add country code if not present
            if (!str_starts_with($cleanPhone, '62')) {
                $cleanPhone = '62' . ltrim($cleanPhone, '0');
            }

            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->post($this->apiUrl, [
                'target' => $cleanPhone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $success = $response->successful() && $response->json('status') === true;
            
            // Log the attempt
            Log::info('WhatsApp notification sent', [
                'phone' => $cleanPhone,
                'success' => $success,
                'response' => $response->json(),
                'metadata' => $metadata
            ]);
            
            return $success;
            
        } catch (\Exception $e) {
            Log::error('WhatsApp notification failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'metadata' => $metadata
            ]);
            
            return false;
        }
    }

    /**
     * Get priority emoji
     */
    private function getPriorityEmoji(string $priority): string
    {
        return match($priority) {
            'urgent' => '�',
            'high' => '🔴',
            'normal' => '🟡',
            'low' => '🟢',
            default => '⚪'
        };
    }

    /**
     * Get ticket type text in Indonesian
     */
    private function getTicketTypeText(string $ticketType): string
    {
        return match($ticketType) {
            'new_installation' => 'Pemasangan Baru',
            'repair' => 'Perbaikan',
            'reactivation' => 'Reaktivasi',
            'upgrade' => 'Upgrade Paket',
            'downgrade' => 'Downgrade Paket',
            'relocation' => 'Relokasi',
            default => ucfirst(str_replace('_', ' ', $ticketType))
        };
    }
}