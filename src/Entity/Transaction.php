<?php

namespace App\Entity;

use Carbon\Carbon;

class Transaction
{
    /**
     * Customer account types
     */
    public const TRANSACTION_TYPES = [
        1 => 'withdraw',
        2 => 'deposit',
    ];

    public function __construct(Carbon $date, string $type, int $amount, string $currency)
    {
        $this->date = $date;
        $this->type = array_search($type, self::TRANSACTION_TYPES);
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
     * Transction type.
     *
     * @var int
     */
    private int $type;

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
}
