<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\ServiceLocation;
use App\Models\CustomerNotificationPreference;

class NotificationController extends Controller
{
    public function customerNotifications()
    {
        $customers = Customer::with(['serviceLocations', 'notificationPreferences'])->get();
        return view('admin.notifications.customers', compact('customers'));
    }

    public function enableCustomerNotifications(Request $request, Customer $customer)
    {
        $request->validate([
            'service_location_id' => 'required|exists:service_locations,id',
            'notification_types' => 'required|array',
            'notification_types.*' => 'in:technician_assigned,payment_reminder',
            'channels' => 'required|array',
            'channels.*' => 'in:telegram',
            'telegram_chat_id' => 'required_if:channels,telegram'
        ]);

        // Update customer telegram_chat_id if provided
        if ($request->telegram_chat_id) {
            $customer->update([
                'telegram_chat_id' => $request->telegram_chat_id,
                'notifications_enabled' => true
            ]);
        }

        // Enable notifications for selected service location
        foreach ($request->notification_types as $type) {
            CustomerNotificationPreference::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'service_location_id' => $request->service_location_id,
                    'notification_type' => $type
                ],
                [
                    'channels' => $request->channels,
                    'is_active' => true
                ]
            );
        }

        return response()->json(['message' => 'Customer notifications enabled successfully!']);
    }
}