<?php
/**
 * core/config_crypto.php
 * Sistema de Cifrado Simétrico AES-256-CBC
 * Versión: 1.0 - Compatible con migración desde texto plano
 */

class ConfigCrypto {
    private static $method = 'AES-256-CBC';
    private static $key = null;
    
    /**
     * Inicializa la clave desde .env
     */
    private static function initKey() {
        if (self::$key !== null) return;
        
        $envFile = __DIR__ . '/.env';
        
        // Si no existe .env, crearlo con clave aleatoria segura
        if (!file_exists($envFile)) {
            $randomKey = bin2hex(random_bytes(32)); // 64 caracteres hex = 256 bits
            $content = "# URTRACK - Clave de Cifrado\n";
            $content .= "# ¡NO COMPARTIR! ¡NO SUBIR A GIT!\n";
            $content .= "ENCRYPTION_KEY=$randomKey\n";
            
            if (file_put_contents($envFile, $content) === false) {
                throw new Exception("No se pudo crear .env. Verifique permisos en /core/");
            }
            
            chmod($envFile, 0600); // Solo lectura/escritura para owner
        }
        
        // Leer clave del archivo
        $envContent = @file_get_contents($envFile);
        if ($envContent === false) {
            throw new Exception("No se pudo leer .env");
        }
        
        if (preg_match('/ENCRYPTION_KEY=([a-f0-9]{64})/i', $envContent, $matches)) {
            self::$key = hex2bin($matches[1]);
        } else {
            throw new Exception("Clave de cifrado corrupta en .env. Regenere el archivo.");
        }
    }
    
    /**
     * Cifra un valor con AES-256-CBC
     * @param string $plaintext Texto plano
     * @return string Texto cifrado en base64
     */
    public static function encrypt($plaintext) {
        if (empty($plaintext)) return '';
        
        self::initKey();
        
        $ivLength = openssl_cipher_iv_length(self::$method);
        $iv = random_bytes($ivLength);
        
        $ciphertext = openssl_encrypt(
            $plaintext, 
            self::$method, 
            self::$key, 
            OPENSSL_RAW_DATA, 
            $iv
        );
        
        if ($ciphertext === false) {
            throw new Exception("Error al cifrar datos");
        }
        
        // Formato: base64(IV + '::' + Ciphertext)
        return base64_encode($iv . '::' . $ciphertext);
    }
    
    /**
     * Descifra un valor
     * @param string $encrypted Texto cifrado
     * @return string Texto plano
     */
    public static function decrypt($encrypted) {
        if (empty($encrypted)) return '';
        
        // Si no está cifrado (migración), devolver tal cual
        if (!self::isEncrypted($encrypted)) {
            return $encrypted;
        }
        
        self::initKey();
        
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false) {
            return $encrypted; // No era base64 válido
        }
        
        $parts = explode('::', $decoded, 2);
        if (count($parts) !== 2) {
            return $encrypted; // Formato incorrecto
        }
        
        list($iv, $ciphertext) = $parts;
        
        $plaintext = openssl_decrypt(
            $ciphertext, 
            self::$method, 
            self::$key, 
            OPENSSL_RAW_DATA, 
            $iv
        );
        
        if ($plaintext === false) {
            throw new Exception("Error al descifrar datos. Clave incorrecta.");
        }
        
        return $plaintext;
    }
    
    /**
     * Verifica si un valor está cifrado
     * @param string $value Valor a verificar
     * @return bool True si está cifrado
     */
    public static function isEncrypted($value) {
        if (empty($value)) return false;
        
        // Intentar decodificar base64
        $decoded = @base64_decode($value, true);
        if ($decoded === false) return false;
        
        // Verificar formato IV::Ciphertext
        return strpos($decoded, '::') !== false;
    }
}
?>