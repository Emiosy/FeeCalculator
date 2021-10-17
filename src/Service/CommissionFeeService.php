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
     * Array with decimal places of currencies.
     *
     * @var array
     */
    private array $decimalPlaces;

    /**
     * Array with parsed deposit fees.
     *
     * @var array Deposit fees
     */
    private array $depositFees;

    /**
     * Array with withdraw fees.
     *
     * @var array Withdraw fees
     */
    private array $withdrawFees;

    /**
     * Base currency for calculations.
     *
     * @var string
     */
    private $baseCurrency;

    /**
     * Exchange rates Service
     *
     * @var ExchangeRatesService
     */
    private ExchangeRatesService $exchangeRates;

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

    public function __construct(ParameterBagInterface $params, ExchangeRatesService $exchangeRatesService)
    {
        $this->decimalPlaces = $this->getParsedCurrenciesConfig($params, 'accept');
        $this->depositFees = $this->getParsedCurrenciesConfig($params, 'deposit');
        $this->withdrawFees = $this->getParsedCurrenciesNestedConfig($params, 'withdraw');
        $this->baseCurrency = $this->getPlainCurrenciesConfig($params, 'default');
        $this->exchangeRates = $exchangeRatesService;
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
//                dd($fee);
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
        return $this->calculateCommissionForTransactionAmount(
            $transaction->getTransactionAmountAsString(),
            $transaction->getTransactionCurrency(),
            $this->depositFees[$transaction->getCustomerTypeAsString()]
        );
    }

    /**
     * Process withdraw transaction and calculate commission fee.
     *
     * @param Transaction $transaction Transaction to process
     * @param array $currencyRates Array with currency rates
     *
     * @return float|string Amount of commission
     */
    private function processWithdraw(Transaction $transaction, array $currencyRates)
    {
        //Initialize fee
        $fee = number_format(0, $transaction->getAmountDecimalPlaces(), '.', '');
        //Get all non parsed transaction in billing week of transaction
        $transactionsToCheck = $this->getPastNotParsedTransactions($transaction, 'withdraw');
        //Calculate amount of withdrawn money at billing week
        $moneyWithdrawnAtWeek = $this->calculateMoneyWithdrawnAtWeek($transactionsToCheck, $currencyRates);
        //Check amount over quota
        $amountOverQuota = $this->checkAmountOverQuota(
            $moneyWithdrawnAtWeek,
            $transaction->getTransactionAmount(),
            $this->withdrawFees[$transaction->getCustomerTypeAsString()]['free_quota']
        );

        if (bccomp($amountOverQuota, 0, 10) === 1) {
            $fee = $this->calculateCommissionForTransactionAmount(
                $amountOverQuota,
                $transaction->getTransactionCurrency(),
                $this->withdrawFees[$transaction->getCustomerTypeAsString()]['fee']
            );
        } else {
            //Free of charge, but count transaction with this transaction
            if (
                ($transactionsToCheck->count() + 1)
                    >
                $this->withdrawFees[$transaction->getCustomerTypeAsString()]['free_transactions']
            ) {
                //This is transaction over free amount
                $fee = $this->calculateCommissionForTransactionAmount(
                    $amountOverQuota,
                    $transaction->getTransactionCurrency(),
                    $this->withdrawFees[$transaction->getCustomerTypeAsString()]['fee']
                );
            }
        }

//        if ($transaction->getTransactionCurrency() === 'JPY') {
//            dd($transactionsToCheck, $moneyWithdrawnAtWeek);
//        }

        return $fee;
    }

    /**
     * Calculate commission for transaction amount
     *
     * @param string $amount Amount to calculate fee
     * @param string $currency Currency of transaction to return correct format
     * @param string $fee Amount of fee
     *
     * @return float
     */
    private function calculateCommissionForTransactionAmount(string $amount, string $currency, string $fee): float
    {
        //Calculate correct format of percents
        $percents = bcdiv($fee, 100, 10);
        //Calculate final fee
        $fee = bcmul($amount, $percents, 10);

        return round($fee, $this->decimalPlaces[$currency], PHP_ROUND_HALF_UP);
    }

    /**
     * Check amount over quota.
     *
     * @param string $moneyWithdrawnAtWeek Sum amount of transaction from billing week
     * @param string $amountOfTransaction Amount of actual transaction
     * @param string $withdrawQuota
     *
     * @return string Amount of transaction to calculate fee (If >0 user fee should be calculated)
     */
    private function checkAmountOverQuota(
        string $moneyWithdrawnAtWeek,
        string $amountOfTransaction,
        string $withdrawQuota
    ): string {
        //Check if full amount of transaction is over quota
        if (bccomp($moneyWithdrawnAtWeek, $withdrawQuota, 10) === 1) {
            //All amount is over quota, calculate fee for 100% of transaction
            return $amountOfTransaction;
        } else {
            //Free quota detected, calculate amount that is over quota
            $freeQuota = bcsub($withdrawQuota, $moneyWithdrawnAtWeek, 10);
            $amountOfTransaction = bcsub($amountOfTransaction, $freeQuota);
        }

        //If $amountOfTransaction >0 user fee should be calculated
        return $amountOfTransaction;
    }

    /**
     * Money withdrawn at passed transactions.
     *
     * @param ArrayCollection $transactionToCheck Transaction of withdraw to check
     * @param array $currencyRates Currency rates to do a calculations
     *
     * @return string Amount of withdrawn money at base currency
     */
    private function calculateMoneyWithdrawnAtWeek(ArrayCollection $transactionToCheck, array $currencyRates): string
    {
        $moneyWithdrawn = 0;

        if (!$transactionToCheck->isEmpty()) {
            /** @var Transaction $transaction */
            foreach ($transactionToCheck as $transaction) {
                //Check if transaction currency is at base currency
                if ($transaction->getTransactionCurrency() !== $this->baseCurrency) {
                    $moneyWithdrawn = bcadd($moneyWithdrawn, $this->exchangeRates->changeCurrencyOfValue(
                        (string)$transaction->getTransactionAmount(),
                        (string)$currencyRates[$transaction->getTransactionCurrency()]
                    ), 10);
                } else {
                    $moneyWithdrawn = bcadd($moneyWithdrawn, $transaction->getTransactionAmount(), 10);
                }
            }
        }

        return (string)$moneyWithdrawn;
    }

    /**
     * Get all parsed transactions of customer from billing week.
     *
     * @param Transaction $compareTransaction Transaction to compare
     * @param string $typeOfTransaction Type of transaction (deposit | withdraw)
     *
     * @return ArrayCollection Array collection with transaction to parse
     */
    private function getPastNotParsedTransactions(
        Transaction $compareTransaction,
        string $typeOfTransaction
    ): ArrayCollection {
        $startWeek = $compareTransaction->getTransactionBillingWeek()['startOfWeek'];
        $endWeek = $compareTransaction->getTransactionBillingWeek()['endOfWeek'];

        $transactionToCheck = $this->transactions->filter(
            /**
             * @param Transaction $transaction
             * @return bool|void
             */
            function (Transaction $transaction) use ($compareTransaction, $startWeek, $endWeek, $typeOfTransaction) {
                if (
                    $transaction->getParsedStatus()
                        &&
                    $transaction->getCustomerId() === $compareTransaction->getCustomerId()
                        &&
                    $transaction->getTransactionTypeAsString() === $typeOfTransaction
                        &&
                    $transaction->getTransactionDate()->between($startWeek, $endWeek, true)
                ) {
                    return true;
                }
            }
        );

        $compareTransaction->setParsedStatus(true);

        return $transactionToCheck;
    }
}
