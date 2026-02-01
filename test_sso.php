$user = App\Models\User::where('name', 'like', '%Muhammad%')->first();
echo "Testing User: " . $user->email . "\n";
Auth::login($user);
$request = Illuminate\Http\Request::create('/api/moodle/login-url', 'POST');
$response = app()->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getContent() . "\n";
