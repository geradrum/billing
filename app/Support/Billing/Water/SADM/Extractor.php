<?php

namespace App\Support\Billing\Water\SADM;

use App\Support\Billing\BaseExtractor;
use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface;

class Extractor extends BaseExtractor
{
    /**
     * Get session status array.
     *
     * @param ResponseInterface $response
     * @return array
     */
    public static function getSessionStatus(ResponseInterface $response): array
    {
        $document = self::parseHtml($response);

        return [
            'status' => 200,
            'message' => 'Logged in',
        ];
    }

    /**
     * Get services.
     *
     * @param ResponseInterface $response
     * @return array
     */
    public static function getServices(ResponseInterface $response): array
    {
        $document = self::parseHtml($response);
        $trs = collect($document->find('table#tabla_servicios1 > tbody > tr')->toArray());
        $trs = $trs->filter(function ($tr, $index) use ($trs) {
            return !in_array($index, [0, $trs->count() - 1]);
        });
        return $trs->filter(function ($tr) {
            return $tr->count() !== 7;
        })->map(function ($tr) {
            return [
                'id' => str_replace('&nbsp;', '', $tr->find('td')[1]?->text()),
                'names' => null,
                'address' => trim($tr->find('td')[3]?->text()),
                'cutoff_date' => str_replace('&nbsp;', '', $tr->find('td')[4]?->text()),
                'amount' => str_replace('&nbsp;', '', $tr->find('td')[5]?->text()),
                'status' => $tr->find('td')[6]->find('font')[0]?->text(),
            ];
        })->values()->toArray();
    }

    /**
     * Get bill urls.
     *
     * @param ResponseInterface $response
     * @return array
     */
    public static function getUrls(ResponseInterface $response): array
    {
        $document = self::parseHtml($response->getBody()->getContents());
        return collect($document->find('a')->toArray())->filter(function ($a) {
            return preg_match('#^https://ayd\.sadm\.gob\.mx/Solicitudes/solicitudcfdi\?idpdf=#', $a->href);
        })->map(function ($a) {
            $date = str_replace('&nbsp;', '', $a->text());
            $dateArray = explode(' ', trim(preg_replace('/\s+\(pdf\)|\s+\(xml\)/', '', $date)));
            $date = implode('-', [$dateArray[1], getMonth($dateArray[0])]);
            return "{$date}|{$a->href}";
        })->values()->unique()->toArray();
    }

    /**
     * Merge arrays.
     *
     * @param array $services
     * @param array $urls
     * @return array
     */
    public static function mergeServicesAndUrls(array $services, array $urls): array
    {
        $services = collect($services);
        $urls = collect($urls);
        return $services->map(function ($service) use ($urls) {
            $url = $urls->first(function ($url) use ($service) {
                return preg_match("/{$service['id']}/", $url);
            });
            preg_match('/^[0-9]{4}-[0-9]{2}/', $url, $matches);
            $service['last_bill'] = [
                'url' => preg_replace('/^[0-9]{4}-[0-9]{2}\|/', '', $url),
                'date' => $matches[0] ?? null,
            ];
            return $service;
        })->toArray();
    }
}
