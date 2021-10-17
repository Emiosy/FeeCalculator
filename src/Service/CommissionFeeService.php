<?php

namespace App\Service;

use App\CurrenciesConfigParserTrait;
use App\Entity\Transaction;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CommissionFeeService
{
    use CurrenciesConfigParserTrait;

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

    /**
     * Array with parsed deposit fees
     *
     * @var array Deposit fees
     */
    private array $depositFees;

    public function __construct(ParameterBagInterface $params)
    {
        $this->depositFees = $this->getParsedCurrenciesConfig($params, 'deposit');
        $this->transactions = new ArrayCollection();
        $this->commissionFees = new ArrayCollection();
    }

    /**
     * Calculate fee
     *
     * @param string $filePath Path to CSV file
     * @param array $acceptedCurrencies Array with accepted currencies
     * @param array $currencyRates Array with currency rates
     *
     * @return ArrayCollection Array collection with calculated commission fees
     */
    public function calculateFee(string $filePath, array $acceptedCurrencies, array $currencyRates): ArrayCollection
    {
        $this->readAndParseTransactions($filePath, $acceptedCurrencies);
        $this->analyzeTransactions($this->transactions, $currencyRates);

        return $this->commissionFees;
    }

    /**
     * Analyze and count commissions for all transactions at collection
     *
     * @param ArrayCollection $transactions Array collection with transactions to analyze
     * @param array $currencyRates Array with currency rates
     *
     * @return ArrayCollection Array collection with calculated commission fees
     */
    public function analyzeTransactions(ArrayCollection $transactions, array $currencyRates): ArrayCollection
    {
        if (!$transactions->isEmpty()) {
            foreach ($transactions as $transaction) {
                /** @var Transaction $transaction */
                $fee = (
                    ($transaction->getTransactionType() === 1) ?
                    $this->processDeposit($transaction) :
                    $this->processWithdraw($transaction, $currencyRates)
                );
                dd($fee);
                $this->commissionFees->add($fee);
            }
        }
        return $this->commissionFees;
    }

    /**
     * Read and parse CSV file with transactions.
     *
     * @param string $filePath Path to CSV file
     * @param array $acceptedCurrencies Array with accepted currencies
     *
     * @return ArrayCollection Array collection with Transaction objects
     */
    public function readAndParseTransactions(string $filePath, array $acceptedCurrencies): ArrayCollection
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
     * @return Transaction Transaction object
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
            (string)$rowFromCsv[5],
            $decimalsOfCurrency
        );
    }

    /**
     * Process deposit transaction and calculate commission.
     *
     * @param Transaction $transaction Transaction to process
     *
     * @return float Amount of commission
     */
    private function processDeposit(Transaction $transaction): float
    {
        //Calculate correct fee depending on type of account
        $fee = bcdiv($this->depositFees[$transaction->getCustomerTypeAsString()], 100, 10);
        //Calculate commission
        $commission = bcmul($transaction->getTransactionAmountAsString(), $fee, 10);

        return round($commission, $transaction->getAmountDecimalPlaces(), PHP_ROUND_HALF_UP);
    }

    private function processWithdraw(Transaction $transaction, array $currencyRates)
    {
        return 'WITH';
    }
}
