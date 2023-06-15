<?php

namespace App\Support\Billing\Water;


use GuzzleHttp\Client;

interface WaterBillInterface
{
    /**
     * Login.
     *
     * @return void
     */
    public function login(): void;

    /**
     * Http client.
     *
     * @return Client
     */
    public function getClient(): Client;
}
