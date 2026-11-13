<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransportController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = DB::table('transport_vehicles')->orderBy('registration_no')->get();
        $routes = DB::table('transport_routes as r')
            ->leftJoin('transport_vehicles as v', 'v.id', '=', 'r.vehicle_id')
            ->leftJoin('staff as ds', 'ds.id', '=', 'r.driver_staff_id')
            ->leftJoin('users as du', 'du.id', '=', 'ds.user_id')
            ->leftJoin('staff as ats', 'ats.id', '=', 'r.attendant_staff_id')
            ->leftJoin('users as au', 'au.id', '=', 'ats.user_id')
            ->select('r.*', 'v.registration_no', 'du.name as driver_name', 'au.name as attendant_name')
            ->orderBy('r.name')->get();
        $stops = DB::table('transport_stops')->orderBy('name')->get();
        $staff = DB::table('staff as s')->join('users as u', 'u.id', '=', 's.user_id')
            ->where('u.is_active', true)->select('s.id', 's.staff_no', 's.designation', 'u.name', 'u.role')->orderBy('u.name')->get();
        $students = DB::table('students')->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get();
        $assignments = DB::table('transport_student_assignments as a')
            ->join('students as s', 's.id', '=', 'a.student_id')
            ->join('transport_routes as r', 'r.id', '=', 'a.route_id')
            ->join('transport_stops as ps', 'ps.id', '=', 'a.pickup_stop_id')
            ->join('transport_stops as ds', 'ds.id', '=', 'a.dropoff_stop_id')
            ->select('a.*', 's.admission_no', 's.first_name', 's.last_name', 'r.name as route_name', 'ps.name as pickup_name', 'ds.name as dropoff_name')
            ->orderBy('s.last_name')->limit(200)->get();
        $trips = DB::table('transport_trips as t')->join('transport_routes as r', 'r.id', '=', 't.route_id')
            ->join('transport_vehicles as v', 'v.id', '=', 't.vehicle_id')
            ->select('t.*', 'r.name as route_name', 'v.registration_no')->orderByDesc('t.trip_date')->orderByDesc('t.id')->limit(30)->get();
        $documents = DB::table('transport_vehicle_documents as d')->join('transport_vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->select('d.*', 'v.registration_no')->orderBy('d.expires_at')->limit(30)->get();
        $maintenance = DB::table('transport_vehicle_maintenance as m')->join('transport_vehicles as v', 'v.id', '=', 'm.vehicle_id')
            ->select('m.*', 'v.registration_no')->orderByDesc('m.service_date')->limit(20)->get();
        $incidents = DB::table('transport_incidents as i')->leftJoin('transport_routes as r', 'r.id', '=', 'i.route_id')
            ->leftJoin('transport_vehicles as v', 'v.id', '=', 'i.vehicle_id')
            ->leftJoin('students as s', 's.id', '=', 'i.student_id')
            ->select('i.*', 'r.name as route_name', 'v.registration_no', 's.first_name', 's.last_name')
            ->orderByDesc('i.occurred_at')->limit(20)->get();
        $summary = [
            'vehicles' => $vehicles->count(),
            'active_routes' => $routes->where('status', 'active')->count(),
            'active_assignments' => DB::table('transport_student_assignments')->where('status', 'active')->count(),
            'open_incidents' => DB::table('transport_incidents')->where('status', 'open')->count(),
            'expiring_documents' => DB::table('transport_vehicle_documents')->whereNotNull('expires_at')->whereBetween('expires_at', [today()->toDateString(), today()->addDays(30)->toDateString()])->count(),
        ];

        return view('tenant.transport.index', compact('vehicles', 'routes', 'stops', 'staff', 'students', 'assignments', 'trips', 'documents', 'maintenance', 'incidents', 'summary'));
    }

    public function parentIndex()
    {
        $userId = Auth::guard('school')->id();
        $guardian = DB::table('parents')->where('user_id', $userId)->first();
        abort_unless($guardian, 403, 'No parent/guardian profile is linked to this account.');

        $children = DB::table('student_parents')->where('parent_id', $guardian->id)->pluck('student_id');
        $assignments = DB::table('transport_student_assignments as a')
            ->join('students as s', 's.id', '=', 'a.student_id')
            ->join('transport_routes as r', 'r.id', '=', 'a.route_id')
            ->join('transport_stops as ps', 'ps.id', '=', 'a.pickup_stop_id')
            ->join('transport_stops as ds', 'ds.id', '=', 'a.dropoff_stop_id')
            ->leftJoin('transport_vehicles as v', 'v.id', '=', 'r.vehicle_id')
            ->leftJoin('staff as st', 'st.id', '=', 'r.driver_staff_id')
            ->leftJoin('users as u', 'u.id', '=', 'st.user_id')
            ->whereIn('a.student_id', $children)->where('a.status', 'active')
            ->select('s.first_name', 's.last_name', 'r.name as route_name', 'r.route_code', 'v.registration_no', 'u.name as driver_name', 'ps.name as pickup_name', 'ps.address as pickup_address', 'ds.name as dropoff_name', 'ds.address as dropoff_address', 'a.starts_on', 'a.ends_on')
            ->orderBy('s.first_name')->get();

        return view('tenant.transport.parent', compact('assignments'));
    }

    public function storeVehicle(Request $request)
    {
        $data = $request->validate([
            'registration_no' => ['required', 'string', 'max:40', 'unique:transport_vehicles,registration_no'],
            'make' => ['nullable', 'string', 'max:80'], 'model' => ['nullable', 'string', 'max:80'],
            'colour' => ['nullable', 'string', 'max:40'], 'vehicle_type' => ['required', Rule::in(['bus', 'van', 'car', 'other'])],
            'seat_capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'status' => ['required', Rule::in(['active', 'maintenance', 'inactive', 'retired'])],
            'insurance_expires_at' => ['nullable', 'date'], 'roadworthiness_expires_at' => ['nullable', 'date'],
            'licence_expires_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $id = DB::table('transport_vehicles')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        $this->audit('transport.vehicle.created', 'transport_vehicle', $id, null, $data);
        return $this->back($request, 'Vehicle registered.');
    }

    public function storeStop(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:140'], 'address' => ['nullable', 'string', 'max:1000'], 'landmark' => ['nullable', 'string', 'max:1000']]);
        $exists = DB::table('transport_stops')->where('name', $data['name'])->where('address', $data['address'] ?? null)->exists();
        if ($exists) throw ValidationException::withMessages(['name' => 'This stop already exists.']);
        $id = DB::table('transport_stops')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        $this->audit('transport.stop.created', 'transport_stop', $id, null, $data);
        return $this->back($request, 'Route stop created.');
    }

    public function storeRoute(Request $request)
    {
        $data = $request->validate([
            'route_code' => ['required', 'string', 'max:40', 'unique:transport_routes,route_code'],
            'name' => ['required', 'string', 'max:140'], 'description' => ['nullable', 'string', 'max:2000'],
            'vehicle_id' => ['nullable', 'integer', 'exists:transport_vehicles,id'],
            'driver_staff_id' => ['nullable', 'integer', 'exists:staff,id'], 'attendant_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'transport_fee' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'fee_frequency' => ['required', Rule::in(['term', 'month', 'year', 'one_way'])],
            'stop_ids' => ['required', 'array', 'min:2'], 'stop_ids.*' => ['required', 'integer', 'distinct', 'exists:transport_stops,id'],
        ]);
        if (!empty($data['vehicle_id'])) {
            $vehicle = DB::table('transport_vehicles')->where('id', $data['vehicle_id'])->first();
            if (!$vehicle || $vehicle->status !== 'active') throw ValidationException::withMessages(['vehicle_id' => 'Choose an active vehicle.']);
        }
        $id = DB::transaction(function () use ($data) {
            $id = DB::table('transport_routes')->insertGetId([
                'route_code' => strtoupper($data['route_code']), 'name' => $data['name'], 'description' => $data['description'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null, 'driver_staff_id' => $data['driver_staff_id'] ?? null,
                'attendant_staff_id' => $data['attendant_staff_id'] ?? null, 'transport_fee' => $data['transport_fee'],
                'fee_frequency' => $data['fee_frequency'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($data['stop_ids'] as $index => $stopId) {
                DB::table('transport_route_stops')->insert([
                    'route_id' => $id, 'stop_id' => $stopId, 'stop_order' => $index + 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $id;
        });
        $this->audit('transport.route.created', 'transport_route', $id, null, ['route_code' => $data['route_code'], 'name' => $data['name'], 'vehicle_id' => $data['vehicle_id'] ?? null, 'stops' => $data['stop_ids']]);
        return $this->back($request, 'Transport route created with ordered stops.');
    }

    public function assignStudent(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'], 'route_id' => ['required', 'integer', 'exists:transport_routes,id'],
            'pickup_stop_id' => ['required', 'integer', 'exists:transport_stops,id'], 'dropoff_stop_id' => ['required', 'integer', 'different:pickup_stop_id', 'exists:transport_stops,id'],
            'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'fee_override' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $routeStops = DB::table('transport_route_stops')->where('route_id', $data['route_id'])->pluck('stop_id')->all();
        $pickupOrder = DB::table('transport_route_stops')->where('route_id', $data['route_id'])->where('stop_id', $data['pickup_stop_id'])->value('stop_order');
        $dropoffOrder = DB::table('transport_route_stops')->where('route_id', $data['route_id'])->where('stop_id', $data['dropoff_stop_id'])->value('stop_order');
        if (!in_array((int) $data['pickup_stop_id'], array_map('intval', $routeStops), true) || !in_array((int) $data['dropoff_stop_id'], array_map('intval', $routeStops), true) || $pickupOrder === null || $dropoffOrder === null || $pickupOrder >= $dropoffOrder) {
            throw ValidationException::withMessages(['pickup_stop_id' => 'Both stops must belong to the selected route, and the pickup stop must come before the drop-off stop.']);
        }
        if (DB::table('transport_student_assignments')->where('student_id', $data['student_id'])->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['student_id' => 'This student already has an active transport assignment. End the existing assignment first.']);
        }
        $id = DB::table('transport_student_assignments')->insertGetId($data + [
            'status' => 'active', 'assigned_by' => Auth::guard('school')->id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit('transport.student.assigned', 'transport_assignment', $id, null, $data);
        return $this->back($request, 'Student assigned to transport route.');
    }

    public function endAssignment(Request $request, int $assignment)
    {
        $row = DB::table('transport_student_assignments')->where('id', $assignment)->first();
        abort_unless($row, 404);
        DB::table('transport_student_assignments')->where('id', $assignment)->update(['status' => 'ended', 'ends_on' => $row->ends_on ?: today()->toDateString(), 'updated_at' => now()]);
        $this->audit('transport.assignment.ended', 'transport_assignment', $assignment, ['status' => $row->status], ['status' => 'ended']);
        return $this->back($request, 'Transport assignment ended.');
    }

    public function storeDocument(Request $request)
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:transport_vehicles,id'], 'document_type' => ['required', Rule::in(['insurance', 'roadworthiness', 'vehicle_licence', 'permit', 'other'])],
            'document_number' => ['nullable', 'string', 'max:100'], 'issued_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $id = DB::table('transport_vehicle_documents')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        $this->audit('transport.document.created', 'transport_vehicle_document', $id, null, $data);
        return $this->back($request, 'Vehicle document recorded.');
    }

    public function storeTrip(Request $request)
    {
        $data = $request->validate([
            'route_id' => ['required', 'integer', 'exists:transport_routes,id'], 'trip_date' => ['required', 'date'],
            'trip_type' => ['required', Rule::in(['pickup', 'dropoff'])], 'vehicle_id' => ['required', 'integer', 'exists:transport_vehicles,id'],
            'driver_staff_id' => ['nullable', 'integer', 'exists:staff,id'], 'status' => ['required', Rule::in(['scheduled', 'departed', 'completed', 'cancelled'])],
            'departed_at' => ['nullable', 'date_format:H:i'], 'arrived_at' => ['nullable', 'date_format:H:i'],
            'odometer_start' => ['nullable', 'integer', 'min:0'], 'odometer_end' => ['nullable', 'integer', 'min:0', 'gte:odometer_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $route = DB::table('transport_routes')->where('id', $data['route_id'])->first();
        $vehicle = DB::table('transport_vehicles')->where('id', $data['vehicle_id'])->first();
        if (!$route || $route->status !== 'active' || !$vehicle || $vehicle->status !== 'active') {
            throw ValidationException::withMessages(['vehicle_id' => 'Trips require an active route and an active vehicle.']);
        }
        if ($route->vehicle_id && (int) $route->vehicle_id !== (int) $vehicle->id) {
            throw ValidationException::withMessages(['vehicle_id' => 'The selected vehicle does not match the vehicle assigned to this route.']);
        }
        $capacity = (int) $vehicle->seat_capacity;
        $assigned = DB::table('transport_student_assignments')->where('route_id', $route->id)->where('status', 'active')->count();
        if ($assigned > $capacity) throw ValidationException::withMessages(['vehicle_id' => 'The active route assignments exceed this vehicle capacity.']);
        if ($data['status'] === 'completed' && empty($data['arrived_at'])) {
            throw ValidationException::withMessages(['arrived_at' => 'Enter an arrival time for a completed trip.']);
        }
        $id = DB::table('transport_trips')->insertGetId($data + [
            'recorded_by' => Auth::guard('school')->id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit('transport.trip.created', 'transport_trip', $id, null, $data);
        return $this->back($request, 'Transport trip recorded.');
    }

    public function recordAttendance(Request $request, int $trip)
    {
        $data = $request->validate([
            'attendance' => ['required', 'array', 'min:1'],
            'attendance.*.assignment_id' => ['required', 'integer', 'distinct', 'exists:transport_student_assignments,id'],
            'attendance.*.attendance_status' => ['required', Rule::in(['boarded', 'absent', 'not_expected', 'dropped_off'])],
            'attendance.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
        $tripRow = DB::table('transport_trips')->where('id', $trip)->first();
        abort_unless($tripRow, 404);
        DB::transaction(function () use ($data, $trip, $tripRow) {
            foreach ($data['attendance'] as $entry) {
                $assignment = DB::table('transport_student_assignments')->where('id', $entry['assignment_id'])->first();
                if (!$assignment || (int) $assignment->route_id !== (int) $tripRow->route_id || $assignment->status !== 'active') {
                    throw ValidationException::withMessages(['attendance' => 'Attendance can only be recorded for active students assigned to this trip route.']);
                }
                DB::table('transport_trip_attendance')->updateOrInsert(
                    ['trip_id' => $trip, 'assignment_id' => $assignment->id],
                    ['attendance_status' => $entry['attendance_status'], 'notes' => $entry['notes'] ?? null, 'recorded_by' => Auth::guard('school')->id(), 'created_at' => now(), 'updated_at' => now()]
                );
            }
        });
        $this->audit('transport.trip.attendance_recorded', 'transport_trip', $trip, null, ['entries' => count($data['attendance'])]);
        return $this->back($request, 'Trip attendance saved.');
    }

    public function storeIncident(Request $request)
    {
        $data = $request->validate([
            'trip_id' => ['nullable', 'integer', 'exists:transport_trips,id'], 'route_id' => ['nullable', 'integer', 'exists:transport_routes,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:transport_vehicles,id'], 'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'occurred_at' => ['required', 'date'], 'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'incident_type' => ['required', 'string', 'max:60'], 'description' => ['required', 'string', 'max:4000'],
            'action_taken' => ['nullable', 'string', 'max:4000'],
        ]);
        if (!empty($data['trip_id'])) {
            $trip = DB::table('transport_trips')->where('id', $data['trip_id'])->first();
            if ($trip && (!empty($data['route_id']) && (int) $data['route_id'] !== (int) $trip->route_id || !empty($data['vehicle_id']) && (int) $data['vehicle_id'] !== (int) $trip->vehicle_id)) {
                throw ValidationException::withMessages(['trip_id' => 'The incident route and vehicle must match the selected trip.']);
            }
            $data['route_id'] = $data['route_id'] ?? $trip->route_id;
            $data['vehicle_id'] = $data['vehicle_id'] ?? $trip->vehicle_id;
        }
        if (empty($data['trip_id']) && empty($data['route_id']) && empty($data['vehicle_id'])) {
            throw ValidationException::withMessages(['route_id' => 'Link the incident to a trip, route or vehicle.']);
        }
        $id = DB::table('transport_incidents')->insertGetId($data + ['reported_by' => Auth::guard('school')->id(), 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('transport.incident.reported', 'transport_incident', $id, null, $data);
        return $this->back($request, 'Transport incident recorded.');
    }

    public function updateIncident(Request $request, int $incident)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'investigating', 'resolved'])], 'action_taken' => ['nullable', 'string', 'max:4000']]);
        $old = DB::table('transport_incidents')->where('id', $incident)->first();
        abort_unless($old, 404);
        DB::table('transport_incidents')->where('id', $incident)->update($data + ['updated_at' => now()]);
        $this->audit('transport.incident.updated', 'transport_incident', $incident, ['status' => $old->status], $data);
        return $this->back($request, 'Incident status updated.');
    }

    public function storeMaintenance(Request $request)
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:transport_vehicles,id'], 'maintenance_type' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:2000'], 'cost' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'service_date' => ['required', 'date'], 'next_service_date' => ['nullable', 'date', 'after_or_equal:service_date'],
            'status' => ['required', Rule::in(['scheduled', 'in_progress', 'completed', 'cancelled'])],
            'provider' => ['nullable', 'string', 'max:140'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $id = DB::table('transport_vehicle_maintenance')->insertGetId($data + ['recorded_by' => Auth::guard('school')->id(), 'created_at' => now(), 'updated_at' => now()]);
        if ($data['status'] === 'in_progress') DB::table('transport_vehicles')->where('id', $data['vehicle_id'])->update(['status' => 'maintenance', 'updated_at' => now()]);
        if ($data['status'] === 'completed') {
            $open = DB::table('transport_vehicle_maintenance')->where('vehicle_id', $data['vehicle_id'])->whereIn('status', ['scheduled', 'in_progress'])->exists();
            if (!$open) DB::table('transport_vehicles')->where('id', $data['vehicle_id'])->where('status', 'maintenance')->update(['status' => 'active', 'updated_at' => now()]);
        }
        $this->audit('transport.vehicle.maintenance_recorded', 'transport_vehicle_maintenance', $id, null, $data);
        return $this->back($request, 'Vehicle maintenance record saved.');
    }

    private function audit(string $action, string $type, int $id, ?array $old, ?array $new): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::guard('school')->id(), 'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'old_value' => $old ? json_encode($old) : null, 'new_value' => $new ? json_encode($new) : null,
            'ip_address' => request()->ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function back(Request $request, string $message)
    {
        return redirect('/school/' . $request->route('school') . '/transport')->with('success', $message);
    }
}
