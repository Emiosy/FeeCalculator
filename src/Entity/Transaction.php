<?php

namespace App\Entity;

use Carbon\Carbon;

class Transaction
{
    /**
     * Customer transaction types
     */
    public const TRANSACTION_TYPES = [
        1 => 'deposit',
        2 => 'withdraw',
    ];

    /**
     * Customer account types
     */
    public const CUSTOMER_ACCOUNT_TYPES = [
        1 => 'business',
        2 => 'private',
    ];

    public function __construct(
        Carbon $date,
        int $customerId,
        string $customerType,
        string $transactionType,
        int $amount,
        string $currency,
        int $amountDecimals
    ) {
        $this->date = $date;
        $this->customer_id = $customerId;
        $this->customer_type = array_search($customerType, self::CUSTOMER_ACCOUNT_TYPES);
        $this->transaction_type = array_search($transactionType, self::TRANSACTION_TYPES);
        $this->amount = $amount;
        $this->currency = $currency;
        $this->amount_decimals = $amountDecimals;
        $this->is_parsed = false;
    }

    /**
     * Transaction date.
     *
     * @var Carbon
     */
    private Carbon $date;

    /**
     * Customer Identifier.
     *
     * @var int
     */
    private int $customer_id;

    /**
     * Customer type.
     *
     * @var int
     */
    private int $customer_type;

    /**
     * Transaction type.
     *
     * @var int
     */
    private int $transaction_type;

    /**
     * Transaction value (without "pennies")
     *
     * @var int
     */
    private int $amount;

    /**
     * Transaction currency
     *
     * @var string
     */
    private string $currency;

    /**
     * Decimals at amount
     *
     * @var int
     */
    private int $amount_decimals;

    /**
     * Is parsed status.
     *
     * @var bool
     */
    private bool $is_parsed;

    /**
     * Getter for Transaction date
     *
     * @return Carbon
     */
    public function getTransactionDate(): Carbon
    {
        return $this->date;
    }

    /**
     * Getter for Transaction date as day of week.
     *
     * @return int
     */
    public function getTransactionDateAsDayOfWeek(): int
    {
        return $this->date->dayOfWeek;
    }

    /**
     * Getter for Transaction billing week.
     *
     * @return array
     */
    public function getTransactionBillingWeek(): array
    {
        return ['startOfWeek' => (clone($this->date))->startOfWeek(), 'endOfWeek' => (clone($this->date))->endOfWeek()];
    }

    /**
     * Getter for Transaction customer identifier.
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return $this->customer_id;
    }

    /**
     * Getter for customer type.
     *
     * @return string
     */
    public function getCustomerType()
    {
        return $this->customer_type;
    }

    /**
     * Getter for customer type as string.
     *
     * @return string
     */
    public function getCustomerTypeAsString(): string
    {
        return self::CUSTOMER_ACCOUNT_TYPES[$this->customer_type];
    }

    /**
     * Getter for Transaction type.
     *
     * @return string
     */
    public function getTransactionType()
    {
        return $this->transaction_type;
    }

    /**
     * Getter for Transaction type as string.
     *
     * @return string
     */
    public function getTransactionTypeAsString(): string
    {
        return self::TRANSACTION_TYPES[$this->transaction_type];
    }

    /**
     * Getter for Transaction amount at raw format.
     *
     * @return int
     */
    public function getTransactionAmount(): int
    {
        return $this->amount;
    }

    /**
     * Getter for Transaction amount at string format.
     *
     * @return string
     */
    public function getTransactionAmountAsString(): string
    {
        return number_format(($this->amount / pow(10, $this->amount_decimals)), $this->amount_decimals, '.', '');
    }

    /**
     * Getter for Transaction currency
     *
     * @return string
     */
    public function getTransactionCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Getter for Transaction amount decimal places.
     *
     * @return int
     */
    public function getAmountDecimalPlaces(): int
    {
        return $this->amount_decimals;
    }

    /**
     * Getter for parsed status.
     *
     * @return bool
     */
    public function getParsedStatus(): bool
    {
        return $this->is_parsed;
    }

    /**
     * Setter for parsed status.
     *
     * @param bool $parsedStatus
     */
    public function setParsedStatus(bool $parsedStatus): void
    {
        $this->is_parsed = $parsedStatus;
    }
}
