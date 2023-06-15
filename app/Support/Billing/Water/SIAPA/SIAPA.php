<?php

namespace App\Support\Billing\Water\SIAPA;

use App\Support\Billing\Water\WaterBillInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PHPHtmlParser\Dom;

class SIAPA implements WaterBillInterface
{

    /**
     * Http client.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * SIAPA constructor.
     *
     * @param $user
     * @param $password
     */
    public function __construct($user, $password)
    {
        $this->user = $user;
        $this->password = $password;
        $this->client = new Client([
            'base_uri' => config('water.siapa.uri'),
            'cookies' => new FileCookieJar(
                Storage::disk('local')->path("cookies/water/siapa/{$this->user}.cookies"),
                true
            ),
            'allow_redirects' => true,
            'http_errors' => false,
        ]);
    }

    /**
     * Log in.
     *
     * @throws GuzzleException|ValidationException
     */
    public function login(): void
    {
        $state = Extractor::getStateObject(
            $this->client->request('GET', '/RegistroWeb/IngresoSD.aspx')
        );
        $login = $this->client->request('POST', '/RegistroWeb/IngresoSD.aspx', [
            'form_params' => [
                ...$state,
                'txtUsuario1' => $this->user,
                'txtContra1' => $this->password,
            ],
        ]);
        $status = Extractor::getSessionStatus($login);
        try {
            $validator = Validator::make($status, [
                'status' => 'not_in:422,500',
            ]);
            $validator->validate();
        } catch (ValidationException $exception) {
            throw $exception;
        }
    }

    /**
     * Get SIAPA active services.
     *
     * @throws GuzzleException
     */
    public function getServices(): Response
    {
        $this->login();

        $state = Extractor::getStateObject(
            $this->client->request('GET', '/RegistroWeb/webform2.aspx')
        );

        return new Response((string) $this->downloadBill($state), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="bill.pdf"'
        ]);
    }

    /**
     * Download PDF.
     *
     * @param $state
     * @return string
     * @throws GuzzleException
     */
    protected function downloadBill($state): string
    {
        return $this->client->request('POST', '/RegistroWeb/webform2.aspx', [
            'form_params' => [
                ...$state,
                'dgCuentas$ctl02$imgb2.x' => rand(10, 15),
                'dgCuentas$ctl02$imgb2.y' => rand(10, 15),
            ],
        ])->getBody()->getContents();
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
