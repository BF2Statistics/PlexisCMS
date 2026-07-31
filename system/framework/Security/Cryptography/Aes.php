<?php
/**
 * Plexis Core
 *
 * PHP Version 8.4.2 or newer is required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */
namespace System\Security\Cryptography;

/**
 * Provides functionality for encrypting and decrypting data using the AES cipher algorithm.
 * The class supports setting custom cipher methods, keys, initialization vectors (IVs),
 * and data to be encrypted or decrypted. It utilizes PHP's OpenSSL extension.
 */
class Aes
{
    /**
     * @var string|null
     */
    private ?string $key = null;

    /**
     * @var mixed|null
     */
    private mixed $data = null;

    /**
     * @var string
     */
    private string $method = "aes-256-cbc";

    /**
     * @var string
     */
    private string $iv = "";

    /**
     * Constructor method to initialize the class with optional data, key, and encryption method.
     *
     * @param mixed $data Optional data to initialize the object with.
     * @param string|null $key Optional encryption key.
     * @param string $method Encryption method, default is "aes-256-cbc".
     *
     * @return void
     */
    function __construct(mixed $data = null, #[\SensitiveParameter] ?string $key = null, string $method = "aes-256-cbc")
    {
        if (!empty($data)) {
            $this->setData($data);
        }
        if ($key !== null) {
            $this->setKey($key);
        }
        $this->setMethod($method);
        $this->generateIv();
    }

    /**
     * Get encrypt/decrypt key
     * @return string|null
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Return encoded or decoded string
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Get selected cipher method
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get available cipher methods
     */
    public static function GetAvailableMethods(bool $aliases = false): array
    {
        return openssl_get_cipher_methods($aliases);
    }

    /**
     * Gets selected iv string
     * @return string
     */
    public function getIv(): string
    {
        return $this->iv;
    }

    /**
     * Generate a new IV string for selected method
     * @return bool
     */
    public function generateIv(): bool
    {
        $iv = openssl_random_pseudo_bytes($this->getIvLength());
        if ($iv === false) {
            return false;
        }

        $this->iv = $iv;
        return true;
    }

    /**
     * Gets the cipher iv length
     * @return int|bool
     */
    public function getIvLength(): bool|int
    {
        return openssl_cipher_iv_length($this->method);
    }

    /**
     * Set encrypt/decrypt key
     * @param string $key
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setKey(#[\SensitiveParameter] string $key): self
    {
        if (empty($key)) {
            throw new \InvalidArgumentException("Key is empty.");
        }
        $this->key = $key;
        return $this;
    }

    /**
     * Set encrypt/decrypt method
     * @param string $method
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setMethod(string $method): self
    {
        $method = strtolower($method);

        if (!in_array($method, self::GetAvailableMethods())) {
            throw new \InvalidArgumentException("The method is not available");
        }
        $this->method = $method;
        return $this;
    }

    /**
     * Set iv string
     * @param string $iv
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setIv(string $iv): self
    {
        if (strlen($iv) != $this->getIvLength()) {
            throw new \InvalidArgumentException("Iv length must be " . $this->getIvLength());
        }
        $this->iv = $iv;

        return $this;
    }

    /**
     * Set encrypt/decrypt data
     * @param mixed $data
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setData(mixed $data): self
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("Data is empty.");
        }
        $this->data = $data;
        return $this;
    }

    /**
     * Encrypts the data using the AES cipher and includes the IV as part of the output.
     *
     * This method automatically generates a random initialization vector (IV) for each
     * encryption operation. The IV is prepended to the encrypted data to ensure that
     * it can be extracted and used for decryption. The resulting output is Base64-encoded
     * for safe storage or transmission.
     *
     * **Important:** The IV is not a secret and does not compromise the security of the
     * encrypted data. It is required to ensure that repeated encryption of the same plaintext
     * with the same key produces different ciphertexts (providing semantic security).
     *
     * @return string The Base64-encoded encrypted data, including the IV prepended to the ciphertext.
     *
     * @throws \RuntimeException If the encryption fails.
     */
    public function encrypt(): string
    {
        if (empty($this->getKey())) {
            throw new \InvalidArgumentException("Please set key.");
        }
        if (empty($this->getData())) {
            throw new \InvalidArgumentException("Please set data.");
        }

        // Generate a random IV if none is already set
        if (empty($this->iv)) {
            $this->generateIv();
        }

        // Check for tag support
        if (str_ends_with($this->method, '-gcm') || str_ends_with($this->method, '-ccm'))
        {
            $tag = '';
            $encryptedData = openssl_encrypt(
                $this->data,
                $this->method,
                $this->key,
                OPENSSL_RAW_DATA,
                $this->iv,
                $tag
            );

            if ($encryptedData === false) {
                throw new \RuntimeException('Encryption failed');
            }

            // Prepend the IV to the encrypted data
            return base64_encode($this->iv . $encryptedData . $tag);
        }

        $encryptedData = openssl_encrypt(
            $this->data,
            $this->method,
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        if ($encryptedData === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Prepend the IV to the encrypted data
        return base64_encode($this->iv . $encryptedData);
    }

    /**
     * Decrypts the encrypted data using the provided encryption method, key, and IV.
     * The method ensures that a valid key and data are set before attempting decryption.
     * Extracts the initialization vector (IV) and ciphertext from the encoded data,
     * and performs the decryption process.
     *
     * @return string|false Returns the decrypted string if successful, or false on failure.
     *
     * @throws \InvalidArgumentException If the key or data is not set.
     * @throws \RuntimeException If decryption fails.
     */
    public function decrypt(): string|false
    {
        $rawData = $this->getData();
        if (empty($this->getKey())) {
            throw new \InvalidArgumentException("Please set key.");
        }
        if (empty($rawData)) {
            throw new \InvalidArgumentException("Please set data.");
        }

        // Decode Base64 payload to raw binary
        $rawData = base64_decode($rawData);

        // Get the IV length for the current cipher method
        $ivLength = $this->getIvLength();

        // Extract IV and ciphertext
        $this->iv = substr($rawData, 0, $ivLength); // IV is the first part (first 16 bytes for AES-256-CBC)

        // Check for tag support
        if (str_ends_with($this->method, '-gcm') || str_ends_with($this->method, '-ccm'))
        {
            $ciphertext = substr($rawData , 12, -16);  // Middle: Ciphertext
            $tag = substr($rawData, -16);  // Last 16 bytes: Authentication tag

            // Return the decrypted data, or null if decryption fails
            return openssl_decrypt(
                $ciphertext,
                $this->method,
                $this->key,
                OPENSSL_RAW_DATA,
                $this->iv,
                $tag
            );
        }

        // Decrypt
        $ciphertext = substr($rawData, $ivLength); // Ciphertext is the remainder
        return openssl_decrypt(
            $ciphertext,
            $this->method,
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );
    }
}