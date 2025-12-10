<?php
/**
 * Web Push Notification Helper
 * Menggunakan native PHP dengan JWT untuk mengirim push notifications
 */

// Cek apakah OpenSSL EC tersedia
function isPushSupported() {
    if (!function_exists('openssl_pkey_new')) {
        return false;
    }
    $curves = openssl_get_curve_names();
    return $curves && in_array('prime256v1', $curves);
}

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

function generateVapidKeys() {
    if (!isPushSupported()) {
        return false;
    }
    
    $privateKey = @openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    
    if (!$privateKey) {
        return false;
    }
    
    $details = openssl_pkey_get_details($privateKey);
    openssl_pkey_export($privateKey, $privateKeyPem);
    
    $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $publicKeyBinary = "\x04" . $x . $y;
    $publicKey = base64UrlEncode($publicKeyBinary);
    
    return [
        'publicKey' => $publicKey,
        'privateKeyPem' => $privateKeyPem
    ];
}

function createJWT($header, $payload, $privateKeyPem) {
    $headerEncoded = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));
    
    $dataToSign = "$headerEncoded.$payloadEncoded";
    
    $privateKey = openssl_pkey_get_private($privateKeyPem);
    if (!$privateKey) {
        return false;
    }
    
    $signature = '';
    if (!openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        return false;
    }
    
    $signatureRaw = derToRaw($signature);
    $signatureEncoded = base64UrlEncode($signatureRaw);
    
    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

function derToRaw($der) {
    $pos = 0;
    if (ord($der[$pos++]) !== 0x30) return $der;
    
    $totalLen = ord($der[$pos++]);
    if ($totalLen & 0x80) {
        $pos += ($totalLen & 0x7f);
    }
    
    if (ord($der[$pos++]) !== 0x02) return $der;
    $rLen = ord($der[$pos++]);
    $r = substr($der, $pos, $rLen);
    $pos += $rLen;
    
    if (ord($der[$pos++]) !== 0x02) return $der;
    $sLen = ord($der[$pos++]);
    $s = substr($der, $pos, $sLen);
    
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);
    
    return $r . $s;
}

function sendPushNotification($endpoint, $p256dh, $auth, $payload, $publicKey, $privateKeyPem) {
    $parsed = parse_url($endpoint);
    $audience = $parsed['scheme'] . '://' . $parsed['host'];
    
    $header = ['typ' => 'JWT', 'alg' => 'ES256'];
    $jwtPayload = [
        'aud' => $audience,
        'exp' => time() + 43200,
        'sub' => 'mailto:admin@absenhima.com'
    ];
    
    $jwt = createJWT($header, $jwtPayload, $privateKeyPem);
    if (!$jwt) {
        return ['success' => false, 'error' => 'Failed to create JWT'];
    }
    
    $payloadJson = json_encode($payload);
    $encrypted = encryptPayload($payloadJson, $p256dh, $auth);
    
    if (!$encrypted) {
        return ['success' => false, 'error' => 'Failed to encrypt payload'];
    }
    
    $headers = [
        'Authorization: vapid t=' . $jwt . ', k=' . $publicKey,
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'Content-Length: ' . strlen($encrypted['ciphertext']),
        'TTL: 86400',
        'Urgency: high'
    ];
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encrypted['ciphertext']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'code' => $httpCode];
    } else {
        return ['success' => false, 'code' => $httpCode, 'error' => $error ?: $response];
    }
}

