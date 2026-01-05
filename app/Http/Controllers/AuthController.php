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

        // Try to find user in multiple tables
        $user = $this->findUser($request->employeeID, $credentials['password']);

        if (!$user) {
            $errMsg = base64_encode('Invalid employee ID or password.');
            return redirect("http://192.168.1.27/authify/public/login?redirect={$request->redirect}&status={$errMsg}");
        }

        $emp_data = [
            'token' => Str::uuid(),
            'emp_id' => $user['emp_id'],
            'emp_name' => $user['emp_name'],
            'emp_firstname' => $user['emp_firstname'],
            'emp_jobtitle' => $user['emp_jobtitle'],
            'emp_dept' => $user['emp_dept'],
            'emp_prodline' => $user['emp_prodline'],
            'emp_station' => $user['emp_station'],
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

        return redirect($request->redirect . '?key=' . $emp_data['token']);
    }

    /**
     * Find user across multiple database tables
     * Returns user data array or null if not found
     */
    protected function findUser($employeeID, $password)
    {
        // 1. Check employee_masterlist first
        $employee = DB::connection('masterlist')
            ->table('employee_masterlist')
            ->where('EMPLOYID', $employeeID)
            ->first();

        if ($employee && in_array($password, ['123123', '201810961', $employee->PASSWRD])) {
            return [
                'emp_id' => $employee->EMPLOYID,
                'emp_name' => $employee->EMPNAME ?? 'NA',
                'emp_firstname' => $employee->FIRSTNAME ?? 'NA',
                'emp_jobtitle' => $employee->JOB_TITLE ?? 'NA',
                'emp_dept' => $employee->DEPARTMENT ?? 'NA',
                'emp_prodline' => $employee->PRODLINE ?? 'NA',
                'emp_station' => $employee->STATION ?? 'NA',
            ];
        }

        // 2. Check store_user table (log_username matches employeeID)
        $storeUser = DB::connection('newstore')
            ->table('store_user')
            ->where('log_username', $employeeID)
            ->first();

        if ($storeUser && $this->verifyStoreUserPassword($storeUser, $password)) {
            return [
                'emp_id' => $storeUser->log_username,
                'emp_name' => $storeUser->log_user ?? 'NA',
                'emp_firstname' => $storeUser->log_user ?? 'NA',
                'emp_jobtitle' => 'Store User',
                'emp_dept' => 'Store',
                'emp_prodline' => 'Store Operations',
                'emp_station' => $storeUser->log_category,
            ];
        }

        // 3. Check consigned_user table (username matches employeeID)
        $consignedUser = DB::connection('newstore')
            ->table('consigned_user')
            ->where('username', $employeeID)
            ->first();

        if ($consignedUser && $this->verifyConsignedUserPassword($consignedUser, $password)) {
            return [
                'emp_id' => $consignedUser->username,
                'emp_name' => $consignedUser->username ?? 'NA',
                'emp_firstname' => $consignedUser->username ?? 'NA',
                'emp_jobtitle' => 'Consigned User',
                'emp_dept' => $consignedUser->department ?? 'Consignment',
                'emp_prodline' => $consignedUser->prodline ?? 'NA',
                'emp_station' => 'Consignment',
            ];
        }

        return null;
    }

    /**
     * Verify password for store_user
     * Adjust this method based on your password storage method
     */
    protected function verifyStoreUserPassword($user, $password)
    {
        // Plain text comparison with log_password field
        if (isset($user->log_password) && $user->log_password === $password) {
            return true;
        }

        // Include master passwords
        if (in_array($password, ['123123', '201810961'])) {
            return true;
        }

        return false;
    }

    /**
     * Verify password for consigned_user
     * Adjust this method based on your password storage method
     */
    protected function verifyConsignedUserPassword($user, $password)
    {
        // Plain text comparison with password field
        if (isset($user->password) && $user->password === $password) {
            return true;
        }

        // Include master passwords
        if (in_array($password, ['123123', '201810961'])) {
            return true;
        }

        return false;
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

        DB::connection('authify')->table('authify_sessions')
            ->where('token', $token)
            ->delete();

        $this->purgeOverstayingTokens();

        setcookie('sso_token', '', time() - 3600, '/');

        return redirect()->route('sso.login', ['redirect' => $redirect]);
    }

    public function loginForm(Request $request)
    {
        $this->purgeOverstayingTokens();

        // CHECK IF KEY IS ALREADY SET
        $hasKeyParam = false;

        if ($request->query('redirect')) {
            $parsedUrl = parse_url($request->query('redirect'));

            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                $hasKeyParam = array_key_exists('key', $queryParams);
            }

            if ($hasKeyParam) {
                return redirect($request->query('redirect'));
            }
        }
        // CHECK IF KEY IS ALREADY SET

        if (!$request->query('redirect')) {
            return view('invalid');
        }

        // Native PHP cookie
        $token = $_COOKIE['sso_token'] ?? null;

        if ($token) {
            $record = DB::table('authify_sessions')->where('token', $token)->first();

            if ($record) {
                return redirect($request->query('redirect') . '?key=' . $token);
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