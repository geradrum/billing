<?php

namespace App\Support\Billing\Water\SADM;

use App\Support\Billing\Water\WaterBillInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Support\Facades\Storage;
use PHPHtmlParser\Dom;

class SADM implements WaterBillInterface
{

    protected Client $client;

    public function __construct($user, $password)
    {
        $this->user = $user;
        $this->password = $password;
        $cookieJar = new FileCookieJar(
            Storage::disk('local')->path("cookies/water/sadm/{$this->user}.cookies"),
            true
        );
        $this->client = new Client([
            'base_uri' => config('water.sadm.uri'),
            'cookies' => $cookieJar,
            'allow_redirects' => true,
            'http_errors' => false,
        ]);
    }

    public function getServices()
    {
        $response = $this->client->request('POST', '/eAyd/autenticacione', [
            'form_params' => [
                'command'=> '',
                'email' => $this->user,
                'password' => $this->password,
            ]
        ]);

        $services = $this->parseServices($response->getBody()->getContents());

        return $services;
    }



    public function getBill()
    {
        return null;
    }

    public function getBills()
    {

    }

    protected function parseHtml($html)
    {
        $dom = new Dom;
        return $dom->loadStr($html);
    }

    protected function parseServices($html)
    {
        $dom = $this->parseHtml($html);
        $trs = collect($dom->find('table#tabla_servicios1 > tbody > tr')->toArray());
        $trs = $trs->filter(function ($tr, $index) use ($trs) {
            return !in_array($index, [0, $trs->count() - 1]);
        });
        $services = $trs->filter(function ($tr) {
            return $tr->count() !== 7;
        })->map(function ($tr) {

            return [
                'id' => str_replace('&nbsp;', '', $tr->find('td')[1]?->text()),
                'address' => trim($tr->find('td')[3]?->text()),
                'cutoff_date' => str_replace('&nbsp;', '', $tr->find('td')[4]?->text()),
                'amount' => str_replace('&nbsp;', '', $tr->find('td')[5]?->text()),
                'status' => $tr->find('td')[6]->find('font')[0]?->text(),
            ];
        })->values();
        $urls = $this->parseUrls($html);
        return $services->map(function ($service) use ($urls) {
            $url = $urls->first(function ($url) use ($service) {
                return preg_match("/{$service['id']}/", $url);
            });
            $month = substr($url, 0 , 3);
            $service['bill_url'] = preg_replace('/^[A-Za-z]{3}\|/', '', $url);
            $service['month'] = $month;
            return $service;
        });

    }

    protected function parseUrls($html)
    {
        $dom = $this->parseHtml($html);
        return collect($dom->find('a')->toArray())->filter(function ($a) {
            return preg_match('#^https://ayd\.sadm\.gob\.mx/Solicitudes/solicitudcfdi\?idpdf=#', $a->href);
        })->map(function ($a) {
            $month = strtolower(substr($a->text(), 0 , 3));
            return "{$month}|{$a->href}";
        })->values()->unique();
    }

    public function login(): void
    {
        return ;
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
