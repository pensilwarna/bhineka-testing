<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\WhatsAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTicketNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ticket;
    protected $notificationType;

    /**
     * Create a new job instance.
     */
    public function __construct(Ticket $ticket, string $notificationType = 'new_ticket')
    {
        $this->ticket = $ticket;
        $this->notificationType = $notificationType;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppNotificationService $whatsappService): void
    {
        try {
            // Tambahkan informasi prioritas ke log
            $priorityInfo = '';
            if ($this->ticket->priority) {
                $priorityInfo = " [Prioritas: {$this->ticket->priority}]";
            }
            
            switch ($this->notificationType) {
                case 'new_ticket':
                    $results = $whatsappService->notifyTechniciansNewTicket($this->ticket);
                    
                    // Update ticket dengan informasi notifikasi
                    $this->ticket->update([
                        'notification_sent_at' => now(),
                        'notification_log' => array_merge($this->ticket->notification_log ?? [], [
                            'new_ticket' => [
                                'sent_at' => now()->toDateTimeString(),
                                'results' => $results,
                                'recipients' => $this->ticket->technicians->pluck('name', 'id')->toArray()
                            ]
                        ])
                    ]);
                    
                    Log::info("Notifikasi tiket baru{$priorityInfo} dikirim ke teknisi", [
                        'ticket_id' => $this->ticket->id,
                        'ticket_code' => $this->ticket->kode,
                        'priority' => $this->ticket->priority,
                        'technicians' => $this->ticket->technicians->pluck('name')->toArray(),
                        'results' => $results
                    ]);
                    break;

                case 'reminder':
                    $reminderResults = [];
                    foreach ($this->ticket->technicians as $technician) {
                        $reminderResults[$technician->id] = $whatsappService->sendTicketReminder($this->ticket, $technician);
                    }
                    
                    // Update ticket dengan informasi reminder
                    $this->ticket->update([
                        'notification_log' => array_merge($this->ticket->notification_log ?? [], [
                            'reminder_' . now()->timestamp => [
                                'sent_at' => now()->toDateTimeString(),
                                'results' => $reminderResults,
                                'recipients' => $this->ticket->technicians->pluck('name', 'id')->toArray()
                            ]
                        ])
                    ]);
                    
                    Log::info("Pengingat tiket{$priorityInfo} dikirim ke teknisi", [
                        'ticket_id' => $this->ticket->id,
                        'ticket_code' => $this->ticket->kode,
                        'priority' => $this->ticket->priority,
                        'technicians' => $this->ticket->technicians->pluck('name')->toArray(),
                        'results' => $reminderResults
                    ]);
                    break;

                case 'completion':
                    $result = $whatsappService->notifyCustomerCompletion($this->ticket);
                    
                    // Update ticket dengan informasi notifikasi penyelesaian
                    $this->ticket->update([
                        'notification_log' => array_merge($this->ticket->notification_log ?? [], [
                            'completion' => [
                                'sent_at' => now()->toDateTimeString(),
                                'result' => $result,
                                'recipient' => [
                                    'id' => $this->ticket->customer->id,
                                    'name' => $this->ticket->customer->name,
                                    'phone' => $this->ticket->customer->phone
                                ]
                            ]
                        ])
                    ]);
                    
                    Log::info("Notifikasi penyelesaian tiket dikirim ke pelanggan", [
                        'ticket_id' => $this->ticket->id,
                        'ticket_code' => $this->ticket->kode,
                        'customer' => $this->ticket->customer->name,
                        'result' => $result
                    ]);
                    break;
                    
                case 'checkin':
                    // Notifikasi supervisor saat teknisi melakukan check-in
                    $result = $whatsappService->notifySupervisorCheckin($this->ticket);
                    
                    // Update ticket dengan informasi notifikasi check-in
                    $this->ticket->update([
                        'notification_log' => array_merge($this->ticket->notification_log ?? [], [
                            'checkin' => [
                                'sent_at' => now()->toDateTimeString(),
                                'result' => $result,
                                'recipient' => [
                                    'id' => $this->ticket->supervisor->id,
                                    'name' => $this->ticket->supervisor->name
                                ]
                            ]
                        ])
                    ]);
                    
                    Log::info("Notifikasi check-in tiket dikirim ke supervisor", [
                        'ticket_id' => $this->ticket->id,
                        'ticket_code' => $this->ticket->kode,
                        'supervisor' => $this->ticket->supervisor->name,
                        'result' => $result
                    ]);
                    break;

                default:
                    Log::warning("Tipe notifikasi tidak dikenal: {$this->notificationType}", [
                        'type' => $this->notificationType,
                        'ticket_id' => $this->ticket->id,
                        'ticket_code' => $this->ticket->kode
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp notification', [
                'ticket_id' => $this->ticket->id,
                'type' => $this->notificationType,
                'error' => $e->getMessage()
            ]);
            
            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('WhatsApp notification job failed permanently', [
            'ticket_id' => $this->ticket->id,
            'type' => $this->notificationType,
            'error' => $exception->getMessage()
        ]);
    }
}