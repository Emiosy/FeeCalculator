<?php

namespace App\Command;

use App\Exception\ExchangeRatesException;
use App\Exception\FileException;
use App\Service\CommissionFeeService;
use App\Service\ExchangeRatesService;
use App\Service\FileService;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CalculateFeeCommand extends Command
{
    private FileService $fileService;
    private ParameterBagInterface $params;
    private CommissionFeeService $commissionFeeService;
    private ExchangeRatesService $exchangeRatesService;

    /**
     * Array with currency rates.
     *
     * @var array
     */
    private array $currencyRates;

    /**
     * Array with accepted currencies.
     *
     * @var array
     */
    private array $acceptedCurrencies;

    /**
     * Collection with Commission fees.
     *
     * @var ArrayCollection
     */
    private ArrayCollection $commissionFees;

    public function __construct(
        ParameterBagInterface $params,
        FileService $fileService,
        CommissionFeeService $commissionFeeService,
        ExchangeRatesService $exchangeRatesService,
        $name = null
    ) {
        parent::__construct($name);
        $this->params = $params;
        $this->fileService = $fileService;
        $this->exchangeRatesService = $exchangeRatesService;
        $this->commissionFeeService = $commissionFeeService;
        $this->acceptedCurrencies = $this->exchangeRatesService->getParsedAcceptedCurrencies();

        //TESTS
        $this->currencyRates = ['EUR' => 1, 'USD' => 1.159911, 'JPY' => 132.671222];
    }

    protected static $defaultName = 'fee:calculate';

    protected function configure(): void
    {
        $this
            ->addArgument('filePath', InputArgument::REQUIRED, 'Path to a file with transactions to parse.')
            ->setDescription('Calculate fees for transactions.')
            ->setHelp("This command calculate fees from input file");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting calculations');

        try {
            $this->fileService->checkFileAndExtension(
                $input->getArgument('filePath'),
                $this->params->get('file.extensionOfInputFile')
            );

//            $this->currencyRates = $this->exchangeRatesService->downloadLatestExchangeRates(
//                $this->params->get('exchangeApi.endpoint'),
//                $this->params->get('exchangeApi.key')
//            );

            $this->commissionFees = $this->commissionFeeService->calculateFee(
                $input->getArgument('filePath'),
                $this->acceptedCurrencies,
                $this->currencyRates
            );

            dd($this->commissionFees);
        } catch (FileException $e) {
            $output->writeln("Error with file - {$e->getMessage()}");
        } catch (ExchangeRatesException $e) {
            $output->writeln("Error with ExchangeRates API - {$e->getMessage()}");
        }

        $output->writeln('End of calculations');

        return 0;
    }
}
