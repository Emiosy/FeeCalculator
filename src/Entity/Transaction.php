<?php

namespace App\Entity;

use Carbon\Carbon;

class Transaction
{
    /**
     * Customer transaction types
     */
    public const TRANSACTION_TYPES = [
        1 => 'withdraw',
        2 => 'deposit',
    ];

    /**
     * Customer account types
     */
    public const CUSTOMER_ACCOUNT_TYPES = [
        1 => 'private',
        2 => 'business',
    ];

    public function __construct(
        Carbon $date,
        int $customerId,
        string $customerType,
        string $transactionType,
        int $amount,
        string $currency
    ) {
        $this->date = $date;
        $this->customer_id = $customerId;
        $this->customer_type = array_search($customerType, self::CUSTOMER_ACCOUNT_TYPES);
        $this->transaction_type = array_search($transactionType, self::TRANSACTION_TYPES);
        $this->amount = $amount;
        $this->currency = $currency;
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
     */
    private string $currency;

    /**
     * Getter for Customer type.
     *
     * @return string
     */
    public function getCustomerType()
    {
        return $this->customer_type;
    }

    /**
     * Getter for Customer type as string.
     *
     * @return string
     */
    public function getCustomerTypeAsString(): string
    {
        return self::CUSTOMER_ACCOUNT_TYPES[$this->customer_type];
    }
}
