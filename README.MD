# Fee calculator

App for parse CSV and calculate commission fees, based on data from file.

Software based on Symfony4 framework.

***

### Requirements
* PHP 7.4 with ext-bcmath
* Composer2

### Installation
1. Clone this repo 
```bash
git clone https://github.com/Emiosy/FeeCalculator.git
```
2. Copy `.env.example` and save as `.env`
3. Replace by Your API key to **exchangeratesapi.io** at `.env` file (*line 24*). You can get Your free API key [here](https://manage.exchangeratesapi.io/signup).
```dotenv
EXCHANGE_RATES_API_KEY=XXXXXXXXXXXXXXXXXXXXXXXXXX
```
**Warning: Free key allow You to use only `EUR` as base currency.**

4. Install all dependencies via Composer2
```php
php composer.phar install
```
5. Generate `APP_SECRET` by command
```php
php bin/console secrets:generate-keys
```
6. Setup Your currencies configuration at `config/currencies.yaml` or leave default. *More information about it You can find at Description section*

***

### Description
Code respects 2 types of transactions: 
* `deposit`
* `withdraw`

and 2 types of customers:
* `private`
* `business`
During calculation software download fresh currency rates from external API [exchangeratesapi.io](https://exchangeratesapi.io).
For more user can change at one place
* Base currency 
  * *default*: 
    * `EUR`, *3 letters of currency code*
* Accepted currencies **with decimal places for each**
  * *default*: 
    * `EUR` - `2`, *3 letters of currency code with decimal places*,
    * `USD` - `2`, *3 letters of currency code with decimal places*
    * `JPY` - `0`, *3 letters of currency code with decimal places*
* Rules of fees for deposit **for each role separately** 
  * *default*: 
    * `business` - `0.03%`, *value of fee, for each deposit*
    * `private` - `0.03%`. *value of fee, for each deposit*
* Rules of feed for withdraw **for each role separately**:
  * default:
    * `business`:
      * fee: `0.5%`, *value when user exceed `free_quota` or `free_transaction` limit*
      * free_quota: `0`, *value with included decimals example: 1EUR = 100*
      * free_transactions: `0`, *amount of free transaction per week*
    * `private`:
        * fee: `0.3%`, *value when user exceed `free_quota` or `free_transaction` limit*
        * free_quota: `100000`, *value with included decimals example: 1EUR = 100*
        * free_transactions: `3`, *amount of free transaction per week*