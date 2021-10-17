<?php

namespace App\Service;

use App\CurrenciesConfigParserTrait;
use App\Entity\Transaction;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CommissionFeeService
{
    use CurrenciesConfigParserTrait;

    /**
     * Array with decimal places of currencies.
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
     * Exchange rates Service.
     */
    private ExchangeRatesService $exchangeRates;

    /**
     * Parsed customers with transactions.
     */
    private ArrayCollection $transactions;

    /**
     * Collection with calculated commission fees.
     *
     * @var ArrayCollection Commission fees
     */
    private ArrayCollection $commissionFees;

    public function __construct(ContainerInterface $container, ExchangeRatesService $exchangeRatesService)
    {
        $this->decimalPlaces = $this->getParsedCurrenciesConfig($container, 'accept');
        $this->depositFees = $this->getParsedCurrenciesConfig($container, 'deposit');
        $this->withdrawFees = $this->getParsedCurrenciesNestedConfig($container, 'withdraw');
        $this->baseCurrency = $this->getPlainCurrenciesConfig($container, 'default');
        $this->exchangeRates = $exchangeRatesService;
        $this->transactions = new ArrayCollection();
        $this->commissionFees = new ArrayCollection();
    }

    /**
     * Calculate fee.
     *
     * @param string $filePath           Path to CSV file
     * @param array  $acceptedCurrencies Array with accepted currencies
     * @param array  $currencyRates      Array with currency rates
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
     * Analyze and count commissions for all transactions at collection.
     *
     * @param ArrayCollection $transactions  Array collection with transactions to analyze
     * @param array           $currencyRates Array with currency rates
     *
     * @return ArrayCollection Array collection with calculated commission fees
     */
    public function analyzeTransactions(ArrayCollection $transactions, array $currencyRates): ArrayCollection
    {
        if (!$transactions->isEmpty()) {
            foreach ($transactions as $transaction) {
                /** @var Transaction $transaction */
                $fee = (
                    (1 === $transaction->getTransactionType()) ?
                    $this->processDeposit($transaction) :
                    $this->processWithdraw($transaction, $currencyRates)
                );
                $this->commissionFees->add($fee);
            }
        }

        return $this->commissionFees;
    }

    /**
     * Read and parse CSV file with transactions.
     *
     * @param string $filePath           Path to CSV file
     * @param array  $acceptedCurrencies Array with accepted currencies
     *
     * @return ArrayCollection Array collection with Transaction objects
     */
    public function readAndParseTransactions(string $filePath, array $acceptedCurrencies): ArrayCollection
    {
        $fileToRead = fopen($filePath, 'r');
        while (($data = fgetcsv($fileToRead, 1000)) !== false) {
            //Check if currency is acceptable
            if (in_array((string) $data[5], array_keys($acceptedCurrencies))) {
                $transaction = $this->parseTransactionToObject($data, $acceptedCurrencies[(string) $data[5]]);

                $this->transactions->add($transaction);
            }
        }

        return $this->transactions;
    }

    /**
     * Ceil up with precision.
     *
     * @param string $value     Value to ceil up
     * @param int    $precision Precision to save
     *
     * @return float|int
     */
    public function ceilUp(string $value, int $precision)
    {
        $pow = pow(10, $precision);

        return (ceil($pow * $value) + ceil($pow * $value - ceil($pow * $value))) / $pow;
    }

    /**
     * Parse data to Transaction object.
     *
     * @param array $rowFromCsv         Array with parsed row from CSV
     * @param int   $decimalsOfCurrency Decimals of currency
     *
     * @return Transaction Transaction object
     */
    private function parseTransactionToObject(array $rowFromCsv, int $decimalsOfCurrency): Transaction
    {
        $carbonDate = Carbon::createFromFormat('Y-m-d', (string) $rowFromCsv[0]);
        $parsedAmount = ($rowFromCsv[4] * pow(10, $decimalsOfCurrency));

        return new Transaction(
            $carbonDate,
            (int) $rowFromCsv[1],
            (string) $rowFromCsv[2],
            (string) $rowFromCsv[3],
            (int) $parsedAmount,
            (string) $rowFromCsv[5],
            $decimalsOfCurrency
        );
    }

    /**
     * Process deposit transaction and calculate commission.
     *
     * @param Transaction $transaction Transaction to process
     *
     * @return string Amount of commission
     */
    private function processDeposit(Transaction $transaction): string
    {
        return $this->calculateCommissionForTransactionAmount(
            $transaction->getTransactionAmount(),
            $transaction->getTransactionCurrency(),
            $this->depositFees[$transaction->getCustomerTypeAsString()]
        );
    }

    /**
     * Process withdraw transaction and calculate commission fee.
     *
     * @param Transaction $transaction   Transaction to process
     * @param array       $currencyRates Array with currency rates
     *
     * @return string Amount of commission
     */
    private function processWithdraw(Transaction $transaction, array $currencyRates): string
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
            $this->getQuotaAtSpecificCurrency(
                $this->withdrawFees[$transaction->getCustomerTypeAsString()]['free_quota'],
                $transaction->getTransactionCurrency(),
                $currencyRates
            )
        );

        if (1 === bccomp($amountOverQuota, 0, 10)) {
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

        return $fee;
    }

    /**
     * Get quota for specific currency.
     *
     * @param string $baseQuota      Amount of quota at default currency
     * @param string $returnCurrency Output currency
     * @param array  $exchangeRates  Array with exchange rates
     *
     * @return string Quota at specific currency
     */
    private function getQuotaAtSpecificCurrency(
        string $baseQuota,
        string $returnCurrency,
        array $exchangeRates
    ): string {
        $recalculatedQuota = bcmul(
            number_format(
                $this->convertAmountToDecimal($baseQuota, $this->baseCurrency),
                $this->decimalPlaces[$this->baseCurrency],
                '.',
                ''
            ),
            $exchangeRates[$returnCurrency],
            10
        );

        return $this->convertAmountWithoutDecimal($recalculatedQuota, $returnCurrency);
    }

    /**
     * Get converted amount with decimals.
     *
     * @param string $amount   Amount without decimals
     * @param string $currency Currency to get decimal places
     *
     * @return string Amount with decimals
     */
    private function convertAmountToDecimal(string $amount, string $currency): string
    {
        if ($this->decimalPlaces[$currency] > 0) {
            $amount = bcdiv($amount, pow(10, $this->decimalPlaces[$currency]), 10);
        }

        return $amount;
    }

    /**
     * Get converted amount without decimals.
     *
     * @param string $amount   Amount with decimals
     * @param string $currency Currency to get decimal places
     *
     * @return string Amount without decimals
     */
    private function convertAmountWithoutDecimal(string $amount, string $currency): string
    {
        if ($this->decimalPlaces[$currency] > 0) {
            $amount = bcmul($amount, pow(10, $this->decimalPlaces[$currency]), 10);
        }

        return $amount;
    }

    /**
     * Calculate commission for transaction amount.
     *
     * @param string $amount   Amount without decimals to calculate fee
     * @param string $currency Currency of transaction to return correct format
     * @param string $fee      Amount of fee
     */
    private function calculateCommissionForTransactionAmount(string $amount, string $currency, string $fee): string
    {
        $amount = $this->convertAmountToDecimal($amount, $currency);

        //Calculate correct format of percents
        $percents = bcdiv($fee, 100, 10);
        //Calculate final fee
        $fee = bcmul($amount, $percents, 10);

        return number_format(
            $this->ceilUp($fee, $this->decimalPlaces[$currency]),
            $this->decimalPlaces[$currency],
            '.',
            ''
        );
    }

    /**
     * Check amount over quota.
     *
     * @param string $moneyWithdrawnAtWeek Sum amount of transaction from billing week
     * @param string $amountOfTransaction  Amount of actual transaction
     *
     * @return string Amount of transaction to calculate fee (If >0 user fee should be calculated)
     */
    private function checkAmountOverQuota(
        string $moneyWithdrawnAtWeek,
        string $amountOfTransaction,
        string $withdrawQuota
    ): string {
        //Check if full amount of transaction is over quota
        if (1 === bccomp($moneyWithdrawnAtWeek, $withdrawQuota, 10)) {
            //All amount is over quota, calculate fee for 100% of transaction
            return $amountOfTransaction;
        } else {
            //Free quota detected, calculate amount that is over quota
            $freeQuota = bcsub($withdrawQuota, $moneyWithdrawnAtWeek, 10);
            $amountOfTransaction = bcsub($amountOfTransaction, $freeQuota, 10);
        }

        //If $amountOfTransaction >0 user fee should be calculated
        return $amountOfTransaction;
    }

    /**
     * Money withdrawn at passed transactions.
     *
     * @param ArrayCollection $transactionToCheck Transaction of withdraw to check
     * @param array           $currencyRates      Currency rates to do a calculations
     *
     * @return string Amount of withdrawn money at base currency
     */
    private function calculateMoneyWithdrawnAtWeek(ArrayCollection $transactionToCheck, array $currencyRates): string
    {
        $moneyWithdrawn = 0;

        if (!$transactionToCheck->isEmpty()) {
            /** @var Transaction $transaction */
            foreach ($transactionToCheck as $transaction) {
                //Check if transaction currency is not at base currency
                if ($transaction->getTransactionCurrency() !== $this->baseCurrency) {
                    $moneyAtBaseCurrency = $this->exchangeRates->changeCurrencyFromBaseToForeign(
                        (string) $transaction->getTransactionAmount(),
                        (string) $currencyRates[$transaction->getTransactionCurrency()]
                    );
                    $moneyWithdrawn = bcadd(
                        $moneyWithdrawn,
                        $this->convertAmountWithoutDecimal($moneyAtBaseCurrency, $this->baseCurrency),
                        10
                    );
                } else {
                    $moneyWithdrawn = bcadd($moneyWithdrawn, $transaction->getTransactionAmount(), 10);
                }
            }
        }

        return (string) $moneyWithdrawn;
    }

    /**
     * Get all parsed transactions of customer from billing week.
     *
     * @param Transaction $compareTransaction Transaction to compare
     * @param string      $typeOfTransaction  Type of transaction (deposit | withdraw)
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
             * @return bool|void
             */
            function (Transaction $transaction) use ($compareTransaction, $startWeek, $endWeek, $typeOfTransaction) {
                if (
                    $transaction->isParsedStatus()
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
