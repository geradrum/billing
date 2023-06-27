<?php

namespace App\Support\Billing\Water\SIAPA;

use App\Models\Bill;
use App\Models\Company;
use App\Models\Credentials;
use App\Models\Service;
use App\Support\Billing\Water\WaterBillInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SIAPA implements WaterBillInterface
{
    /**
     * Credentials.
     *
     * @var Credentials
     */
    protected Credentials $credentials;

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
        $this->credentials = Credentials::updateOrCreate([
            'user' => $user,
            'company_id' => Company::firstWhere(['code' => 'siapa'])->id,
        ], [
            'password' => $password
        ]);
        $this->client = new Client([
            'base_uri' => config('water.siapa.uri'),
            'cookies' => new FileCookieJar(
                Storage::disk('local')->path("cookies/water/siapa/{$this->credentials->user}.cookies"),
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
                    'txtUsuario1' => $this->credentials->user,
                    'txtContra1' => $this->credentials->password,
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
        $this->downloadBills($services);
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
                'company_id' => Company::firstWhere(['code' => 'siapa'])->id,
                'credentials_id' => $this->credentials->id,
                'contract_number' => $service['id'],
            ], [
                'names' => $service['names'],
                'address' => $service['address'],
            ]);
        }
    }

    /**
     * Download bills to storage.
     *
     * @param array $services
     * @return void
     * @throws GuzzleException
     */
    protected function downloadBills(array $services): void
    {
        foreach ($services as $index => $service) {
            $path = "bills/water/siapa/{$service['id']}";
            $state = Extractor::getStateObject(
                $this->client->request('GET', '/RegistroWeb/webform2.aspx')
            );
            $this->downloadBill($state, $service, $index + 2, $path);
        }
    }

    /**
     * Download PDF.
     *
     * @param $state
     * @param $service
     * @param $index
     * @param $path
     * @return void
     * @throws GuzzleException
     */
    protected function downloadBill($state, $service, $index, $path): void
    {
        if (! Storage::disk('local')->directoryExists($path)) {
            makeDirectory(Storage::path($path), 0755, true);
        }
        $pdf = $this->client->request('POST', '/RegistroWeb/webform2.aspx', [
            'form_params' => [
                ...$state,
                "dgCuentas\$ctl0$index\$imgb2.x" => rand(10, 15),
                "dgCuentas\$ctl0$index\$imgb2.y" => rand(10, 15),
            ],
        ])->getBody()->getContents();
        $filename = Str::orderedUuid();
        $fullPath = "/$path/$filename.pdf";
        if (Storage::disk('local')->put($fullPath, $pdf)) {
            $this->registerBill($service, $fullPath);
        }
    }

    /**
     * Register bill in DB.
     *
     * @param $service
     * @param $path
     * @return void
     */
    protected function registerBill($service, $path): void
    {
        $data = Extractor::extractPdfData($path);
        $serviceModel = Service::firstWhere([
            'company_id' => Company::firstWhere(['code' => 'siapa'])->id,
            'contract_number' => $service['id'],
            'credentials_id' => $this->credentials->id,
        ]);
        $bill = Bill::firstWhere([
            'service_id' => $serviceModel->id,
            'month' => $data['date'],
        ]);
        if (! is_null($bill)) {
            Storage::disk('local')->delete($path);
            $bill->update([
                'amount' => $service['amount'],
                'status' => 'pending',
            ]);
        }

        if (is_null($bill)) {
            Bill::create([
                'service_id' => $serviceModel->id,
                'pdf' => $path,
                'status' => 'pending',
                'month' => $data['date'],
                'amount' => $service['amount'],
            ]);
        }
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
