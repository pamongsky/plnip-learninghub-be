<?php
Route::get('/', function () {
    return response()->json(['message' => 'PLN IP Learning Hub API is running. Access endpoints via /api.']);
});

// require __DIR__.'/auth.php'; // Removed as we use API auth only
