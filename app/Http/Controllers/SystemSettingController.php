<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class SystemSettingController extends Controller
{
    /**
     * Display a listing of the system settings.
     */
    public function index()
    {
        $this->authorize('manage-system-settings'); // Define this permission for Manager, Owner, Super-Admin
        $settings = SystemSetting::orderBy('category')->get()->groupBy('category');
        return view('asset-management.settings.index', compact('settings'));
    }

    /**
     * Update the specified system settings in storage.
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorize('manage-system-settings');

        // Dynamically validate based on existing settings
        $rules = [];
        $messages = [];
        foreach ($request->all() as $key => $value) {
            $setting = SystemSetting::where('key_name', $key)->first();
            if ($setting) {
                switch ($setting->data_type) {
                    case 'integer':
                        $rules[$key] = 'required|integer';
                        $messages["$key.integer"] = "The :attribute must be an integer.";
                        break;
                    case 'decimal':
                        $rules[$key] = 'required|numeric';
                        $messages["$key.numeric"] = "The :attribute must be a number.";
                        break;
                    case 'boolean':
                        $rules[$key] = 'required|boolean';
                        $messages["$key.boolean"] = "The :attribute must be true or false.";
                        break;
                    case 'json':
                        $rules[$key] = 'nullable|json';
                        $messages["$key.json"] = "The :attribute must be a valid JSON string.";
                        break;
                    default: // string
                        $rules[$key] = 'nullable|string';
                        break;
                }
            }
        }

        $request->validate($rules, $messages);

        try {
            DB::beginTransaction();
            foreach ($request->all() as $key => $value) {
                $setting = SystemSetting::where('key_name', $key)->first();
                if ($setting) {
                    // Convert boolean value from string 'true'/'false' to actual boolean
                    if ($setting->data_type === 'boolean') {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    }
                    $setting->update(['value' => $value]);
                }
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => 'System settings updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to update settings: ' . $e->getMessage()], 500);
        }
    }
}