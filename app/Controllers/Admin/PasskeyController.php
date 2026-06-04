<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Session;
use App\Services\PasskeyService;

class PasskeyController
{
    private $service;

    public function __construct()
    {
        $this->service = new PasskeyService();
    }

    public function registerOptions(Request $request)
    {
        $challenge = base64_encode(random_bytes(32));
        Session::set('passkey_challenge', $challenge);

        $options = [
            'challenge' => $challenge,
            'rp' => ['name' => 'LiteNote', 'id' => $_SERVER['HTTP_HOST']],
            'user' => ['id' => '1', 'name' => 'admin', 'displayName' => 'Admin'],
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7]],
            'timeout' => 60000,
            'attestation' => 'none'
        ];

        header('Content-Type: application/json');
        echo json_encode($options);
        exit;
    }

    public function register(Request $request)
    {
        $data = json_decode($request->input('credential'), true);

        $saved = $this->service->saveCredential([
            'credential_id' => $data['id'],
            'public_key'    => json_encode($data['response']),
            'counter'       => 0,
            'device_name'   => $request->input('device_name', '未知设备')
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => $saved]);
        exit;
    }

    public function loginOptions(Request $request)
    {
        $challenge = base64_encode(random_bytes(32));
        Session::set('passkey_login_challenge', $challenge);

        $options = [
            'challenge' => $challenge,
            'timeout' => 60000,
            'rpId' => $_SERVER['HTTP_HOST']
        ];

        header('Content-Type: application/json');
        echo json_encode($options);
        exit;
    }

    public function login(Request $request)
    {
        $data = json_decode($request->input('credential'), true);
        $credential = $this->service->getCredentialById($data['id']);

        if ($credential) {
            $this->service->updateCounter($credential['credential_id'], $credential['counter'] + 1);
            Session::set('admin_logged_in', true);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false]);
        }
        exit;
    }
}