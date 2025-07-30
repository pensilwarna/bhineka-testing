<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Customer;
use App\Models\ServiceLocation;
use Carbon\Carbon;

class NotificationTemplateService
{
    /**
     * Process template untuk ticket assignment ke technician
     */
    public static function processTicketAssignmentTemplate($template, Ticket $ticket): string
    {
        $customer = $ticket->customer;
        $serviceLocation = $ticket->serviceLocation;
        $technician = $ticket->technicians->first();
        
        $placeholders = [
            '{ticket_id}' => $ticket->id,
            '{ticket_code}' => $ticket->kode ?? $ticket->id, // Gunakan kode jika ada
            '{customer_name}' => $customer->name,
            '{customer_phone}' => $customer->phone,
            '{service_address}' => $serviceLocation->address,
            '{priority}' => ucfirst($ticket->priority),
            '{description}' => $ticket->description,
            '{assigned_at}' => Carbon::parse($ticket->assigned_at)->format('d M Y H:i'),
            '{ticket_url}' => UrlService::generateTicketUrl($ticket),
            '{maps_url}' => UrlService::generateMapsUrl($serviceLocation->latitude, $serviceLocation->longitude),
            '{company_name}' => config('app.company_name', 'PT. Your Company')
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * Process template untuk technician assignment ke customer
     */
    public static function processTechnicianAssignmentTemplate($template, Ticket $ticket): string
    {
        $customer = $ticket->customer;
        $serviceLocation = $ticket->serviceLocation;
        $technician = $ticket->technicians->first();
        
        $placeholders = [
            '{customer_name}' => $customer->name,
            '{technician_name}' => $technician ? $technician->name : 'Tim Teknisi',
            '{technician_phone}' => $technician ? $technician->phone_number : '-',
            '{service_address}' => $serviceLocation->address,
            '{estimated_arrival}' => 'Akan dikonfirmasi',
            '{maps_url}' => UrlService::generateMapsUrl($serviceLocation->latitude, $serviceLocation->longitude),
            '{company_name}' => config('app.company_name', 'PT. Your Company')
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * Process template untuk payment reminder
     */
    public static function processPaymentReminderTemplate($template, Customer $customer, ServiceLocation $serviceLocation, $amount, $dueDate): string
    {
        $placeholders = [
            '{customer_name}' => $customer->name,
            '{service_address}' => $serviceLocation->address,
            '{amount}' => number_format($amount, 0, ',', '.'),
            '{due_date}' => Carbon::parse($dueDate)->format('d M Y'),
            '{maps_url}' => UrlService::generateMapsUrl($serviceLocation->latitude, $serviceLocation->longitude),
            '{company_name}' => config('app.company_name', 'PT. Your Company')
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * Process template untuk system maintenance
     */
    public static function processMaintenanceTemplate($template, $maintenanceData): string
    {
        $placeholders = [
            '{maintenance_date}' => $maintenanceData['date'],
            '{maintenance_time}' => $maintenanceData['time'],
            '{duration}' => $maintenanceData['duration'],
            '{description}' => $maintenanceData['description'],
            '{maintenance_url}' => UrlService::generateMaintenanceUrl($maintenanceData['id']),
            '{company_name}' => config('app.company_name', 'PT. Your Company')
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
}