function encryptPayload($payload, $userPublicKey, $userAuth) {
    $userPublicKeyBinary = base64UrlDecode($userPublicKey);
    $userAuthBinary = base64UrlDecode($userAuth);
    
    $localKey = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    
    if (!$localKey) {
        return false;
    }
    
    $localDetails = openssl_pkey_get_details($localKey);
    $localX = str_pad($localDetails['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $localY = str_pad($localDetails['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $localPublicKey = "\x04" . $localX . $localY;
    
    $sharedSecret = computeECDH($localKey, $userPublicKeyBinary);
    if (!$sharedSecret) {
        return false;
    }
    
    $salt = random_bytes(16);
    
    $ikm = deriveIKM($sharedSecret, $userAuthBinary, $userPublicKeyBinary, $localPublicKey);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    
    $cekInfo = "Content-Encoding: aes128gcm\x00";
    $nonceInfo = "Content-Encoding: nonce\x00";
    
    $cek = hkdfExpand($prk, $cekInfo, 16);
    $nonce = hkdfExpand($prk, $nonceInfo, 12);
    
    $paddedPayload = "\x02\x00" . $payload;
    
    $tag = '';
    $ciphertext = openssl_encrypt(
        $paddedPayload,
        'aes-128-gcm',
        $cek,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        '',
        16
    );
    
    if ($ciphertext === false) {
        return false;
    }
    
    $recordSize = pack('N', 4096);
    $keyIdLen = chr(65);
    
    $header = $salt . $recordSize . $keyIdLen . $localPublicKey;
    $content = $header . $ciphertext . $tag;
    
    return ['ciphertext' => $content];
}

function computeECDH($privateKey, $publicKeyBinary) {
    if (strlen($publicKeyBinary) !== 65 || $publicKeyBinary[0] !== "\x04") {
        return false;
    }
    
    $x = substr($publicKeyBinary, 1, 32);
    $y = substr($publicKeyBinary, 33, 32);
    
    $pubKeyPem = createPublicKeyPem($x, $y);
    $peerKey = openssl_pkey_get_public($pubKeyPem);
    
    if (!$peerKey) {
        return false;
    }
    
    // PHP 8.x - openssl_pkey_derive returns the shared secret directly
    $sharedSecret = openssl_pkey_derive($peerKey, $privateKey, 32);
    
    if ($sharedSecret === false) {
        return false;
    }
    
    return $sharedSecret;
}

function createPublicKeyPem($x, $y) {
    $der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00\x04" . $x . $y;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64) . "-----END PUBLIC KEY-----\n";
}

function deriveIKM($sharedSecret, $authSecret, $userPublicKey, $localPublicKey) {
    $info = "WebPush: info\x00" . $userPublicKey . $localPublicKey;
    $prk = hash_hmac('sha256', $sharedSecret, $authSecret, true);
    return hkdfExpand($prk, $info, 32);
}

function hkdfExpand($prk, $info, $length) {
    $t = '';
    $output = '';
    $counter = 1;
    
    while (strlen($output) < $length) {
        $t = hash_hmac('sha256', $t . $info . chr($counter), $prk, true);
        $output .= $t;
        $counter++;
    }
    
    return substr($output, 0, $length);
}

function getVapidKeys($conn) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'vapid_keys'");
    if (mysqli_num_rows($check) == 0) {
        return null;
    }
    $result = mysqli_query($conn, "SELECT * FROM vapid_keys ORDER BY id DESC LIMIT 1");
    return mysqli_fetch_assoc($result);
}

function sendNotificationToAllUsers($conn, $title, $body, $url = '/user/dashboard.php') {
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'push_subscriptions'");
    if (!$check || mysqli_num_rows($check) == 0) {
        return ['success' => false, 'error' => 'Tables not configured', 'sent' => 0, 'failed' => 0];
    }
    
    $vapid = getVapidKeys($conn);
    
    if (!$vapid) {
        return ['success' => false, 'error' => 'VAPID keys not configured', 'sent' => 0, 'failed' => 0];
    }
    
    $payload = [
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'icon' => '/uploads/settings/logo.png'
    ];
    
    $result = mysqli_query($conn, "SELECT * FROM push_subscriptions");
    
    $sent = 0;
    $failed = 0;
    $errors = [];
    
    while ($sub = mysqli_fetch_assoc($result)) {
        $sendResult = sendPushNotification(
            $sub['endpoint'],
            $sub['p256dh'],
            $sub['auth'],
            $payload,
            $vapid['public_key'],
            $vapid['private_key']
        );
        
        if ($sendResult['success']) {
            $sent++;
        } else {
            $failed++;
            $errors[] = $sendResult['error'] ?? 'Unknown error';
            
            if (isset($sendResult['code']) && in_array($sendResult['code'], [404, 410])) {
                $stmt = mysqli_prepare($conn, "DELETE FROM push_subscriptions WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $sub['id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    return [
        'success' => true,
        'sent' => $sent,
        'failed' => $failed,
        'errors' => $errors
    ];
}

function saveVapidKeys($conn, $publicKey, $privateKeyPem) {
    ensurePushTables($conn);
    
    mysqli_query($conn, "DELETE FROM vapid_keys");
    
    $stmt = mysqli_prepare($conn, "INSERT INTO vapid_keys (public_key, private_key) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ss", $publicKey, $privateKeyPem);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

function countPushSubscriptions($conn) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'push_subscriptions'");
    if (mysqli_num_rows($check) == 0) {
        return 0;
    }
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM push_subscriptions");
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

function ensurePushTables($conn) {
    $sql1 = "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh VARCHAR(255) NOT NULL,
        auth VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $sql2 = "CREATE TABLE IF NOT EXISTS vapid_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        public_key TEXT NOT NULL,
        private_key TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    mysqli_query($conn, $sql1);
    mysqli_query($conn, $sql2);
}
?>
