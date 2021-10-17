<?php

namespace App\Service;

use App\CurrenciesConfigParserTrait;
use App\Exception\ExchangeRatesException;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExchangeRatesService
{
    use CurrenciesConfigParserTrait;

    private HttpClientInterface $client;
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container, HttpClientInterface $client)
    {
        $this->container = $container;
        $this->client = $client;
    }

    /**
     * Download the latest exchange rates.
     *
     * @param string $apiEndpoint Api endpoint URL
     * @param string $apiKey Api endpoint key
     *
     * @return Exception|mixed|ClientExceptionInterface|DecodingExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface
     */
    public function downloadLatestExchangeRates(string $apiEndpoint, string $apiKey)
    {
        try {
            $response = $this->client->request(
                'GET',
                $apiEndpoint,
                [
                    'query' => [
                        'access_key' => $apiKey,
                        'base' => $this->getPlainCurrenciesConfig($this->container, 'default'),
                        'symbols' => implode(',', array_keys($this->getParsedCurrenciesConfig(
                            $this->container,
                            'accept'
                        ))),
                    ]
                ]
            );

            if ($response->getStatusCode() === 200) {
                if (!empty($response->toArray()) && isset($response->toArray()['rates'])) {
                    return $response->toArray()['rates'];
                }

                throw new ExchangeRatesException("Rates not found at expected place.");
            }

            throw new ExchangeRatesException("Expected status code 200, received {$response->getStatusCode()}");
        } catch (
            TransportExceptionInterface |
            ClientExceptionInterface |
            DecodingExceptionInterface |
            ServerExceptionInterface |
            RedirectionExceptionInterface $e
        ) {
            throw new ExchangeRatesException("Error with HTTP Client: {$e->getMessage()}");
        }
    }

    /**
     * Change currency from base to foreign of transaction using exchange rate.
     *
     * @param string $valueToChange Value to change currency
     * @param string $exchangeRate Exchange rate
     *
     * @return string Value at new currency
     */
    public function changeCurrencyFromBaseToForeign(string $valueToChange, string $exchangeRate): string
    {
        return bcdiv($valueToChange, $exchangeRate, 10);
    }
}
