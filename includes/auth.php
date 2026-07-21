<?php
// =============================================
// INSPECTRA — Auth Helper
// =============================================

class Auth {
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => APP_ENV === 'production',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function login(array $user): void {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_uuid'] = $user['uuid'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email']= $user['email'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time']= time();

        // Update last login
        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
    }

    public static function logout(): void {
        self::startSession();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }

    public static function check(): bool {
        self::startSession();
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public static function requireLogin(string $redirectTo = '/inspectra/auth/login.php'): void {
        if (!self::check()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function requireRole(string $role): void {
        self::requireLogin();
        if (self::role() !== $role) {
            if ($role === 'admin') {
                header('Location: ' . APP_URL . '/customer/dashboard.php');
            } else {
                header('Location: ' . APP_URL . '/admin/dashboard.php');
            }
            exit;
        }
    }

    public static function requireAdmin(): void {
        self::requireRole('admin');
    }

    public static function requireCustomer(): void {
        self::requireRole('customer');
    }

    public static function redirectIfLoggedIn(): void {
        if (self::check()) {
            $dest = self::role() === 'admin'
                ? APP_URL . '/admin/dashboard.php'
                : APP_URL . '/customer/dashboard.php';
            header('Location: ' . $dest);
            exit;
        }
    }

    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public static function uuid(): ?string {
        return $_SESSION['user_uuid'] ?? null;
    }

    public static function role(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    public static function name(): ?string {
        return $_SESSION['user_name'] ?? null;
    }

    public static function email(): ?string {
        return $_SESSION['user_email'] ?? null;
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        return DB::fetchOne("SELECT * FROM users WHERE id = ?", [self::id()]);
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }

    public static function generateUUID(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function generateBookingRef(): string {
        return 'INS-' . strtoupper(substr(uniqid(), -6));
    }
}
