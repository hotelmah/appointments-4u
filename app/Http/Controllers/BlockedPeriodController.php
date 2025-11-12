<?php

namespace App\Http\Controllers;

use App\Models\BlockedPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class BlockedPeriodController extends Controller
{
    /**
     * Display a listing of blocked periods.
     */
    public function index(Request $request)
    {
        $query = BlockedPeriod::query();

        // Apply filters
        if ($request->has('status')) {
            switch ($request->status) {
                case 'active':
                    $query->active();
                    break;
                case 'upcoming':
                    $query->upcoming();
                    break;
                case 'past':
                    $query->past();
                    break;
            }
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->betweenDates($request->start_date, $request->end_date);
        }

        // Order by start datetime
        $blockedPeriods = $query->orderBy('start_datetime', 'asc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'blocked_periods' => $blockedPeriods
        ]);
    }

    /**
     * Display a specific blocked period.
     */
    public function show(int $id)
    {
        try {
            // Use Laravel's findOrFail (throws ModelNotFoundException)
            $blockedPeriod = BlockedPeriod::findOrFail($id);

            return response()->json([
                'success' => true,
                'blocked_period' => $blockedPeriod
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Blocked period not found.'
            ], 404);
        }
    }

    /**
     * Find a blocked period (CI3 compatibility endpoint).
     *
     * This endpoint maintains CI3 behavior by throwing InvalidArgumentException.
     */
    public function find(Request $request)
    {
        try {
            $blockedPeriodId = $request->input('blocked_period_id');

            // Use custom findOrThrow method for CI3 compatibility
            $blockedPeriod = BlockedPeriod::findOrThrow($blockedPeriodId);

            return response()->json([
                'success' => true,
                'blocked_period' => $blockedPeriod
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while finding the blocked period.'
            ], 500);
        }
    }

    /**
     * Store a new blocked period.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:256',
                'start_datetime' => 'required|date',
                'end_datetime' => 'required|date|after:start_datetime',
                'notes' => 'nullable|string',
            ]);

            BlockedPeriod::validateBlockedPeriodData($data);
            BlockedPeriod::validateBlockedPeriodRelationships($data);

            // Use Laravel's create() - automatically handles timestamps
            $blockedPeriod = BlockedPeriod::create($data);

            return response()->json([
                'success' => true,
                'id' => $blockedPeriod->id,
                'blocked_period' => $blockedPeriod
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
        }
    }

    /**
     * Update an existing blocked period.
     */
    public function update(Request $request, int $id)
    {
        try {
            // Use Laravel's findOrFail
            $blockedPeriod = BlockedPeriod::findOrFail($id);

            $data = $request->validate([
                'name' => 'sometimes|required|string|max:256',
                'start_datetime' => 'sometimes|required|date',
                'end_datetime' => 'sometimes|required|date|after:start_datetime',
                'notes' => 'nullable|string',
            ]);

            $dataToValidate = array_merge($blockedPeriod->toArray(), $data);
            BlockedPeriod::validateBlockedPeriodData($dataToValidate, $id);
            BlockedPeriod::validateBlockedPeriodRelationships($dataToValidate);

            // Use Laravel's update() - automatically handles updated_at
            $blockedPeriod->update($data);

            return response()->json([
                'success' => true,
                'id' => $blockedPeriod->id,
                'blocked_period' => $blockedPeriod
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Blocked period not found.'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Delete a blocked period.
     */
    public function destroy(int $id)
    {
        try {
            // Use Laravel's findOrFail and delete()
            $blockedPeriod = BlockedPeriod::findOrFail($id);
            $blockedPeriod->delete();

            return response()->json([
                'success' => true,
                'message' => 'Blocked period deleted successfully.'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Blocked period not found.'
            ], 404);
        }
    }

    /**
     * Search blocked periods by keyword.
     */
    public function search(Request $request)
    {
        try {
            $data = $request->validate([
                'keyword' => 'required|string|min:1',
                'limit' => 'nullable|integer|min:1|max:1000',
                'offset' => 'nullable|integer|min:0',
                'order_by' => 'nullable|string',
            ]);

            $keyword = $data['keyword'];
            $limit = $data['limit'] ?? 1000;
            $offset = $data['offset'] ?? 0;
            $orderBy = $data['order_by'] ?? 'updated_at DESC';

            // Use the model's search method
            $blockedPeriods = BlockedPeriod::search(
                $keyword,
                $limit,
                $offset,
                $orderBy
            );

            return response()->json([
                'success' => true,
                'keyword' => $keyword,
                'count' => $blockedPeriods->count(),
                'blocked_periods' => $blockedPeriods
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
                'error' => 'An error occurred while searching blocked periods.'
            ], 500);
        }
    }

    /**
     * Advanced search with multiple filters.
     */
    public function advancedSearch(Request $request)
    {
        try {
            $filters = $request->validate([
                'keyword' => 'nullable|string|min:1',
                'status' => 'nullable|in:active,upcoming,past',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'order_by' => 'nullable|string',
                'order_direction' => 'nullable|in:asc,desc',
                'limit' => 'nullable|integer|min:1|max:1000',
                'offset' => 'nullable|integer|min:0',
            ]);

            $blockedPeriods = BlockedPeriod::advancedSearch($filters);

            return response()->json([
                'success' => true,
                'filters' => $filters,
                'count' => $blockedPeriods->count(),
                'blocked_periods' => $blockedPeriods
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
                'error' => 'An error occurred during advanced search.'
            ], 500);
        }
    }

    /**
     * Check for overlapping blocked periods.
     */
    public function checkOverlaps(Request $request)
    {
        try {
            $data = $request->validate([
                'start_datetime' => 'required|date',
                'end_datetime' => 'required|date|after:start_datetime',
                'exclude_id' => 'nullable|integer',
            ]);

            $overlaps = BlockedPeriod::overlapping(
                $data['start_datetime'],
                $data['end_datetime']
            );

            if (!empty($data['exclude_id'])) {
                $overlaps->where('id', '!=', $data['exclude_id']);
            }

            $overlappingPeriods = $overlaps->get();

            return response()->json([
                'success' => true,
                'has_overlaps' => $overlappingPeriods->isNotEmpty(),
                'count' => $overlappingPeriods->count(),
                'overlapping_periods' => $overlappingPeriods
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
                'error' => 'An error occurred while checking overlaps.'
            ], 500);
        }
    }

    /**
     * Get a specific field value from a blocked period (CI3 compatibility).
     */
    public function getValue(Request $request)
    {
        try {
            $data = $request->validate([
                'blocked_period_id' => 'required|integer',
                'field' => 'required|string',
            ]);

            $value = BlockedPeriod::getValue(
                $data['blocked_period_id'],
                $data['field']
            );

            return response()->json([
                'success' => true,
                'field' => $data['field'],
                'value' => $value
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while retrieving the field value.'
            ], 500);
        }
    }

    /**
     * Get multiple field values from a blocked period.
     */
    public function getValues(Request $request)
    {
        try {
            $data = $request->validate([
                'blocked_period_id' => 'required|integer',
                'fields' => 'required|array',
                'fields.*' => 'string',
            ]);

            $values = BlockedPeriod::getValues(
                $data['blocked_period_id'],
                $data['fields']
            );

            return response()->json([
                'success' => true,
                'values' => $values
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while retrieving field values.'
            ], 500);
        }
    }

    /**
     * Get blocked periods with filtering (CI3 compatibility).
     */
    public function getFiltered(Request $request)
    {
        try {
            $data = $request->validate([
                'where' => 'nullable|array',
                'limit' => 'nullable|integer|min:1|max:1000',
                'offset' => 'nullable|integer|min:0',
                'order_by' => 'nullable|string',
            ]);

            $blockedPeriods = BlockedPeriod::getFiltered(
                $data['where'] ?? null,
                $data['limit'] ?? null,
                $data['offset'] ?? null,
                $data['order_by'] ?? null
            );

            return response()->json([
                'success' => true,
                'count' => $blockedPeriods->count(),
                'blocked_periods' => $blockedPeriods
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
                'error' => 'An error occurred while fetching blocked periods.'
            ], 500);
        }
    }

    /**
     * Get blocked periods with criteria-based filtering.
     */
    public function getByCriteria(Request $request)
    {
        try {
            $data = $request->validate([
                'criteria' => 'nullable|array',
                'options' => 'nullable|array',
                'options.limit' => 'nullable|integer|min:1|max:1000',
                'options.offset' => 'nullable|integer|min:0',
                'options.order_by' => 'nullable|string',
                'options.order_direction' => 'nullable|in:asc,desc',
                'options.paginate' => 'nullable|boolean',
                'options.per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $criteria = $data['criteria'] ?? [];
            $options = $data['options'] ?? [];

            $result = BlockedPeriod::getByCriteria($criteria, $options);

            return response()->json([
                'success' => true,
                'blocked_periods' => $result
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
                'error' => 'An error occurred while fetching blocked periods.'
            ], 500);
        }
    }

    /**
     * Get blocked periods for a specific date range.
     */
    public function getForPeriod(Request $request)
    {
        try {
            $data = $request->validate([
                'start_date' => 'required|date_format:Y-m-d',
                'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            ]);

            $blockedPeriods = BlockedPeriod::getForPeriod(
                $data['start_date'],
                $data['end_date']
            );

            return response()->json([
                'success' => true,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'count' => $blockedPeriods->count(),
                'blocked_periods' => $blockedPeriods
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
                'error' => 'An error occurred while fetching blocked periods for the period.'
            ], 500);
        }
    }

    /**
     * Get blocked periods for a specific date.
     */
    public function getForDate(Request $request)
    {
        try {
            $data = $request->validate([
                'date' => 'required|date_format:Y-m-d',
            ]);

            $blockedPeriods = BlockedPeriod::getForDate($data['date']);

            return response()->json([
                'success' => true,
                'date' => $data['date'],
                'count' => $blockedPeriods->count(),
                'is_blocked' => $blockedPeriods->isNotEmpty(),
                'is_entirely_blocked' => BlockedPeriod::isEntireDateBlocked($data['date']),
                'blocked_periods' => $blockedPeriods
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
                'error' => 'An error occurred while checking if date is blocked.'
            ], 500);
        }
    }

    /**
     * Check if a date is blocked (CI3 compatibility).
     */
    public function checkDateBlocked(Request $request)
    {
        try {
            $data = $request->validate([
                'date' => 'required|date_format:Y-m-d',
                'check_entire_day' => 'nullable|boolean',  // ✅ Added option
            ]);

            $date = $data['date'];
            $checkEntireDay = $data['check_entire_day'] ?? false;

            // Choose which check to perform
            if ($checkEntireDay) {
                $isBlocked = BlockedPeriod::isEntireDateBlocked($date);
                $checkType = 'entire_day';
            } else {
                $isBlocked = BlockedPeriod::isDateBlocked($date);
                $checkType = 'any_period';
            }

            $blockedPeriods = BlockedPeriod::getForDate($date);

            return response()->json([
                'success' => true,
                'date' => $date,
                'check_type' => $checkType,
                'is_blocked' => $isBlocked,
                'is_entirely_blocked' => BlockedPeriod::isEntireDateBlocked($date),
                'has_any_blocks' => BlockedPeriod::isDateBlocked($date),
                'blocked_periods_count' => $blockedPeriods->count(),
                'blocked_periods' => $blockedPeriods
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
                'error' => 'An error occurred while checking blocked status.'
            ], 500);
        }
    }

    /**
     * Check if working hours are blocked on a specific date.
     */
    public function checkWorkingHours(Request $request)
    {
        try {
            $data = $request->validate([
                'date' => 'required|date_format:Y-m-d',
                'work_start' => 'nullable|date_format:H:i:s',
                'work_end' => 'nullable|date_format:H:i:s',
            ]);

            $workStart = $data['work_start'] ?? '09:00:00';
            $workEnd = $data['work_end'] ?? '17:00:00';

            $blockingPeriods = BlockedPeriod::getBlockingWorkingHours(
                $data['date'],
                $workStart,
                $workEnd
            );

            return response()->json([
                'success' => true,
                'date' => $data['date'],
                'work_start' => $workStart,
                'work_end' => $workEnd,
                'is_blocked' => $blockingPeriods->isNotEmpty(),
                'blocking_periods_count' => $blockingPeriods->count(),
                'blocking_periods' => $blockingPeriods
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
                'error' => 'An error occurred while checking working hours.'
            ], 500);
        }
    }
}
