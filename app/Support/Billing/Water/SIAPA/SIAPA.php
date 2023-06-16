<?php

namespace App\Support\Billing\Water\SIAPA;

use App\Models\Company;
use App\Models\Service;
use App\Support\Billing\Water\WaterBillInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SIAPA implements WaterBillInterface
{
    /**
     * User.
     *
     * @var string
     */
    protected string $user;

    /**
     * Password
     *
     * @var string
     */
    protected string $password;

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
        $status = Extractor::getSessionStatus(
            $this->client->request('POST', '/RegistroWeb/IngresoSD.aspx', [
                'form_params' => [
                    ...$state,
                    'txtUsuario1' => $this->user,
                    'txtContra1' => $this->password,
                ],
            ])
        );
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
     * @throws GuzzleException|ValidationException
     */
    public function getServices(): array
    {
        $this->login();

        $services = Extractor::getServices(
            $this->client->request('GET', '/RegistroWeb/webform2.aspx')
        );

        foreach ($services as &$service) {
            $amount = Extractor::getAmount(
                $this->client->request('GET', '/RegistroWeb/PagarVerif.aspx', [
                    'query' => [
                        'pcta' => $service['id'],
                    ],
                ])
            );
            $service['amount'] = $amount;
        }
        // Register
        $this->registerServices($services);
        return $services;
    }

    /**
     * Register services to DB.
     *
     * @param array $services
     * @return void
     */
    protected function registerServices(array $services): void
    {
        foreach ($services as $service) {
            Service::updateOrCreate([
                'company_id' => Company::firstWhere(['name' => 'SIAPA'])->id,
                'contract_number' => $service['id'],
            ], [
                'names' => $service['names'],
                'address' => $service['address'],
            ]);
        }
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

    /**
     * Get HTTP client.
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
