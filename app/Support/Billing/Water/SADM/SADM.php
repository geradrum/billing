<?php

namespace App\Support\Billing\Water\SADM;

use App\Models\Bill;
use App\Models\Company;
use App\Models\Service;
use App\Support\Billing\Water\WaterBillInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SADM implements WaterBillInterface
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
     * SADM constructor.
     *
     * @param $user
     * @param $password
     */
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

    /**
     * Log in.
     *
     * @throws GuzzleException
     */
    public function login(): void
    {
        $status = Extractor::getSessionStatus(
            $this->client->request('POST', '/eAyd/autenticacione', [
                'form_params' => [
                    'command'=> '',
                    'email' => $this->user,
                    'password' => $this->password,
                ]
            ])
        );
    }

    /**
     * Get SIAPA active services.
     *
     * @throws GuzzleException|ValidationException
     */
    public function getServices()
    {
        $this->login();
        $services = Extractor::getServices($this->client->request('GET', '/eAyd/Inicio.jsp'));
        $urls = Extractor::getUrls($this->client->request('GET', '/eAyd/Inicio.jsp'));
        $services = Extractor::mergeServicesAndUrls($services, $urls);
        $this->registerServices($services);
        $this->downloadBills($services);
        return $services;
    }

    /**
     * Register bill in DB.
     *
     * @param $bill
     * @param $service
     * @return void
     */
    protected function registerBill($bill, $service): void
    {
        $serviceModel = Service::firstWhere([
            'company_id' => Company::firstWhere(['code' => 'sadm'])->id,
            'contract_number' => $service['id']
        ]);
        $month = Arr::get($service, 'last_bill.date');
        $bill->fill([
            'service_id' => $serviceModel->id,
            'month' => "$month-01",
            'amount' => $service['amount'],
            'status' => 'pending',
        ]);
        $bill->save();
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
                'company_id' => Company::firstWhere(['code' => 'sadm'])->id,
                'contract_number' => $service['id'],
            ], [
                'names' => $service['names'],
                'address' => $service['address'],
            ]);
        }
    }

    protected function downloadBill(string $url, string $path, $filename)
    {
        $pdf = $this->client->request('GET', $url)->getBody()->getContents();
        if (! Storage::disk('local')->directoryExists($path)) {
            makeDirectory(Storage::path($path), 0755, true);
        }
        Storage::disk('local')->put("/$path/$filename.pdf", $pdf);
        return "/$path/$filename.pdf";
    }

    /**
     * Download bills to storage.
     *
     * @param array $services
     * @return void
     */
    protected function downloadBills(array $services): void
    {
        foreach ($services as $service) {
            $month = Arr::get($service, 'last_bill.date');
            $serviceModel = Service::firstWhere([
                'company_id' => Company::firstWhere(['code' => 'sadm'])->id,
                'contract_number' => $service['id']
            ]);
            $bill = Bill::firstWhere([
                'service_id' => $serviceModel->id,
                'month' => "$month-01",
            ]);
            if (is_null($bill)) {
                $bill = new Bill;
                $path = "bills/water/sadm/{$service['id']}";
                $filename = Str::orderedUuid();
                $bill->pdf = $this->downloadBill(Arr::get($service, 'last_bill.url'), $path, $filename);
                $this->registerBill($bill, $service);
            }
            $bill->update([
                'amount' => $service['amount'],
                'status' => 'pending',
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
