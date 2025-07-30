<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class IdEncryptor
{
    /**
     * Encrypts a given ID.
     *
     * @param int|string $id The ID to encrypt.
     * @return string The encrypted ID.
     */
    public static function encrypt($id)
    {
        return Crypt::encryptString($id);
    }

    /**
     * Decrypts a given encrypted ID.
     * Throws DecryptException if the value cannot be decrypted.
     *
     * @param string $encryptedId The encrypted ID to decrypt.
     * @return int|string The decrypted ID.
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public static function decrypt($encryptedId)
    {
        try {
            return Crypt::decryptString($encryptedId);
        } catch (DecryptException $e) {
            // Log the error or handle it as needed.
            // For example, you might return null or re-throw a more specific exception.
            throw $e; // Re-throw the exception to be caught by Laravel's exception handler
        }
    }
}