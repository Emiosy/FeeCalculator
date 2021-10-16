<?php

namespace App\Service;

use App\Entity\Customer;
use App\Exception\FileException;
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
            $this->customers->get((int)$data[1]);
            dd($data);
        }

        dd($this->customers);

        return true;
    }

    /**
     * Check if inside collection persis Customer
     *
     * @param int $customerId CustomerId to find
     *
     * @return bool Status if exist
     */
    private function checkIfCustomerInCollection(int $customerId): bool
    {
        return $this->customers->exists(function ($key, $customer) use ($customerId) {
            return $customer->getCustomerId() === $customerId;
        });
    }
}
