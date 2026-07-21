<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::redirectIfLoggedIn();

// Step 1: Redirect to Google
if (!isset($_GET['code'])) {
    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => bin2hex(random_bytes(16)),
    ]);
    $_SESSION['oauth_state'] = $params;
    redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
}

// Step 2: Exchange code for token
$tokenResp = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'code'          => $_GET['code'],
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
    ],
]));

$tokenData = json_decode($tokenResp, true);
if (empty($tokenData['access_token'])) { redirect(APP_URL.'/auth/login.php?error=oauth_failed'); }

// Step 3: Get user info
$userInfo = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, stream_context_create([
    'http' => ['header' => 'Authorization: Bearer '.$tokenData['access_token']],
])), true);

if (empty($userInfo['email'])) { redirect(APP_URL.'/auth/login.php?error=oauth_no_email'); }

// Step 4: Find or create user
$user = DB::fetchOne("SELECT * FROM users WHERE email=?", [$userInfo['email']]);
if (!$user) {
    $userId = DB::insert('users', [
        'uuid'           => Auth::generateUUID(),
        'first_name'     => $userInfo['given_name'] ?? 'Google',
        'last_name'      => $userInfo['family_name'] ?? 'User',
        'email'          => $userInfo['email'],
        'avatar'         => $userInfo['picture'] ?? '',
        'oauth_provider' => 'google',
        'oauth_id'       => $userInfo['id'],
        'role'           => 'customer',
        'is_verified'    => 1,
        'is_active'      => 1,
        'created_at'     => date('Y-m-d H:i:s'),
    ]);
    $user = DB::fetchOne("SELECT * FROM users WHERE id=?", [$userId]);
} else {
    DB::update('users', ['oauth_provider'=>'google','oauth_id'=>$userInfo['id'],'avatar'=>$userInfo['picture']??$user['avatar']], 'id=?', [$user['id']]);
}

Auth::login($user);
$dest = $user['role'] === 'admin' ? APP_URL.'/admin/dashboard.php' : APP_URL.'/customer/dashboard.php';
redirect($dest);
