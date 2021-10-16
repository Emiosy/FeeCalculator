<?php

namespace App\Service;

use App\Entity\Transaction;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;

class CommissionFeeService
{
    /**
     * Parsed customers with transactions.
     *
     * @var ArrayCollection
     */
    private ArrayCollection $transactions;

    /**
     * Collection with calculated commission fees
     *
     * @var ArrayCollection Commission fees
     */
    private ArrayCollection $commissionFees;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
        $this->commissionFees = new ArrayCollection();
    }

    public function calculateFee(string $filePath, array $acceptedCurrencies, array $currencyRates): ArrayCollection
    {
        $this->importAndParseTransactions($filePath, $acceptedCurrencies);
        return $this->commissionFees;
    }

    public function importAndParseTransactions(string $filePath, array $acceptedCurrencies): ArrayCollection
    {
        $fileToRead = fopen($filePath, "r");
        while (($data = fgetcsv($fileToRead, 1000, ",")) !== false) {
            //Check if currency is acceptable
            if (in_array((string)$data[5], array_keys($acceptedCurrencies))) {
                $transaction = $this->parseTransactionToObject($data, $acceptedCurrencies[(string)$data[5]]);

                $this->transactions->add($transaction);
            }
        }

        return $this->transactions;
    }

    /**
     * Parse data to Transaction object
     *
     * @param array $rowFromCsv Array with parsed row from CSV
     * @param int $decimalsOfCurrency Decimals of currency
     *
     * @return Transaction
     */
    private function parseTransactionToObject(array $rowFromCsv, int $decimalsOfCurrency): Transaction
    {
        $carbonDate = Carbon::createFromFormat('Y-m-d', (string)$rowFromCsv[0]);
        $parsedAmount = ($rowFromCsv[4] * pow(10, $decimalsOfCurrency));

        return new Transaction(
            $carbonDate,
            (int)$rowFromCsv[1],
            (string)$rowFromCsv[2],
            (string)$rowFromCsv[3],
            (int)$parsedAmount,
            (string)$rowFromCsv[5]
        );
    }
}
