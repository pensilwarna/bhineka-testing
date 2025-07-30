<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

class UrlService
{
    /**
     * Generate mini URL untuk ticket
     */
    public static function generateTicketUrl($ticket): string
    {
        $encryptedId = Crypt::encryptString($ticket->id);
        return URL::to('/t/' . $encryptedId);
    }
    
    /**
     * Generate mini URL untuk maintenance
     */
    public static function generateMaintenanceUrl($maintenanceId): string
    {
        $encryptedId = Crypt::encryptString($maintenanceId);
        return URL::to('/m/' . $encryptedId);
    }
    
    /**
     * Generate Google Maps URL dari latitude & longitude
     */
    public static function generateMapsUrl($latitude, $longitude): string
    {
        if (!$latitude || !$longitude) {
            return 'Location not available';
        }
        
        return "https://maps.google.com/maps?q={$latitude},{$longitude}";
    }
    
    /**
     * Generate Google Maps URL dengan label
     */
    public static function generateMapsUrlWithLabel($latitude, $longitude, $label = null): string
    {
        if (!$latitude || !$longitude) {
            return 'Location not available';
        }
        
        if ($label) {
            $encodedLabel = urlencode($label);
            return "https://www.google.com/maps/search/?api=1&query={$latitude},{$longitude}&query_place_id={$encodedLabel}";
        }
        
        return self::generateMapsUrl($latitude, $longitude);
    }
    
    /**
     * Generate short URL using bit.ly or similar (optional)
     */
    public static function shortenUrl($longUrl): string
    {
        // Implementasi URL shortener jika diperlukan
        // Untuk sementara return original URL
        return $longUrl;
    }
}