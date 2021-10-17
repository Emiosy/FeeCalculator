<?php

namespace App;

use Symfony\Component\DependencyInjection\ContainerInterface;

trait CurrenciesConfigParserTrait
{
    /**
     * Get parsed array with config about currencies.
     *
     * @return array parsed array with config
     */
    public function getParsedCurrenciesConfig(ContainerInterface $container, string $nameOfParameter): array
    {
        $configArray = [];
        foreach ($container->getParameter("currencies.{$nameOfParameter}") as $calledConfig) {
            $configArray[$calledConfig[array_key_first($calledConfig)]] = $calledConfig[array_key_last($calledConfig)];
        }

        return $configArray;
    }

    /**
     * Get parsed nested array with config about currencies.
     *
     * @return array parsed array with config
     */
    public function getParsedCurrenciesNestedConfig(ContainerInterface $container, string $nameOfParameter): array
    {
        $configArray = [];
        foreach ($container->getParameter("currencies.{$nameOfParameter}") as $calledConfig) {
            $innerConfigArray = [];
            foreach ($calledConfig[array_key_last($calledConfig)] as $innerConfig) {
                $innerConfigName = $innerConfig[array_key_first($innerConfig)];
                $innerConfigArray[$innerConfigName] = $innerConfig[array_key_last($innerConfig)];
            }
            $configArray[$calledConfig[array_key_first($calledConfig)]] = $innerConfigArray;
        }

        return $configArray;
    }

    /**
     * Get plain value with config about currencies.
     *
     * @return array|bool|float|int|string|null Plain value with config
     */
    public function getPlainCurrenciesConfig(ContainerInterface $container, string $nameOfParameter)
    {
        return $container->getParameter("currencies.{$nameOfParameter}");
    }
}
