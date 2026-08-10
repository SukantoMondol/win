<?php
class Encryption {
    
    /**
     * Encrypt payload using AES-256-ECB for SoftAPI
     * * @param array $data The payload array
     * @param string $key 32-character secret key
     * @return string Base64 encoded encrypted string
     * @throws Exception If key is not exactly 32 characters
     */
    public static function encryptPayloadECB(array $data, string $key): string {
        if (strlen($key) !== 32) {
            throw new Exception("Encryption Error: Secret key must be exactly 32 characters.");
        }
        
        $jsonPayload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            throw new Exception("Encryption Error: Failed to encode JSON.");
        }
        
        $encryptedRaw = openssl_encrypt($jsonPayload, "AES-256-ECB", $key, OPENSSL_RAW_DATA);
        if ($encryptedRaw === false) {
            throw new Exception("Encryption Error: OpenSSL encryption failed.");
        }
        
        return base64_encode($encryptedRaw);
    }

    /**
     * Decrypt payload using AES-256-ECB
     * * @param string $encryptedBase64 Base64 encoded encrypted string
     * @param string $key 32-character secret key
     * @return array|null Decoded payload array or null on failure
     * @throws Exception If key is not exactly 32 characters
     */
    public static function decryptPayloadECB(string $encryptedBase64, string $key): ?array {
        if (strlen($key) !== 32) {
            throw new Exception("Decryption Error: Secret key must be exactly 32 characters.");
        }
        
        $decodedRaw = base64_decode($encryptedBase64);
        if ($decodedRaw === false) {
            return null;
        }
        
        $decryptedJson = openssl_decrypt($decodedRaw, "AES-256-ECB", $key, OPENSSL_RAW_DATA);
        if ($decryptedJson === false) {
            return null;
        }
        
        return json_decode($decryptedJson, true);
    }
}
?>