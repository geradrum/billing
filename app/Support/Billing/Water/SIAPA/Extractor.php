<?php

namespace App\Support\Billing\Water\SIAPA;

use App\Support\Billing\BaseExtractor;
use Psr\Http\Message\ResponseInterface;

class Extractor extends BaseExtractor
{
    /**
     * Get state object.
     *
     * @param ResponseInterface $response
     * @return array
     */
    public static function getStateObject(ResponseInterface $response): array
    {
        $state = [];
        $html = self::parseHtml($response);
        $inputs = $html->find('input');
        foreach ($inputs as $input) {
            $state[$input->name] = $input->value;
        }
        return $state;
    }

    /**
     * Check login status.
     *
     * @param ResponseInterface $response
     * @return bool
     */
    public static function isLoggedIn(ResponseInterface $response): bool
    {
        return false;
    }

    /**
     * Get session status array.
     *
     * @param ResponseInterface $response
     * @return array
     */
    public static function getSessionStatus(ResponseInterface $response): array
    {
        $document = self::parseHtml($response);

        $incorrectPassword = collect($document->find('#cvMensajes1 > font')->toArray())->first();

        if (! is_null($incorrectPassword) && $incorrectPassword->text() === 'Contraseña incorrecta') {
            return [
                'status' => 422,
                'message' => 'Incorrect password',
            ];
        }

        if (! is_null($incorrectPassword) && $incorrectPassword->text() === 'Usuario no registrado') {
            return [
                'status' => 422,
                'message' => 'Incorrect user',
            ];
        }

        $sessionIdentifer = $document->find('#lblTitulo')->toArray();

        if (count($sessionIdentifer) === 0) {
            return [
                'status' => 500,
                'message' => 'Server error',
            ];
        }

        return [
            'status' => 200,
            'message' => 'Logged in',
        ];
    }
}
