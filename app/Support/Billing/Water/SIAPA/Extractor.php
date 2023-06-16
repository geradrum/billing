<?php

namespace App\Support\Billing\Water\SIAPA;

use App\Support\Billing\BaseExtractor;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use PHPHtmlParser\Dom\HtmlNode;
use Psr\Http\Message\ResponseInterface;
use Spatie\PdfToText\Pdf;

class Extractor extends BaseExtractor
{
    public static function getAmount(ResponseInterface $response): string
    {
        $amount = '0.0';
        $document = self::parseHtml($response);
        preg_match('/\$.+$/is', $document->find('#datosCta')->text(), $matches);
        $amount = preg_replace('/[$,*]/', '', $matches[0]);
        if (str_ends_with($amount, '-')) {
            $amount = str_replace('-', '', $amount);
            $amount = floatval("-$amount");
        }
        return $amount;
    }

    /**
     * Extract data from PDF.
     *
     * @param $path
     * @return array
     */
    public static function extractPdfData($path): array
    {
        $text = Pdf::getText(
            Storage::disk('local')->path($path)
        );
        $collection = collect(explode(PHP_EOL, $text));
        $date = $collection->first(function ($line) {
            return preg_match('/\d{2}\.\d{2}\.\d{4} al \d{2}\.\d{2}\.\d{4}/', $line);
        });
        $date = preg_replace('/^\d{2}\.\d{2}\.\d{4} al /', '', $date);
        $date = Carbon::createFromFormat('d.m.Y', $date)->startOfMonth()->format('Y-m-d');
        return [
            'name' => $collection->first(),
            'date' => $date,
        ];
    }

    /**
     * Get state object.
     *
     * @param ResponseInterface $response
     * @return array
     */
    public static function getStateObject(ResponseInterface $response): array
    {
        $state = [];
        $document = self::parseHtml($response);
        $inputs = $document->find('input');
        foreach ($inputs as $input) {
            $state[$input->name] = $input->value;
        }
        return $state;
    }

    /**
     * Get services array.
     *
     * @param ResponseInterface $response
     * @return array
     */
    public static function getServices(ResponseInterface $response): array
    {
        $document = self::parseHtml($response);
        $rows = collect($document->find('table#dgCuentas > tr')->toArray())->forget(0);
        return $rows->map(function (HtmlNode $row) {
            return self::parseServiceRow($row);
        })->values()->toArray();
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
     * Parse table row.
     *
     * @param HtmlNode $tr
     * @return array
     */
    protected static function parseServiceRow(HtmlNode $tr): array
    {
        $tds = collect($tr->find('td > font')->toArray())->slice(0, 3);
        $tds = $tds->map(function (HtmlNode $td) {
            return trim($td->text());
        })->toArray();
        return [
            'id' => Arr::get($tds, 0),
            'names' => Arr::get($tds, 1),
            'address' => Arr::get($tds, 2),
        ];
    }
}
