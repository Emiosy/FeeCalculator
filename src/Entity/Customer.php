<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;

class Customer
{
    /**
     * Customer account types
     */
    public const CUSTOMER_ACCOUNT_TYPES = [
        1 => 'private',
        2 => 'business',
    ];

    public function __construct(int $customerId, string $customerType)
    {
        $this->id = $customerId;
        $this->type = array_search($customerType, self::CUSTOMER_ACCOUNT_TYPES);
        $this->transactions = new ArrayCollection();
    }

    /**
     * Customer ID.
     *
     * @var int
     */
    private int $id;

    /**
     * Customer type.
     *
     * @var int
     */
    private int $type;

    /**
     * User transactions.
     *
     * @var ArrayCollection
     */
    private $transactions;

    /**
     * Getter for CustomerId.
     *
     * @return int|null
     */
    public function getCustomerId(): ?int
    {
        return $this->id;
    }

    /**
     * Getter for Customer type.
     *
     * @return string
     */
    public function getCustomerType()
    {
        return $this->type;
    }

    /**
     * Getter for Customer type as string.
     *
     * @return string
     */
    public function getCustomerTypeAsString(): string
    {
        return self::CUSTOMER_ACCOUNT_TYPES[$this->type];
    }

    public function addNewTransaction()
    {
        $this->transactions->add();
    }
}
