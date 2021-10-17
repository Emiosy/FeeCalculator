<?php

namespace App;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

trait CurrenciesConfigParserTrait
{
    /**
     * Get parsed array with config about currencies.
     *
     * @return array Parsed array with config.
     */
    public function getParsedCurrenciesConfig(ParameterBagInterface $params, string $nameOfParameter): array
    {
        $configArray = [];
        foreach ($params->get("currencies.{$nameOfParameter}") as $calledConfig) {
            $configArray[$calledConfig[array_key_first($calledConfig)]] = $calledConfig[array_key_last($calledConfig)];
        }

        return $configArray;
    }

    /**
     * Get plain value with config about currencies.
     *
     * @return array|bool|float|int|string|null Plain value with config
     */
    public function getPlainCurrenciesConfig(ParameterBagInterface $params, string $nameOfParameter)
    {
        return $params->get("currencies.{$nameOfParameter}");
    }
}
