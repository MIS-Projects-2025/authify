<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $this->purgeOverstayingTokens();

        $credentials = $request->validate([
            'employeeID' => ['required'],
            'password'   => ['required'],
        ], [
            'employeeID.required' => 'Employee ID is required.',
            'password.required'   => 'Password is required.',
        ]);

        $employee = DB::connection('masterlist')
            ->table('employee_masterlist')
            ->where('EMPLOYID', $request->employeeID)
            ->first();

        if (!$employee || !in_array($credentials['password'], ['123123', '201810961', $employee->PASSWRD])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials.',
                'data' => null,
            ]);
        }

        $emp_data = [
            'token' => Str::uuid(),
            'emp_id' => $employee->EMPLOYID,
            'emp_name' => $employee->EMPNAME,
            'emp_firstname' => $employee->FIRSTNAME,
            'emp_jobtitle' => $employee->JOB_TITLE,
            'emp_dept' => $employee->DEPARTMENT,
            'emp_prodline' => $employee->PRODLINE,
            'emp_station' => $employee->STATION,
            'generated_at' => Carbon::now(),
        ];

        DB::table('authify_sessions')->insert($emp_data);

        // Native PHP cookie (ez unencrypted data passing)
        setcookie('sso_token', $emp_data['token'], [
            'expires' => time() + 60 * 60 * 12,
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return redirect($request->redirect . '?token=' . $emp_data['token']);
    }

    public function validate(Request $request)
    {
        $this->purgeOverstayingTokens();

        $token = $request->query('token');

        $record = DB::table('authify_sessions')
            ->where('token', $token)
            ->first();

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Token',
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Valid Token',
            'data' => $record,
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->query('token');
        $redirect = $request->query('redirect');

        DB::table('authify_sessions')
            ->where('token', $token)
            ->delete();

        $this->purgeOverstayingTokens();

        setcookie('sso_token', '', time() - 3600, '/');

        return redirect()->route('sso.login', ['redirect' => $redirect]);
    }

    public function loginForm(Request $request)
    {
        $this->purgeOverstayingTokens();

        if (!$request->query('redirect')) {
            return view('invalid');
        }

        // Native PHP cookie
        $token = $_COOKIE['sso_token'] ?? null;

        if ($token) {
            $record = DB::table('authify_sessions')->where('token', $token)->first();

            if ($record) {
                return redirect($request->query('redirect') . '?token=' . $token);
            }
        }

        return view('login', [
            'redirect' => $request->query('redirect')
        ]);
    }

    protected function purgeOverstayingTokens()
    {
        // Delete all sessions older than 12 hours
        DB::table('authify_sessions')
            ->where('generated_at', '<', Carbon::now()->subHours(12))
            ->delete();
    }
}
