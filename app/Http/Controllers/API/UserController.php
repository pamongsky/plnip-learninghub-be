<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get list of users (for selection dropdowns etc)
     */
    public function index(Request $request)
    {
        $query = User::select('id', 'name', 'email', 'employee_id');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        // Limit to prevent huge response
        $users = $query->orderBy('name')->limit(50)->get();

        return response()->json($users);
    }
}
