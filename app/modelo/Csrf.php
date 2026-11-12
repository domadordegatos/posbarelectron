<?php
require_once __DIR__ . '/Auth.php';

class Csrf
{
    public static function token(): string
    {
        Auth::iniciarSesion();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function campoOculto(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return "<input type=\"hidden\" name=\"csrf_token\" value=\"$token\">";
    }

    public static function validarApi(): void
    {
        Auth::iniciarSesion();
        $enviado = $_POST['csrf_token'] ?? '';
        $esperado = $_SESSION['csrf_token'] ?? '';
        if ($enviado === '' || $esperado === '' || !hash_equals($esperado, $enviado)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Token de seguridad inválido, recarga la página.']);
            exit;
        }
    }
}
