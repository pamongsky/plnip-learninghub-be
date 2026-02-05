@echo off
echo === Testing Instructor Dashboard API ===
echo.

REM Get auth token first (replace with actual login)
echo Logging in as instructor...
curl -X POST http://localhost:8000/api/auth/login ^
  -H "Content-Type: application/json" ^
  -H "Accept: application/json" ^
  -d "{\"email\":\"instructor@plnip.local\",\"password\":\"password\"}" ^
  -o token_response.json

echo.
echo.

REM Parse token (manual - view token_response.json and copy token)
echo Copy the token from token_response.json, then run:
echo.
echo curl -X GET http://localhost:8000/api/dashboard/instructor ^
echo   -H "Authorization: Bearer YOUR_TOKEN_HERE" ^
echo   -H "Accept: application/json"

pause
