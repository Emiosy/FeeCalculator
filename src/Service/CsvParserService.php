<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Transaction;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;

class CsvParserService
{
    /**
     * Parsed customers with transactions.
     *
     * @var ArrayCollection
     */
    private ArrayCollection $customers;

    public function __construct()
    {
        $this->customers = new ArrayCollection();
    }

    public function importCustomersAndTransactions(string $filePath, array $acceptedCurrencies): ArrayCollection
    {
        $fileToRead = fopen($filePath, "r");
        while (($data = fgetcsv($fileToRead, 1000, ",")) !== false) {
            //Check if currency is acceptable
            if (in_array((string)$data[5], array_keys($acceptedCurrencies))) {
                //Check if user exist in Collection (if not - add to collection)
                if (!$this->customers->containsKey((int)$data[1])) {
                    $this->customers->set((int)$data[1], new Customer((int)$data[1], (string)$data[2]));
                }

                $transaction = $this->parseTransactionToObject(
                    (string)$data[0],
                    (string)$data[3],
                    (string)$data[4],
                    (string)$data[5],
                    $acceptedCurrencies[(string)$data[5]]
                );

                /** @var Customer $customer */
                $customer = $this->customers->get((int)$data[1]);
                $customer->addNewTransaction($transaction);
            }
        }

        return $this->customers;
    }

    /**
     * Parse data to Transaction object
     *
     * @param string $date Date of transaction
     * @param string $type Type of transaction
     * @param string $amount Amount of transaction
     * @param string $currency Currency of transaction
     * @param int $decimalsOfCurrency Number of decimals at currency
     *
     * @return Transaction
     */
    private function parseTransactionToObject(
        string $date,
        string $type,
        string $amount,
        string $currency,
        int $decimalsOfCurrency
    ): Transaction {
        $date = Carbon::createFromFormat('Y-m-d', $date);
        $amount = ($amount * pow(10, $decimalsOfCurrency));

        return new Transaction($date, $type, (int)$amount, $currency);
    }
}
