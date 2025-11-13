<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Http\Resources\AdminResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Display admins page (backend view).
     */
    public function index()
    {
        // Return the admin management view
        return view('admins.index', [
            'page_title' => 'Admins',
            'active_menu' => 'users',
        ]);
    }

    /**
    * Search admins by keyword (enhanced with CI3 compatibility).
    */
    public function search(Request $request)
    {
        try {
            $data = $request->validate([
                'keyword' => 'nullable|string',
                'limit' => 'nullable|integer|min:1|max:1000',
                'offset' => 'nullable|integer|min:0',
                'order_by' => 'nullable|string',
            ]);

            $keyword = $data['keyword'] ?? '';
            $limit = $data['limit'] ?? 1000;
            $offset = $data['offset'] ?? 0;
            $orderBy = $data['order_by'] ?? 'updated_at DESC';

            // ✅ Use the new searchAdmins method
            $admins = User::searchAdmins(
                $keyword,
                $limit,
                $offset,
                $orderBy,
                $asArray = false // Return as collection for resources
            );

            // Get total count for pagination
            $total = User::searchCount($keyword, 'admin');

            return response()->json([
                'success' => true,
                'admins' => AdminResource::collection($admins),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Admin search failed', [
                'keyword' => $data['keyword'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while searching admins.'
            ], 500);
        }
    }


    /**
     * Store a new admin (simplified using saveAdmin).
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'first_name' => 'required|string|max:256',
                'last_name' => 'required|string|max:512',
                'email' => 'required|email|unique:users,email',
                'mobile_phone_number' => 'required|string|max:128',
                'work_phone_number' => 'nullable|string|max:128',
                'address' => 'nullable|string|max:256',
                'city' => 'nullable|string|max:256',
                'state' => 'nullable|string|max:128',
                'zip_code' => 'nullable|string|max:64',
                'notes' => 'nullable|string',
                'timezone' => 'nullable|string|max:256',
                'language' => 'nullable|string|max:256',
                'ldap_dn' => 'nullable|string|max:512',
                'settings' => 'required|array',
                'settings.username' => 'required|string|max:256|unique:user_settings,username',
                'settings.password' => 'required|string|min:7',
                'settings.notifications' => 'nullable|boolean',
                'settings.calendar_view' => 'nullable|in:default,table',
            ]);

            // ✅ Use the new saveAdmin method (includes validation)
            $adminId = User::saveAdmin($data);

            // Load the created admin with relationships
            $admin = User::findWithSettings($adminId);

            return response()->json([
                'success' => true,
                'id' => $adminId,
                'admin' => new AdminResource($admin)
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while creating the admin.'
            ], 500);
        }
    }

    // ...existing code...

    /**
    * Find a specific admin (enhanced).
    */
    public function find(Request $request)
    {
        try {
            $adminId = $request->input('admin_id');

            // ✅ Use the new findAdmin method
            // Returns as model by default, but can return as array for CI3 compatibility
            $asArray = $request->input('as_array', false);
            $admin = User::findAdmin($adminId, $asArray);

            if ($asArray) {
                // Return raw array (CI3 format)
                return response()->json([
                    'success' => true,
                    'admin' => $admin
                ]);
            } else {
                // Return as resource (Laravel format)
                return response()->json([
                    'success' => true,
                    'admin' => new AdminResource($admin)
                ]);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while finding the admin.'
            ], 500);
        }
    }

    /**
    * Get a specific field value from an admin (enhanced).
    */
    public function getValue(Request $request)
    {
        try {
            $data = $request->validate([
                'admin_id' => 'required|integer',
                'field' => 'required|string',
            ]);

            $adminId = $data['admin_id'];
            $field = $data['field'];

            // ✅ Use the enhanced value method (with proper error handling)
            $value = User::value($adminId, $field);

            return response()->json([
                'success' => true,
                'field' => $field,
                'value' => $value
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            // This catches all the specific error messages from value()
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            Log::error('Admin getValue failed', [
                'admin_id' => $data['admin_id'] ?? null,
                'field' => $data['field'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while getting the field value.'
            ], 500);
        }
    }

    /**
    * Get multiple field values from an admin.
    */
    public function getValues(Request $request)
    {
        try {
            $data = $request->validate([
                'admin_id' => 'required|integer',
                'fields' => 'required|array',
                'fields.*' => 'required|string',
            ]);

            $adminId = $data['admin_id'];
            $fields = $data['fields'];

            // ✅ Use the new values method
            $values = User::values($adminId, $fields);

            return response()->json([
                'success' => true,
                'values' => $values
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while getting field values.'
            ], 500);
        }
    }

    /**
    * Check if a field exists for an admin.
    */
    public function hasField(Request $request)
    {
        try {
            $data = $request->validate([
                'admin_id' => 'required|integer',
                'field' => 'required|string',
            ]);

            $adminId = $data['admin_id'];
            $field = $data['field'];

            // ✅ Use the new hasField method
            $exists = User::hasField($adminId, $field);

            return response()->json([
                'success' => true,
                'field' => $field,
                'exists' => $exists
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while checking field existence.'
            ], 500);
        }
    }

    /**
    * Get all available fields for an admin.
    */
    public function getAvailableFields(Request $request)
    {
        try {
            $data = $request->validate([
                'admin_id' => 'required|integer',
            ]);

            $adminId = $data['admin_id'];

            // ✅ Use the new getAvailableFields method
            $fields = User::getAvailableFields($adminId);

            return response()->json([
                'success' => true,
                'fields' => $fields
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while getting available fields.'
            ], 500);
        }
    }

    /**
    * Get admins with flexible criteria (CI3 compatibility).
    */
    public function get(Request $request)
    {
        try {
            $data = $request->validate([
                'where' => 'nullable|array', // WHERE conditions as array
                'where_raw' => 'nullable|string', // Raw WHERE string (use with caution)
                'limit' => 'nullable|integer|min:1|max:1000',
                'offset' => 'nullable|integer|min:0',
                'order_by' => 'nullable|string',
            ]);

            // Determine WHERE conditions (array or raw string)
            $where = $data['where'] ?? $data['where_raw'] ?? null;
            $limit = $data['limit'] ?? 1000;
            $offset = $data['offset'] ?? 0;
            $orderBy = $data['order_by'] ?? 'updated_at DESC';

            // ✅ Use the new getAdmins method
            $admins = User::getAdmins(
                $where,
                $limit,
                $offset,
                $orderBy,
                $asArray = false // Return as collection for resources
            );

            // Get total count for pagination
            $total = User::countUsers($where, 'admin');

            return response()->json([
                'success' => true,
                'admins' => AdminResource::collection($admins),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Admin get failed', [
                'where' => $data['where'] ?? $data['where_raw'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while getting admins.'
            ], 500);
        }
    }

    /**
    * Get multiple admins by IDs.
    */
    public function findMany(Request $request)
    {
        try {
            $data = $request->validate([
                'admin_ids' => 'required|array',
                'admin_ids.*' => 'required|integer|exists:users,id',
            ]);

            // Get admins and verify they're all admins
            $admins = User::findMany($data['admin_ids']);

            // Filter only admins
            $admins = $admins->filter(fn($user) => $user->isAdmin());

            return response()->json([
                'success' => true,
                'admins' => AdminResource::collection($admins)
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while finding admins.'
            ], 500);
        }
    }

    /**
    * Update an existing admin (enhanced error handling).
    */
    public function update(Request $request, $id)
    {
        try {
            // Validate request data
            $data = $request->validate([
                'first_name' => 'required|string|max:256',
                'last_name' => 'required|string|max:512',
                'email' => 'required|email|unique:users,email,' . $id,
                'mobile_phone_number' => 'required|string|max:128',
                'work_phone_number' => 'nullable|string|max:128',
                'address' => 'nullable|string|max:256',
                'city' => 'nullable|string|max:256',
                'state' => 'nullable|string|max:128',
                'zip_code' => 'nullable|string|max:64',
                'notes' => 'nullable|string',
                'timezone' => 'nullable|string|max:256',
                'language' => 'nullable|string|max:256',
                'ldap_dn' => 'nullable|string|max:512',
                'settings' => 'nullable|array',
                'settings.username' => 'nullable|string|max:256',
                'settings.password' => 'nullable|string|min:7',
                'settings.notifications' => 'nullable|boolean',
                'settings.calendar_view' => 'nullable|in:default,table',
            ]);

            // Add ID to data for saveAdmin
            $data['id'] = $id;

            // ✅ Use the enhanced saveAdmin method
            $adminId = User::saveAdmin($data);

            // Load the updated admin with relationships
            $admin = User::findWithSettings($adminId);

            return response()->json([
                'success' => true,
                'id' => $adminId,
                'message' => 'Admin updated successfully.',
                'admin' => new AdminResource($admin)
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Admin update failed', [
                'admin_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred while updating the admin.'
            ], 500);
        }
    }

    /**
    * Delete an admin (enhanced with batch delete support).
    */
    public function destroy(Request $request, $id = null)
    {
        try {
            // Support both single and batch delete
            if ($id) {
                // Single delete from route parameter
                User::deleteUser($id, 'admin');

                return response()->json([
                    'success' => true,
                    'message' => 'Admin deleted successfully.'
                ]);
            } else {
                // Batch delete from request body
                $data = $request->validate([
                    'admin_ids' => 'required|array',
                    'admin_ids.*' => 'required|integer|exists:users,id',
                ]);

                $results = User::deleteUsers($data['admin_ids'], 'admin');

                $successCount = count(array_filter($results, fn($r) => $r['success']));
                $failCount = count($results) - $successCount;

                return response()->json([
                    'success' => $failCount === 0,
                    'message' => "{$successCount} admin(s) deleted successfully" .
                            ($failCount > 0 ? ", {$failCount} failed." : "."),
                    'results' => $results
                ]);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Admin not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Admin deletion failed', [
                'admin_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred while deleting the admin.'
            ], 500);
        }
    }

    /**
     * Get admin settings.
     */
    public function getSettings(Request $request)
    {
        try {
            $adminId = $request->input('admin_id');

            // ✅ Use the new getSettings method
            $settings = User::getSettings($adminId);

            return response()->json([
                'success' => true,
                'settings' => $settings
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Admin not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while getting admin settings.'
            ], 500);
        }
    }



    /**
    * Get a specific admin setting value (CI3 compatibility).
    */
    public function getSetting(Request $request)
    {
        try {
            $data = $request->validate([
                'admin_id' => 'required|integer',
                'setting_name' => 'required|string',
            ]);

            $adminId = $data['admin_id'];
            $settingName = $data['setting_name'];

            // ✅ Use the strict CI3-compatible method
            $value = User::getUserSetting($adminId, $settingName);

            return response()->json([
                'success' => true,
                'setting_name' => $settingName,
                'value' => $value // Always a string (CI3 compatibility)
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\RuntimeException $e) {
            // This catches the CI3-style "setting not found" error
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            Log::error('Admin getSetting failed', [
                'admin_id' => $data['admin_id'] ?? null,
                'setting_name' => $data['setting_name'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while getting the setting value.'
            ], 500);
        }
    }

    /**
    * Get multiple admin setting values.
    */
    public function getSettingValues(Request $request)
    {
        try {
            $data = $request->validate([
                'admin_id' => 'required|integer',
                'setting_names' => 'required|array',
                'setting_names.*' => 'required|string',
                'strict' => 'nullable|boolean', // If true, throw on missing
            ]);

            $adminId = $data['admin_id'];
            $settingNames = $data['setting_names'];
            $strict = $data['strict'] ?? false;

            // ✅ Use the batch method
            $values = User::getUserSettings($adminId, $settingNames, $strict);

            return response()->json([
                'success' => true,
                'values' => $values
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while getting setting values.'
            ], 500);
        }
    }

    /**
    * Check if an admin setting exists.
    */
    public function hasUserSetting(Request $request)
    {
        try {
            $data = $request->validate([
                'admin_id' => 'required|integer',
                'setting_name' => 'required|string',
            ]);

            $adminId = $data['admin_id'];
            $settingName = $data['setting_name'];

            // ✅ Use the hasUserSetting method
            $exists = User::hasUserSetting($adminId, $settingName);

            return response()->json([
                'success' => true,
                'setting_name' => $settingName,
                'exists' => $exists
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while checking setting existence.'
            ], 500);
        }
    }



    /**
     * Update admin settings.
     */
    public function updateSettings(Request $request)
    {
        try {
            $data = $request->validate([
                'admin_id' => 'required|integer|exists:users,id',
                'settings' => 'required|array',
                'settings.username' => 'nullable|string|max:256',
                'settings.password' => 'nullable|string|min:7',
                'settings.notifications' => 'nullable|boolean',
                'settings.calendar_view' => 'nullable|in:default,table',
                'settings.google_sync' => 'nullable|boolean',
                'settings.google_token' => 'nullable|string',
                'settings.google_calendar' => 'nullable|string|max:128',
                'settings.caldav_sync' => 'nullable|boolean',
                'settings.caldav_url' => 'nullable|string|max:512',
                'settings.caldav_username' => 'nullable|string|max:256',
                'settings.caldav_password' => 'nullable|string|max:256',
                'settings.working_plan' => 'nullable|array',
                'settings.working_plan_exceptions' => 'nullable|array',
            ]);

            $adminId = $data['admin_id'];
            $settings = $data['settings'];

            // Hash password if provided
            if (!empty($settings['password'])) {
                $user = User::findOrFail($adminId);
                $user->password = Hash::make($settings['password']);
                $user->save();
                unset($settings['password']); // Don't save in settings table
            }

            // ✅ Use the new setSettings method
            User::setSettings($adminId, $settings);

            // Get updated settings
            $updatedSettings = User::getSettings($adminId);

            return response()->json([
                'success' => true,
                'settings' => $updatedSettings
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while updating admin settings.'
            ], 500);
        }
    }

    /**
     * Update a single admin setting.
     */
    public function updateSetting(Request $request)
    {
        try {
            $data = $request->validate([
                'admin_id' => 'required|integer|exists:users,id',
                'setting_name' => 'required|string',
                'setting_value' => 'required',
            ]);

            $adminId = $data['admin_id'];
            $settingName = $data['setting_name'];
            $settingValue = $data['setting_value'];

            // ✅ Use the new setSetting method
            User::setSetting($adminId, $settingName, $settingValue);

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully.',
                'setting' => [
                    $settingName => User::getSetting($adminId, $settingName)
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while updating the setting.'
            ], 500);
        }
    }

    /**
    * Validate admin username (CI3 compatibility).
    */
    public function validateUsername(Request $request)
    {
        try {
            $data = $request->validate([
                'username' => 'required|string|max:255',
                'admin_id' => 'nullable|integer', // Exclude this admin from check
            ]);

            $username = $data['username'];
            $adminId = $data['admin_id'] ?? null;

            // ✅ Use the validateUsername method
            $isValid = User::validateUsername($username, $adminId);

            return response()->json([
                'success' => true,
                'username' => $username,
                'is_valid' => $isValid,
                'is_unique' => $isValid, // CI3 compatibility
                'message' => $isValid
                    ? 'Username is available'
                    : 'Username is already taken'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Username validation failed', [
                'username' => $data['username'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while validating username.'
            ], 500);
        }
    }

    /**
    * Check if username exists.
    */
    public function checkUsername(Request $request)
    {
        try {
            $data = $request->validate([
                'username' => 'required|string|max:255',
            ]);

            $username = $data['username'];
            $exists = User::usernameExists($username);

            return response()->json([
                'success' => true,
                'username' => $username,
                'exists' => $exists,
                'available' => !$exists
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while checking username.'
            ], 500);
        }
    }

    /**
    * Find admin by username.
    */
    public function findByUsername(Request $request)
    {
        try {
            $data = $request->validate([
                'username' => 'required|string|max:255',
            ]);

            $username = $data['username'];
            $admin = User::findByUsername($username);

            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'error' => 'Admin not found with username: ' . $username
                ], 404);
            }

            // Verify it's an admin
            if (!$admin->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'error' => 'User is not an admin.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'admin' => new AdminResource($admin)
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while finding admin.'
            ], 500);
        }
    }
}
