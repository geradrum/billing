<?php

namespace App\Support\Water\SIAPA;

use App\Support\Water\WaterInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use PHPHtmlParser\Dom;

class SIAPA implements WaterInterface
{

    protected Client $client;

    public function __construct($user, $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    public function getBill()
    {
        $cookieJar = new FileCookieJar(
            Storage::disk('local')->path("cookies/water/siapa/{$this->user}.cookies"),
            true
        );
        $this->client = new Client([
            'base_uri' => config('water.siapa.uri'),
            'cookies' => $cookieJar,
            'allow_redirects' => true,
            'http_errors' => false,
        ]);

        $loginState = $this->getLoginState();
        $response = $this->client->request('POST', '/RegistroWeb/IngresoSD.aspx', [
            'form_params' => [
                'txtUsuario1' => $this->user,
                'txtContra1' => $this->password,
                'btnIngresar' => 'Ingresar',
                '__EVENTTARGET' => '',
                '__EVENTARGUMENT' => '',
                '__VIEWSTATE' => $loginState['__VIEWSTATE'],
                '__VIEWSTATEGENERATOR' => $loginState['__VIEWSTATEGENERATOR'],
                '__EVENTVALIDATION' => $loginState['__EVENTVALIDATION'],
            ],
        ]);

        $body = (string) $response->getBody();

        [$statusCode, $message] = $this->validateLogin($body);

        if ($statusCode !== 200) {
            return response(['message' => $message], $statusCode);
        }

        $bill = $this->downloadBill($this->getMainState($body));

        return new Response((string) $bill->getBody(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="bill.pdf"'
        ]);
    }

    protected function downloadBill($state)
    {
        return $this->client->request('POST', '/RegistroWeb/webform2.aspx', [
            'form_params' => [
                '__EVENTTARGET' => '',
                '__EVENTARGUMENT' => '',
                '__VIEWSTATE' => $state['__VIEWSTATE'],
                '__VIEWSTATEGENERATOR' => $state['__VIEWSTATEGENERATOR'],
                '__EVENTVALIDATION' => $state['__EVENTVALIDATION'],
                'dgCuentas$ctl02$imgb2.x' => rand(10, 15),
                'dgCuentas$ctl02$imgb2.y' => rand(10, 15),
            ],
        ]);
    }

    protected function getLoginState()
    {
        $response = $this->client->request('GET', '/RegistroWeb/IngresoSD.aspx');
        $html = $this->parseHtml((string) $response->getBody());
        $inputs = $html->find('input');
        $state = [];
        foreach ($inputs as $input) {
            $state[$input->name] = $input->value;
        }
        return $state;
    }

    protected function getMainState($html)
    {
        $html = $this->parseHtml($html);
        $inputs = $html->find('input');
        $state = [];
        foreach ($inputs as $input) {
            $state[$input->name] = $input->value;
        }
        return $state;
    }

    protected function validateLogin($html)
    {
        $document = $this->parseHtml($html);

        $incorrectPassword = collect($document->find('#cvMensajes1 > font')->toArray())->first();

        if (! is_null($incorrectPassword) && $incorrectPassword->text() === 'Contraseña incorrecta') {
            return [422, 'Incorrect password'];
        }

        if (! is_null($incorrectPassword) && $incorrectPassword->text() === 'Usuario no registrado') {
            return [422, 'Incorrect user'];
        }

        $sessionIdentifer = $document->find('#lblTitulo')->toArray();

        if (count($sessionIdentifer) === 0) {
            return [500, 'Service error'];
        }

        return [200, 'OK'];
    }

    protected function parseHtml($html)
    {
        $dom = new Dom;
        return $dom->loadStr($html);
    }

}
