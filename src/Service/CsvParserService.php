<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Transaction;
use App\Exception\FileException;
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

    public function importCustomersAndTransactions(string $filePath, array $acceptedCurrencies): bool
    {
        $fileToRead = fopen($filePath, "r");
        while (($data = fgetcsv($fileToRead, 1000, ",")) !== false) {
            //Check if user exist in Collection (if not - add to collection)
            if (!$this->customers->containsKey((int)$data[1])) {
                $this->customers->set((int)$data[1], new Customer((int)$data[1], (string)$data[2]));
            }
            $transaction = $this->parseTransactionToObject(
                (string)$data[0],
                (string)$data[3],
                (string)$data[4],
                (string)$data[5]
            );
            /** @var Customer $customer */
            $customer = $this->customers->get((int)$data[1]);
            $customer->addNewTransaction($transaction);
        }

        dd($this->customers);

        return true;
    }

    private function parseTransactionToObject(string $date, string $type, string $amount, string $currency)
    {
        $date = Carbon::createFromFormat('Y-m-d', $date);
        return new Transaction($date, $type, $amount, $currency);
    }
}
