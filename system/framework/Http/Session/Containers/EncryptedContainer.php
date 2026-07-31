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
namespace System\Http\Session\Containers;

use System\Collections\Dictionary;
use System\Security\Cryptography\Aes;

/**
 * Class EncryptedSession
 *
 * Represents a secure session that encrypts and decrypts its data to ensure confidentiality.
 * This class makes use of AES encryption for secure storage and retrieval of session data.
 *
 * @package System\Security\Session
 */
class EncryptedContainer implements SessionDataInterface
{
    /**
     * AES encryptor used for encryption and decryption.
     *
     * @var Aes
     */
    protected Aes $encryptor;

    /**
     * Dictionary container for storing session data.
     *
     * @var Dictionary
     */
    private Dictionary $container;

    /**
     * @var bool
     */
    private $hasChanges = false;

    /**
     * EncryptedSession constructor.
     *
     * Initializes the AES encryptor with a secure key and prepares a container for session data.
     */
    public function __construct()
    {
        $encryptionKey = \System::Config()->get('security_seed');
        $this->encryptor = new Aes('', $encryptionKey);
        $this->container = new Dictionary();
    }

    /**
     * Encrypts a given value into a secure string format.
     *
     * @param mixed $value The value to be encrypted, which can be of any type.
     *
     * @return string The encrypted representation of the value.
     *
     * @throws \JsonException If the value cannot be converted to JSON.
     */
    protected function encrypt(mixed $value): string
    {
        $value = json_encode($value, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $this->encryptor->setData($value);
        $this->encryptor->generateIv();
        return $this->encryptor->encrypt();
    }

    /**
     * Decrypts the given encrypted value.
     *
     * @param string $value The encrypted string to decrypt.
     *
     * @return array|false The decrypted value as an associative array, or false on failure.
     */
    protected function decrypt(string $value): array|false
    {
        $this->encryptor->setData($value);
        $decrypted = $this->encryptor->decrypt();
        return $decrypted !== false ? json_decode($decrypted, true) : false;
    }

    /**
     * Retrieves a value from the session by its key.
     *
     * @param string $key The key associated with the desired value.
     * @param mixed $default The default value to return if the key does not exist.
     *
     * @return mixed The value associated with the specified key, or the default value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->container->getValueOrDefault($key, $default);
    }

    /**
     * Sets a key-value pair in the session.
     *
     * @param string $key The key under which the value will be stored.
     * @param mixed $value The value to store.
     *
     * @throws \Exception If the value cannot be added to the container.
     */
    public function set(string $key, mixed $value): void
    {
        $this->hasChanges = true;
        $this->container->add($key, $value);
    }

    /**
     * Checks whether a given key exists in the session.
     *
     * @param string $key The key to check.
     *
     * @return bool True if the key exists; otherwise, false.
     */
    public function has(string $key): bool
    {
        return $this->container->containsKey($key);
    }

    /**
     * Removes a key-value pair from the session.
     *
     * @param string $key The key to remove.
     *
     * @throws \Exception If the key cannot be removed.
     */
    public function remove(string $key): void
    {
        $this->hasChanges = true;
        $this->container->remove($key);
    }

    /**
     * Clears all data from the session.
     *
     * @throws \Exception If the container cannot be cleared.
     */
    public function clear(): void
    {
        $this->hasChanges = true;
        $this->container->clear();
    }

    /**
     * Initializes the session with data from an encrypted string.
     *
     * @param string $data The encrypted string containing serialized session data.
     *
     * @throws \Exception If the data cannot be decrypted or loaded into the container.
     */
    public function initialize(string $data): void
    {
        $decoded = $this->decrypt($data);
        if ($decoded !== false) {
            $this->container->mergeLeft($decoded);
        }
    }

    /**
     * Encodes the contents of the session container into an encrypted string.
     *
     * @return string The encrypted string representation of the container's data.
     *
     * @throws \JsonException If the container data cannot be serialized to JSON.
     */
    public function encode(): string
    {
        return $this->encrypt($this->container->toArray());
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        return $this->container->toArray();
    }

    /**
     * @inheritDoc
     */
    public function hasChanges(): bool
    {
        return $this->hasChanges;
    }
}
